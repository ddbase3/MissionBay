<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionReview;
use Base3\AssistantFoundation\Dto\AgentMutationCommitDecision;
use Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IAgentMutationGuardedTool;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Audit\AgentToolAuditContext;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Event\MissionBayToolFailedEvent;
use Base3\MissionBay\Event\MissionBayToolFinishedEvent;
use Base3\MissionBay\Event\MissionBayToolStartedEvent;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use Base3\MissionBay\Resource\ConfiguredAgentToolResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Base3\MissionBay\Resource\ConfiguredAgentToolResource::class)]
final class ConfiguredAgentToolResourceMutationGuardTest extends TestCase {

	public function testConfiguredWrapperForwardsMutationGuardToDockedTool(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(fn(mixed $value): mixed => $value);
		$context = $this->createStub(IAgentContext::class);
		$tool = new GuardedConfiguredToolTestDouble();
		$wrapper = new ConfiguredAgentToolResource(
			$resolver,
			$this->createStub(IEventManager::class),
			'configured_userprefs'
		);
		$wrapper->setConfig(['namespace' => 'prefs']);
		$wrapper->init(['tool' => [$tool]], $context);

		$this->assertInstanceOf(IAgentMutationGuardedTool::class, $wrapper);
		$this->assertSame('prefs__set_user_pref', $wrapper->getToolDefinitions()[0]['function']['name']);

		$action = new AgentAction(
			'call-1',
			AgentAction::TYPE_TOOL_CALL,
			'prefs__set_user_pref',
			['key' => 'addressing_style', 'value' => 'informal']
		);
		$snapshot = $wrapper->captureMutationCommitSnapshot($action, 'fingerprint-1', $context);
		$review = $wrapper->getActionReview($action, $snapshot, $context);
		$decision = $wrapper->validateMutationCommit($action, $snapshot, $context);
		$result = $wrapper->callTool('prefs__set_user_pref', $action->getInput(), $context);

		$this->assertSame('set_user_pref', $tool->getCapturedActionName());
		$this->assertSame('set_user_pref', $tool->getReviewedActionName());
		$this->assertSame('Set user preference', $review->getTitle());
		$this->assertSame('set_user_pref', $tool->getValidatedActionName());
		$this->assertSame('set_user_pref', $tool->getCalledToolName());
		$this->assertSame('fingerprint-1', $snapshot->getActionFingerprint());
		$this->assertTrue($decision->isAllowed());
		$this->assertSame(['ok' => true], $result);
	}

	public function testCommitGuardServiceFindsGuardThroughConfiguredWrapper(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(fn(mixed $value): mixed => $value);
		$tool = new GuardedConfiguredToolTestDouble();
		$wrapper = new ConfiguredAgentToolResource(
			$resolver,
			$this->createStub(IEventManager::class),
			'configured_userprefs'
		);
		$context = $this->createStub(IAgentContext::class);
		$wrapper->init(['tool' => [$tool]], $context);
		$definitions = $wrapper->getToolDefinitions();
		$context->method('getVar')->willReturnCallback(
			fn(string $key): mixed => match ($key) {
				AgentToolLoopContextKeys::TOOL_DEFINITIONS => $definitions,
				AgentToolLoopContextKeys::TOOLS => [$wrapper],
				default => null
			}
		);
		$call = new AiToolCall(
			'call-service',
			'set_user_pref',
			['key' => 'addressing_style', 'value' => 'informal']
		);
		$action = new AgentAction(
			$call->getId(),
			AgentAction::TYPE_TOOL_CALL,
			$call->getName(),
			$call->getArguments()
		);
		$service = new AgentMutationCommitGuardService(new AgentActionFingerprint());

		$snapshot = $service->capture($action, $call, $context);

		$this->assertInstanceOf(AgentMutationCommitSnapshot::class, $snapshot);
		$this->assertSame('set_user_pref', $tool->getCapturedActionName());
	}

	public function testConfiguredWrapperEmitsCorrelatedExecutionEvents(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(fn(mixed $value): mixed => $value);
		$eventManager = new ConfiguredToolRecordingEventManager();
		$context = new AgentContext();
		$tool = new GuardedConfiguredToolTestDouble();
		$wrapper = new ConfiguredAgentToolResource($resolver, $eventManager, 'configured_userprefs');
		$wrapper->setConfig(['namespace' => 'prefs']);
		$wrapper->init(['tool' => [$tool]], $context);
		$wrapper->getToolDefinitions();

		AgentToolAuditContext::push($context, [
			'source' => AgentToolAuditContext::SOURCE_AGENT,
			'node_id' => 'node-1',
			'call_id' => 'call-audit-1',
			'iteration' => 2,
			'call_index' => 3,
			'trace' => [
				'turn_id' => 'turn-1',
				'chatbot_key' => 'chatbot-1'
			]
		]);

		$result = $wrapper->callTool('prefs__set_user_pref', ['key' => 'tone'], $context);
		$events = $eventManager->getFiredEvents();

		$this->assertSame(['ok' => true], $result);
		$this->assertCount(2, $events);
		$this->assertInstanceOf(MissionBayToolStartedEvent::class, $events[0]);
		$this->assertInstanceOf(MissionBayToolFinishedEvent::class, $events[1]);
		$this->assertSame('node-1', $events[0]->getNodeId());
		$this->assertSame('call-audit-1', $events[0]->getCallId());
		$this->assertSame('prefs__set_user_pref', $events[0]->getToolName());
		$this->assertSame('set_user_pref', $events[0]->getTrace()['original_tool_name']);
		$this->assertSame('call-audit-1', $events[1]->getCallId());
	}

	public function testConfiguredWrapperEmitsFailedEventAndRethrowsToolError(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(fn(mixed $value): mixed => $value);
		$eventManager = new ConfiguredToolRecordingEventManager();
		$context = new AgentContext();
		$wrapper = new ConfiguredAgentToolResource($resolver, $eventManager, 'configured_failure');
		$wrapper->init(['tool' => [new FailingConfiguredToolTestDouble()]], $context);
		$wrapper->getToolDefinitions();

		try {
			$wrapper->callTool('failing_tool', [], $context);
			$this->fail('Expected configured tool exception.');
		}
		catch(\RuntimeException $e) {
			$this->assertSame('Configured tool failed.', $e->getMessage());
		}

		$events = $eventManager->getFiredEvents();
		$this->assertCount(2, $events);
		$this->assertInstanceOf(MissionBayToolStartedEvent::class, $events[0]);
		$this->assertInstanceOf(MissionBayToolFailedEvent::class, $events[1]);
		$this->assertSame('Configured tool failed.', $events[1]->getErrorMessage());
	}

	public function testConfiguredWrapperRejectsGuardedMutationWhenDockedToolHasNoGuard(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(fn(mixed $value): mixed => $value);
		$context = $this->createStub(IAgentContext::class);
		$wrapper = new ConfiguredAgentToolResource(
			$resolver,
			$this->createStub(IEventManager::class),
			'configured_unguarded'
		);
		$wrapper->init(['tool' => [new UnguardedConfiguredToolTestDouble()]], $context);
		$wrapper->getToolDefinitions();

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage(
			'Configured mutation tool does not implement IAgentMutationGuardedTool: set_user_pref'
		);

		$wrapper->captureMutationCommitSnapshot(
			new AgentAction(
				'call-2',
				AgentAction::TYPE_TOOL_CALL,
				'set_user_pref',
				['key' => 'addressing_style', 'value' => 'informal']
			),
			'fingerprint-2',
			$context
		);
	}
}

require_once __DIR__ . '/GuardedConfiguredToolTestDouble.php';
require_once __DIR__ . '/UnguardedConfiguredToolTestDouble.php';
require_once __DIR__ . '/ConfiguredToolRecordingEventManager.php';
require_once __DIR__ . '/FailingConfiguredToolTestDouble.php';

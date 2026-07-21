<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionReview;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
use MissionBay\Audit\AgentToolAuditContext;
use MissionBay\Context\AgentContext;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Resource\ConfiguredAgentToolResource;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MissionBay\Resource\ConfiguredAgentToolResource
 */
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

final class GuardedConfiguredToolTestDouble implements IAgentTool, IAgentMutationGuardedTool {

	private string $capturedActionName = '';
	private string $validatedActionName = '';
	private string $reviewedActionName = '';
	private string $calledToolName = '';

	public static function getName(): string {
		return 'guardedconfiguredtooltestdouble';
	}

	public function getDescription(): string {
		return 'Guarded configured tool test double.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'mutation' => true,
			'requiresApproval' => true,
			'commitGuardRequired' => true,
			'function' => [
				'name' => 'set_user_pref',
				'description' => 'Sets a user preference.',
				'parameters' => [
					'type' => 'object',
					'properties' => [],
					'required' => []
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->calledToolName = $name;
		return ['ok' => true];
	}

	public function captureMutationCommitSnapshot(
		AgentAction $action,
		string $actionFingerprint,
		IAgentContext $context
	): AgentMutationCommitSnapshot {
		$this->capturedActionName = $action->getName();

		return new AgentMutationCommitSnapshot(
			$action->getId(),
			$actionFingerprint,
			[],
			[],
			metadata: ['review' => ['operation' => 'Set user preference']]
		);
	}

	public function getActionReview(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentActionReview {
		$this->reviewedActionName = $action->getName();
		return new AgentActionReview(
			'Set user preference',
			'The user preference will be changed.',
			['Preference' => (string)($action->getInput()['key'] ?? '')]
		);
	}

	public function validateMutationCommit(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentMutationCommitDecision {
		$this->validatedActionName = $action->getName();
		return AgentMutationCommitDecision::allow('Guard forwarded.');
	}

	public function getCapturedActionName(): string {
		return $this->capturedActionName;
	}

	public function getReviewedActionName(): string {
		return $this->reviewedActionName;
	}

	public function getValidatedActionName(): string {
		return $this->validatedActionName;
	}

	public function getCalledToolName(): string {
		return $this->calledToolName;
	}
}

final class UnguardedConfiguredToolTestDouble implements IAgentTool {

	public static function getName(): string {
		return 'unguardedconfiguredtooltestdouble';
	}

	public function getDescription(): string {
		return 'Unguarded configured tool test double.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'mutation' => true,
			'requiresApproval' => true,
			'commitGuardRequired' => true,
			'function' => [
				'name' => 'set_user_pref',
				'description' => 'Sets a user preference.',
				'parameters' => [
					'type' => 'object',
					'properties' => [],
					'required' => []
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return ['ok' => true];
	}
}

final class ConfiguredToolRecordingEventManager implements IEventManager {

	/** @var array<int,object|string> */
	private array $events = [];

	public function on(string $event, callable $listener, int $priority = 0): void {
	}

	public function once(string $event, callable $listener, int $priority = 0): void {
	}

	public function off(string $event, callable $listener): void {
	}

	public function fire(object|string $event, ...$args): array {
		$this->events[] = $event;
		return [];
	}

	/** @return array<int,object|string> */
	public function getFiredEvents(): array {
		return $this->events;
	}
}

final class FailingConfiguredToolTestDouble implements IAgentTool {

	public static function getName(): string {
		return 'failingconfiguredtooltestdouble';
	}

	public function getDescription(): string {
		return 'Failing configured tool test double.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'failing_tool',
				'description' => 'Always fails.',
				'parameters' => [
					'type' => 'object',
					'properties' => []
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		throw new \RuntimeException('Configured tool failed.');
	}
}

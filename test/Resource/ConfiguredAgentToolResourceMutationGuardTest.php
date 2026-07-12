<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
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
		$wrapper = new ConfiguredAgentToolResource($resolver, 'configured_userprefs');
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
		$decision = $wrapper->validateMutationCommit($action, $snapshot, $context);
		$result = $wrapper->callTool('prefs__set_user_pref', $action->getInput(), $context);

		$this->assertSame('set_user_pref', $tool->getCapturedActionName());
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
		$wrapper = new ConfiguredAgentToolResource($resolver, 'configured_userprefs');
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

	public function testConfiguredWrapperRejectsGuardedMutationWhenDockedToolHasNoGuard(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(fn(mixed $value): mixed => $value);
		$context = $this->createStub(IAgentContext::class);
		$wrapper = new ConfiguredAgentToolResource($resolver, 'configured_unguarded');
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

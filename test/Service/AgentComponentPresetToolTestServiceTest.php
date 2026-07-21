<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentActionPolicy;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Policy\IAgentActionPolicyResolver;
use MissionBay\Orchestrator\Service\AgentActionResumeService;
use MissionBay\Orchestrator\Service\AgentActionReviewService;
use MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use MissionBay\Orchestrator\Service\AgentInteractionResponseResolver;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Validation\JsonSchemaValidator;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use MissionBay\Policy\MutationApprovalAgentActionPolicy;
use MissionBay\Service\AgentComponentPresetToolTestService;
use PHPUnit\Framework\TestCase;

final class AgentComponentPresetToolTestServiceTest extends TestCase {

	public function testMutationIsSuspendedAndExecutesExactlyOnceAfterApproval(): void {
		$tool = new ComponentPresetApprovalMutationTool();
		$service = $this->createService();
		$first = $service->invoke(
			$tool,
			'set_value',
			['value' => 'A'],
			new AgentContext()
		);

		$this->assertSame('confirmation_required', $first['status'] ?? null);
		$this->assertTrue($first['requires_confirmation'] ?? false);
		$this->assertSame(0, $tool->getCallCount());

		$request = $first['interaction_requests'][0] ?? [];
		$approved = $service->resume(
			$tool,
			(string)($first['resume_handle'] ?? ''),
			(string)($request['id'] ?? ''),
			'approve',
			'',
			new AgentContext()
		);

		$this->assertTrue($approved['ok'] ?? false);
		$this->assertSame('executed', $approved['status'] ?? null);
		$this->assertSame(1, $tool->getCallCount());
		$this->assertSame(['value' => 'A'], $tool->getLastArguments());
	}

	public function testDeniedMutationIsNotExecuted(): void {
		$tool = new ComponentPresetApprovalMutationTool();
		$service = $this->createService();
		$first = $service->invoke(
			$tool,
			'set_value',
			['value' => 'B'],
			new AgentContext()
		);
		$request = $first['interaction_requests'][0] ?? [];
		$denied = $service->resume(
			$tool,
			(string)($first['resume_handle'] ?? ''),
			(string)($request['id'] ?? ''),
			'deny',
			'Do not execute this action.',
			new AgentContext()
		);

		$this->assertSame(0, $tool->getCallCount());
		$this->assertContains(
			$denied['status'] ?? null,
			['blocked_or_failed', 'declined_or_no_execution']
		);
	}

	private function createService(): AgentComponentPresetToolTestService {
		$events = new ComponentPresetTestEventManager();
		$fingerprint = new AgentActionFingerprint();
		$mutationCommitGuard = new AgentMutationCommitGuardService(
			$fingerprint,
			$events,
			new AgentToolDefinitionSemantics()
		);
		$contractValidation = new AgentToolContractValidationService(new JsonSchemaValidator());
		$suspensions = new ComponentPresetTestSuspensionRepository();
		$review = new AgentActionReviewService(
			$fingerprint,
			$suspensions,
			900,
			$mutationCommitGuard,
			$events
		);

		return new AgentComponentPresetToolTestService(
			new ComponentPresetTestActionPolicyResolver(),
			$fingerprint,
			$review,
			new AgentActionResumeService(
				$fingerprint,
				$suspensions,
				$events,
				new AgentInteractionResponseResolver()
			),
			$contractValidation,
			new AgentCapabilitySelectionGuardService(),
			$mutationCommitGuard,
			$events,
			new AgentToolDefinitionSemantics()
		);
	}
}

final class ComponentPresetTestActionPolicyResolver implements IAgentActionPolicyResolver {

	/** @return array<int,IAgentActionPolicy> */
	public function resolve(array $policyIds): array {
		return [
			new MutationApprovalAgentActionPolicy(),
			new AllowAllAgentActionPolicy()
		];
	}
}

final class ComponentPresetTestSuspensionRepository implements IAgentSuspensionRepository {

	/** @var array<string,AgentSuspension> */
	private array $suspensions = [];

	public function create(AgentSuspension $suspension, int $ttlSeconds): string {
		$handle = 'component-preset-test-' . count($this->suspensions);
		$this->suspensions[$handle] = $suspension;
		return $handle;
	}

	public function claim(string $resumeHandle): AgentSuspensionClaim {
		if(!isset($this->suspensions[$resumeHandle])) {
			throw new \RuntimeException('Unknown resume handle.');
		}

		return new AgentSuspensionClaim(
			$resumeHandle,
			'component-preset-test-claim',
			$this->suspensions[$resumeHandle]
		);
	}

	public function release(AgentSuspensionClaim $claim): void {
	}

	public function consume(AgentSuspensionClaim $claim): void {
		unset($this->suspensions[$claim->getResumeHandle()]);
	}
}

final class ComponentPresetTestEventManager implements IEventManager {

	public function on(string $event, callable $listener, int $priority = 0): void {
	}

	public function once(string $event, callable $listener, int $priority = 0): void {
	}

	public function off(string $event, callable $listener): void {
	}

	public function fire(object|string $event, ...$args): array {
		return [];
	}
}

final class ComponentPresetApprovalMutationTool implements IAgentTool, IAgentMutationGuardedTool {

	private int $callCount = 0;

	/** @var array<string,mixed> */
	private array $lastArguments = [];

	public static function getName(): string {
		return 'componentpresetapprovalmutationtool';
	}

	public function getDescription(): string {
		return 'Mutation tool used by the component preset tester regression test.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'mutation' => true,
			'requiresApproval' => true,
			'commitGuardRequired' => true,
			'function' => [
				'name' => 'set_value',
				'description' => 'Sets a value.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'value' => ['type' => 'string']
					],
					'required' => ['value'],
					'additionalProperties' => false
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->callCount++;
		$this->lastArguments = $arguments;

		return [
			'ok' => true,
			'value' => $arguments['value'] ?? null
		];
	}

	public function captureMutationCommitSnapshot(
		AgentAction $action,
		string $actionFingerprint,
		IAgentContext $context
	): AgentMutationCommitSnapshot {
		return new AgentMutationCommitSnapshot($action->getId(), $actionFingerprint);
	}

	public function validateMutationCommit(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentMutationCommitDecision {
		return AgentMutationCommitDecision::allow('Mutation remains valid.');
	}

	public function getCallCount(): int {
		return $this->callCount;
	}

	/** @return array<string,mixed> */
	public function getLastArguments(): array {
		return $this->lastArguments;
	}
}

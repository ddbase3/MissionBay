<?php declare(strict_types=1);

namespace MissionBay\Test\Tool\Profile;

final class MutationToolSetSmokeDouble1 implements \MissionBay\Api\IAgentTool, \MissionBay\Api\IAgentMutationGuardedTool {
		public int $calls = 0;
		public function __construct(private readonly array $definition) {}
		public static function getName(): string { return 'smokeguardedmutationtool'; }
		public function getToolDefinitions(): array { return [$this->definition]; }
		public function callTool(string $name, array $arguments, \AssistantFoundation\Api\IAgentContext $context): mixed {
			$this->calls++;
			return ['stored' => $arguments['value'] ?? null];
		}
		public function captureMutationCommitSnapshot(\AssistantFoundation\Dto\AgentAction $action, string $actionFingerprint, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentMutationCommitSnapshot {
			return new \AssistantFoundation\Dto\AgentMutationCommitSnapshot(
				$action->getId(),
				$actionFingerprint,
				['owner' => 'smoke'],
				['record' => 'v1']
			);
		}
		public function getActionReview(\AssistantFoundation\Dto\AgentAction $action, \AssistantFoundation\Dto\AgentMutationCommitSnapshot $snapshot, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentActionReview {
			return new \AssistantFoundation\Dto\AgentActionReview(
				'Confirm smoke update',
				'Update the controlled smoke value?',
				['Value' => $action->getInput()['value'] ?? '']
			);
		}
		public function validateMutationCommit(\AssistantFoundation\Dto\AgentAction $action, \AssistantFoundation\Dto\AgentMutationCommitSnapshot $snapshot, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentMutationCommitDecision {
			return ($snapshot->getResourceVersions()['record'] ?? '') === 'v1'
				? \AssistantFoundation\Dto\AgentMutationCommitDecision::allow('Smoke snapshot is current.')
				: \AssistantFoundation\Dto\AgentMutationCommitDecision::deny(\AssistantFoundation\Dto\AgentMutationCommitDecision::CODE_STALE, 'Smoke snapshot is stale.');
		}
	}

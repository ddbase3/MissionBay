<?php declare(strict_types=1);

namespace MissionBay\Test\Tool\Profile;

final class BatchToolSetSmokeDouble1 implements \MissionBay\Api\IAgentTool, \MissionBay\Api\IAgentMutationGuardedTool {
		public array $calls = [];
		public function __construct(private readonly array $definition) {}
		public static function getName(): string { return 'smokebatchtargettool'; }
		public function getToolDefinitions(): array { return [$this->definition]; }
		public function callTool(string $name, array $arguments, \AssistantFoundation\Api\IAgentContext $context): mixed {
			$this->calls[] = $arguments;
			return ['ok' => true, 'plugin' => $arguments['plugin'], 'state' => $arguments['state']];
		}
		public function captureMutationCommitSnapshot(\AssistantFoundation\Dto\AgentAction $action, string $actionFingerprint, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentMutationCommitSnapshot {
			return new \AssistantFoundation\Dto\AgentMutationCommitSnapshot(
				$action->getId(),
				$actionFingerprint,
				['owner' => 'smoke'],
				['plugin' => 'v1'],
				metadata: ['plugin' => $action->getInput()['plugin'] ?? '']
			);
		}
		public function getActionReview(\AssistantFoundation\Dto\AgentAction $action, \AssistantFoundation\Dto\AgentMutationCommitSnapshot $snapshot, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentActionReview {
			return new \AssistantFoundation\Dto\AgentActionReview(
				'Confirm smoke plugin state',
				'Set one plugin state.',
				['Plugin' => $action->getInput()['plugin'] ?? '', 'State' => $action->getInput()['state'] ?? '']
			);
		}
		public function validateMutationCommit(\AssistantFoundation\Dto\AgentAction $action, \AssistantFoundation\Dto\AgentMutationCommitSnapshot $snapshot, \AssistantFoundation\Api\IAgentContext $context): \AssistantFoundation\Dto\AgentMutationCommitDecision {
			return ($snapshot->getResourceVersions()['plugin'] ?? '') === 'v1'
				? \AssistantFoundation\Dto\AgentMutationCommitDecision::allow('Smoke plugin snapshot is current.')
				: \AssistantFoundation\Dto\AgentMutationCommitDecision::deny(\AssistantFoundation\Dto\AgentMutationCommitDecision::CODE_STALE, 'Smoke plugin snapshot is stale.');
		}
	}

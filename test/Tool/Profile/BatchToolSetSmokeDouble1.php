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

namespace Base3\MissionBay\Test\Tool\Profile;

final class BatchToolSetSmokeDouble1 implements \Base3\MissionBay\Api\IAgentTool, \Base3\MissionBay\Api\IAgentMutationGuardedTool {
		public array $calls = [];
		public function __construct(private readonly array $definition) {}
		public static function getName(): string { return 'smokebatchtargettool'; }
		public function getToolDefinitions(): array { return [$this->definition]; }
		public function callTool(string $name, array $arguments, \Base3\AssistantFoundation\Api\IAgentContext $context): mixed {
			$this->calls[] = $arguments;
			return ['ok' => true, 'plugin' => $arguments['plugin'], 'state' => $arguments['state']];
		}
		public function captureMutationCommitSnapshot(\Base3\AssistantFoundation\Dto\AgentAction $action, string $actionFingerprint, \Base3\AssistantFoundation\Api\IAgentContext $context): \Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot {
			return new \Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot(
				$action->getId(),
				$actionFingerprint,
				['owner' => 'smoke'],
				['plugin' => 'v1'],
				metadata: ['plugin' => $action->getInput()['plugin'] ?? '']
			);
		}
		public function getActionReview(\Base3\AssistantFoundation\Dto\AgentAction $action, \Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot $snapshot, \Base3\AssistantFoundation\Api\IAgentContext $context): \Base3\AssistantFoundation\Dto\AgentActionReview {
			return new \Base3\AssistantFoundation\Dto\AgentActionReview(
				'Confirm smoke plugin state',
				'Set one plugin state.',
				['Plugin' => $action->getInput()['plugin'] ?? '', 'State' => $action->getInput()['state'] ?? '']
			);
		}
		public function validateMutationCommit(\Base3\AssistantFoundation\Dto\AgentAction $action, \Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot $snapshot, \Base3\AssistantFoundation\Api\IAgentContext $context): \Base3\AssistantFoundation\Dto\AgentMutationCommitDecision {
			return ($snapshot->getResourceVersions()['plugin'] ?? '') === 'v1'
				? \Base3\AssistantFoundation\Dto\AgentMutationCommitDecision::allow('Smoke plugin snapshot is current.')
				: \Base3\AssistantFoundation\Dto\AgentMutationCommitDecision::deny(\Base3\AssistantFoundation\Dto\AgentMutationCommitDecision::CODE_STALE, 'Smoke plugin snapshot is stale.');
		}
	}

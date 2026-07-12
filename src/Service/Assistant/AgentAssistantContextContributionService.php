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

namespace MissionBay\Service\Assistant;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentAssistantContextContributionService;
use MissionBay\Api\IAgentMemoryRoleResolver;
use MissionBay\Orchestrator\AgentStateSynchronizer;

final class AgentAssistantContextContributionService implements IAgentAssistantContextContributionService {

	public function __construct(
		private readonly IAgentMemoryRoleResolver $roleResolver,
		private readonly ?AgentStateSynchronizer $stateSynchronizer = null
	) {
	}

	public function buildMessages(array $resources, IAgentContext $context, ?ILogger $logger = null): array {
		$entries = [];
		$sequence = 0;

		foreach ($this->deduplicateResources($resources) as $resource) {
			if (!$resource instanceof IAgentContextContributor) {
				continue;
			}

			if ($resource instanceof IAgentMemory && !$this->roleResolver->isContextContributor($resource)) {
				continue;
			}

			try {
				foreach ($resource->contribute($context) as $block) {
					if (!$block instanceof AgentInstructionBlock) {
						$this->logError($logger, 'Context contributor returned an invalid instruction block: ' . $resource::class);
						continue;
					}

					$entries[] = [
						'contributor_priority' => $resource->getPriority(),
						'block_priority' => $block->getPriority(),
						'sequence' => $sequence++,
						'block' => $block
					];
				}
			}
			catch (\Throwable $e) {
				$this->logError($logger, 'Context contribution failed for ' . $resource::class . ': ' . $e->getMessage());
			}
		}

		usort($entries, static function(array $left, array $right): int {
			$result = ((int)$left['contributor_priority']) <=> ((int)$right['contributor_priority']);
			if ($result !== 0) {
				return $result;
			}

			$result = ((int)$left['block_priority']) <=> ((int)$right['block_priority']);
			if ($result !== 0) {
				return $result;
			}

			/** @var AgentInstructionBlock $leftBlock */
			$leftBlock = $left['block'];
			/** @var AgentInstructionBlock $rightBlock */
			$rightBlock = $right['block'];
			$result = strcmp($leftBlock->getId(), $rightBlock->getId());

			return $result !== 0 ? $result : ((int)$left['sequence']) <=> ((int)$right['sequence']);
		});

		$diagnostics = [];
		$messages = [];
		foreach ($entries as $entry) {
			/** @var AgentInstructionBlock $block */
			$block = $entry['block'];
			$diagnostics[] = $block->toDiagnosticArray();
			$messages[] = $block->toMessage();
		}

		try {
			$context->setVar('agent_context_contributions', $diagnostics);
			($this->stateSynchronizer ?? new AgentStateSynchronizer())
				->updateContextContributions($context, $diagnostics);
		}
		catch (\Throwable $e) {
			$this->logError($logger, 'Context contribution diagnostics could not be stored: ' . $e->getMessage());
		}

		return $messages;
	}

	/**
	 * @param array<int,object> $resources
	 * @return array<int,object>
	 */
	private function deduplicateResources(array $resources): array {
		$result = [];
		$seen = [];

		foreach ($resources as $resource) {
			if (!is_object($resource)) {
				continue;
			}

			$id = spl_object_id($resource);
			if (isset($seen[$id])) {
				continue;
			}

			$seen[$id] = true;
			$result[] = $resource;
		}

		return $result;
	}

	private function logError(?ILogger $logger, string $message): void {
		if ($logger === null) {
			return;
		}

		$logger->log('agentassistantcontextcontributionservice', '[ERROR] ' . $message);
	}
}

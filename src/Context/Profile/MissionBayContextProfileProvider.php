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

namespace MissionBay\Context\Profile;

use AssistantFoundation\Api\IAgentContextProfileProvider;
use AssistantFoundation\Dto\AgentContextProfileResult;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentInstructionBlock;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Profile\AgentContextProfileResolver;

/**
 * Exposes the existing MissionBay context-profile store to all agent runtimes.
 */
final class MissionBayContextProfileProvider implements IAgentContextProfileProvider {

	public function __construct(
		private readonly AgentContextProfileResolver $profileResolver,
		private readonly IAgentComponentPresetMaterializer $presetMaterializer
	) {}

	public static function getName(): string {
		return 'missionbaycontextprofileprovider';
	}

	public static function getProviderId(): string {
		return 'missionbay';
	}

	public function getOptions(): array {
		return $this->profileResolver->getOptions();
	}

	public function hasProfile(string $profileId): bool {
		return $this->profileResolver->hasProfile($profileId);
	}

	public function build(
		string $profileId,
		AgentExecutionRequest $request
	): AgentContextProfileResult {
		$profile = $this->profileResolver->getProfile($profileId);
		if (empty($profile['enabled'])) {
			throw new \RuntimeException('Context profile is disabled: ' . $profileId);
		}

		$context = $this->presetMaterializer->createContext(array_replace(
			$request->getContext(),
			[
				'agent_configuration' => $request->getAgentConfiguration(),
				'agent_inputs' => $request->getInputs(),
				'context_profile' => $profileId
			]
		));
		$entries = [];
		$warnings = [];
		$sequence = 0;

		foreach ($profile['presets'] ?? [] as $presetId) {
			$presetId = trim((string)$presetId);
			if ($presetId === '') {
				continue;
			}

			$materialization = $this->presetMaterializer->materialize($presetId, $context);
			$warnings = array_merge($warnings, $materialization->getWarnings());
			$contributor = $materialization->getContextContributor();
			if ($contributor === null) {
				$warnings[] = 'Context profile preset produced no context contributor: ' . $presetId;
				continue;
			}

			try {
				foreach ($contributor->contribute($context) as $block) {
					if (!$block instanceof AgentInstructionBlock) {
						$warnings[] = 'Context contributor returned an invalid instruction block: ' . $presetId;
						continue;
					}

					$entries[] = [
						'contributor_priority' => $contributor->getPriority(),
						'block_priority' => $block->getPriority(),
						'source' => $block->getSource(),
						'id' => $block->getId(),
						'sequence' => $sequence++,
						'block' => $block
					];
				}
			}
			catch (\Throwable $e) {
				$warnings[] = 'Context contribution failed for ' . $presetId . ': ' . $e->getMessage();
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

			$result = strcmp((string)$left['source'], (string)$right['source']);
			if ($result !== 0) {
				return $result;
			}

			$result = strcmp((string)$left['id'], (string)$right['id']);
			return $result !== 0 ? $result : ((int)$left['sequence']) <=> ((int)$right['sequence']);
		});

		$blocks = array_map(
			static fn(array $entry): AgentInstructionBlock => $entry['block'],
			$entries
		);

		return new AgentContextProfileResult(
			$profileId,
			$blocks,
			array_values(array_unique(array_filter(array_map('trim', $warnings))))
		);
	}
}

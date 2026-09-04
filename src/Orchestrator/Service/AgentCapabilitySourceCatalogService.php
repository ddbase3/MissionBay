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

namespace MissionBay\Orchestrator\Service;

use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelection;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use MissionBay\Api\IAgentCapabilitySourceMetadata;
use MissionBay\Api\IAgentTool;
use MissionBay\Capability\HybridAgentCapabilitySelector;

/**
 * Builds the compact source universe used by the agent-selected native
 * orchestrator and resolves one exact active source set to callable functions.
 *
 * Hard capability filters remain authoritative. No relevance ranking happens
 * here: the main agent chooses sources from the complete eligible universe.
 */
final class AgentCapabilitySourceCatalogService {

	private HybridAgentCapabilitySelector $eligibilitySelector;

	public function __construct(?HybridAgentCapabilitySelector $eligibilitySelector = null) {
		$this->eligibilitySelector = $eligibilitySelector ?? new HybridAgentCapabilitySelector();
	}

	/**
	 * @param array<int,mixed> $tools
	 * @param array<int,string> $requiredToolNames
	 * @return array<string,array{
	 *     source_id:string,
	 *     label:string,
	 *     description:string,
	 *     capabilities:array<int,AgentCapability>
	 * }>
	 */
	public function buildSources(
		AgentCapabilityCatalog $catalog,
		array $tools,
		AgentCapabilitySelectionConfig $config,
		array $requiredToolNames = []
	): array {
		$eligible = $this->eligibleCapabilities($catalog, $config, $requiredToolNames);
		$eligibleByName = [];
		foreach ($eligible as $capability) {
			$eligibleByName[$capability->getName()] = $capability;
		}

		$sources = [];
		$mappedNames = [];

		foreach ($tools as $tool) {
			if (!$tool instanceof IAgentTool) {
				continue;
			}

			$capabilities = [];
			foreach ($tool->getToolDefinitions() as $definition) {
				if (!is_array($definition)) {
					continue;
				}
				$name = trim((string)($definition['function']['name'] ?? ''));
				if ($name === '' || !isset($eligibleByName[$name])) {
					continue;
				}
				$capabilities[$name] = $eligibleByName[$name];
				$mappedNames[$name] = true;
			}

			if ($capabilities === []) {
				continue;
			}

			$metadata = $this->sourceMetadata($tool, array_values($capabilities));
			$sourceId = $metadata['source_id'];
			if (!isset($sources[$sourceId])) {
				$sources[$sourceId] = [
					'source_id' => $sourceId,
					'label' => $metadata['label'],
					'description' => $metadata['description'],
					'capabilities' => []
				];
			}

			foreach ($capabilities as $name => $capability) {
				$sources[$sourceId]['capabilities'][$name] = $capability;
			}
		}

		// Direct compatibility tools should normally have been mapped above.
		// Preserve every remaining eligible capability instead of hiding it from
		// the main agent merely because its source has no configured metadata.
		foreach ($eligible as $capability) {
			if (isset($mappedNames[$capability->getName()])) {
				continue;
			}
			$sourceId = $this->fallbackCapabilitySourceId($capability);
			if (!isset($sources[$sourceId])) {
				$sources[$sourceId] = [
					'source_id' => $sourceId,
					'label' => $sourceId,
					'description' => trim($capability->getDescription()),
					'capabilities' => []
				];
			}
			$sources[$sourceId]['capabilities'][$capability->getName()] = $capability;
		}

		ksort($sources);
		foreach ($sources as &$source) {
			$source['capabilities'] = array_values($source['capabilities']);
		}
		unset($source);

		return $sources;
	}

	/**
	 * @param array<string,array{source_id:string,label:string,description:string,capabilities:array<int,AgentCapability>}> $sources
	 * @param array<int,string> $sourceIds
	 * @param array<int,string> $requiredToolNames
	 */
	public function selectSources(
		AgentCapabilityCatalog $catalog,
		array $sources,
		array $sourceIds,
		AgentCapabilitySelectionConfig $config,
		array $requiredToolNames,
		int $iteration
	): AgentCapabilitySelection {
		$sourceIds = $this->normalizeSourceIds($sourceIds);
		if (count($sourceIds) > $config->getMaxSources()) {
			throw new \RuntimeException(
				'Capability source selection requested ' . count($sourceIds)
				. ' sources but maxSources is ' . $config->getMaxSources() . '.'
			);
		}

		$selected = [];
		$selectedSourceByTool = [];
		foreach ($sourceIds as $sourceId) {
			if (!isset($sources[$sourceId])) {
				throw new \RuntimeException('Unknown capability source id: ' . $sourceId);
			}
			foreach ($sources[$sourceId]['capabilities'] as $capability) {
				$selected[$capability->getName()] = $capability;
				$selectedSourceByTool[$capability->getName()] = $sourceId;
			}
		}

		$eligible = [];
		foreach ($sources as $source) {
			foreach ($source['capabilities'] as $capability) {
				$eligible[$capability->getName()] = $capability;
			}
		}
		$mandatory = $this->mandatoryToolNames(array_values($eligible), $config, $requiredToolNames);
		foreach ($mandatory as $name) {
			$selected[$name] = $eligible[$name];
		}

		if (count($selected) > $config->getMaxTools()) {
			throw new \RuntimeException(
				'Capability source selection exposes ' . count($selected)
				. ' functions but maxTools is ' . $config->getMaxTools() . '.'
			);
		}

		$capabilities = array_values($selected);
		usort(
			$capabilities,
			static fn(AgentCapability $left, AgentCapability $right): int => strcmp($left->getName(), $right->getName())
		);
		$scores = [];
		$reasons = [];
		$mandatoryMap = array_fill_keys($mandatory, true);
		foreach ($capabilities as $capability) {
			$name = $capability->getName();
			$scores[$name] = isset($mandatoryMap[$name]) ? 1000.0 : 100.0;
			$reasons[$name] = isset($mandatoryMap[$name])
				? ['mandatory', 'agent-source-selection']
				: ['agent-source-selection', 'source:' . ($selectedSourceByTool[$name] ?? 'configured')];
		}

		return new AgentCapabilitySelection(
			iteration: $iteration,
			strategy: AgentCapabilitySelectionConfig::STRATEGY_ALL,
			catalogSize: count($catalog),
			eligibleSize: count($eligible),
			capabilities: $capabilities,
			scores: $scores,
			reasons: $reasons
		);
	}

	/**
	 * @param array<string,array{source_id:string,label:string,description:string,capabilities:array<int,AgentCapability>}> $sources
	 * @param array<int,string> $toolNames
	 * @return array<int,string>
	 */
	public function sourceIdsForToolNames(array $sources, array $toolNames): array {
		$toolMap = [];
		foreach ($toolNames as $toolName) {
			$toolName = trim((string)$toolName);
			if ($toolName !== '') {
				$toolMap[$toolName] = true;
			}
		}

		$result = [];
		foreach ($sources as $sourceId => $source) {
			foreach ($source['capabilities'] as $capability) {
				if (isset($toolMap[$capability->getName()])) {
					$result[] = $sourceId;
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * @param array<string,array{source_id:string,label:string,description:string,capabilities:array<int,AgentCapability>}> $sources
	 */
	public function renderCatalog(array $sources, int $maxCharacters): string {
		$maxCharacters = max(2000, $maxCharacters);
		foreach ([1200, 600, 240, 80, 0] as $descriptionLimit) {
			$rows = [];
			foreach ($sources as $source) {
				$row = [
					'source_id' => $source['source_id'],
					'label' => $source['label'],
					'function_count' => count($source['capabilities'])
				];
				$description = trim($source['description']);
				if ($descriptionLimit > 0 && $description !== '') {
					$row['description'] = $this->limitText($description, $descriptionLimit);
				}
				$rows[] = $row;
			}

			$json = $this->encode($rows);
			if (strlen($json) <= $maxCharacters) {
				return $json;
			}
		}

		return $this->encode([
			'source_ids' => array_values(array_keys($sources))
		]);
	}

	/**
	 * @param array<int,string> $sourceIds
	 * @return array<int,string>
	 */
	private function normalizeSourceIds(array $sourceIds): array {
		$result = [];
		foreach ($sourceIds as $sourceId) {
			$sourceId = is_scalar($sourceId) ? trim((string)$sourceId) : '';
			if ($sourceId !== '') {
				$result[$sourceId] = true;
			}
		}

		return array_keys($result);
	}

	/**
	 * @param array<int,string> $requiredToolNames
	 * @return array<int,AgentCapability>
	 */
	private function eligibleCapabilities(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionConfig $config,
		array $requiredToolNames
	): array {
		$data = $config->toArray();
		$data['enabled'] = false;
		$data['strategy'] = AgentCapabilitySelectionConfig::STRATEGY_ALL;
		$eligibilityConfig = AgentCapabilitySelectionConfig::fromArray($data);
		$selection = $this->eligibilitySelector->select(
			$catalog,
			new AgentCapabilitySelectionRequest(
				iteration: 0,
				contextText: '',
				config: $eligibilityConfig,
				previousSelectedToolNames: [],
				recentToolNames: [],
				requiredToolNames: $requiredToolNames,
				model: null,
				messages: []
			)
		);

		return $selection->getCapabilities();
	}

	/**
	 * @param array<int,AgentCapability> $eligible
	 * @param array<int,string> $requiredToolNames
	 * @return array<int,string>
	 */
	private function mandatoryToolNames(
		array $eligible,
		AgentCapabilitySelectionConfig $config,
		array $requiredToolNames
	): array {
		$eligibleMap = [];
		foreach ($eligible as $capability) {
			$eligibleMap[$capability->getName()] = $capability;
		}

		$required = [];
		foreach ($config->getAlwaysAvailable() as $name) {
			$name = trim((string)$name);
			if ($name !== '') {
				$required[$name] = true;
			}
		}
		foreach ($requiredToolNames as $name) {
			$name = trim((string)$name);
			if ($name !== '') {
				$required[$name] = true;
			}
		}
		foreach ($eligible as $capability) {
			if ($capability->isAlwaysAvailable()) {
				$required[$capability->getName()] = true;
			}
		}

		$missing = array_values(array_diff(array_keys($required), array_keys($eligibleMap)));
		if ($missing !== []) {
			sort($missing);
			throw new \RuntimeException('Mandatory capabilities are unavailable after agent filters: ' . implode(', ', $missing));
		}

		return array_keys($required);
	}

	/**
	 * @param array<int,AgentCapability> $capabilities
	 * @return array{source_id:string,label:string,description:string}
	 */
	private function sourceMetadata(IAgentTool $tool, array $capabilities): array {
		if ($tool instanceof IAgentCapabilitySourceMetadata) {
			$sourceId = trim($tool->getCapabilitySourceId());
			$label = trim($tool->getCapabilitySourceLabel());
			$description = trim($tool->getCapabilitySourceDescription());
		}
		else {
			$sourceId = $tool::getName();
			$label = $sourceId;
			$description = $this->capabilityDescription($capabilities);
		}

		if ($sourceId === '') {
			$sourceId = $tool::getName();
		}
		if ($label === '') {
			$label = $sourceId;
		}
		if ($description === '') {
			$description = $this->capabilityDescription($capabilities);
		}

		return [
			'source_id' => $sourceId,
			'label' => $label,
			'description' => $description
		];
	}

	/** @param array<int,AgentCapability> $capabilities */
	private function capabilityDescription(array $capabilities): string {
		$descriptions = [];
		foreach ($capabilities as $capability) {
			$description = trim($capability->getDescription());
			if ($description !== '') {
				$descriptions[$description] = true;
			}
			if (count($descriptions) >= 3) {
				break;
			}
		}

		return implode(' ', array_keys($descriptions));
	}

	private function fallbackCapabilitySourceId(AgentCapability $capability): string {
		$sourceId = trim($capability->getSourceId());
		if ($sourceId !== '') {
			return $sourceId;
		}
		$sourceName = trim($capability->getSourceName());
		if ($sourceName !== '') {
			return $sourceName;
		}
		return 'tool:' . $capability->getName();
	}

	private function limitText(string $text, int $maxCharacters): string {
		if (strlen($text) <= $maxCharacters) {
			return $text;
		}
		if ($maxCharacters <= 3) {
			return substr($text, 0, $maxCharacters);
		}
		return substr($text, 0, $maxCharacters - 3) . '...';
	}

	private function encode(mixed $value): string {
		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($json)) {
			throw new \RuntimeException('Capability source catalog could not be encoded.');
		}
		return $json;
	}
}

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

namespace MissionBay\Capability;

use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use MissionBay\Api\IAgentTool;

/**
 * Builds one normalized run-local catalog from the tools already assigned to
 * the agent and the definitions allowed by the active profile.
 */
final class AgentCapabilityCatalogBuilder {

	/**
	 * @param array<int,IAgentTool> $tools
	 * @param array<int,array<string,mixed>> $toolDefinitions
	 */
	public function build(array $tools, array $toolDefinitions): AgentCapabilityCatalog {
		$activeNames = [];
		foreach ($toolDefinitions as $definition) {
			if (is_array($definition)) {
				$name = trim((string)($definition['function']['name'] ?? ''));
				if ($name !== '') {
					$activeNames[$name] = true;
				}
			}
		}
		$sources = $this->indexSources($tools, $activeNames);
		$capabilities = [];
		$known = [];

		foreach ($toolDefinitions as $definition) {
			if (!is_array($definition)) {
				continue;
			}
			$definition = $this->withBatchDescription($definition);

			$name = trim((string)($definition['function']['name'] ?? ''));
			if ($name === '') {
				throw new \RuntimeException('Agent tool definitions require a non-empty function name.');
			}
			if (isset($known[$name])) {
				throw new \RuntimeException('Duplicate agent tool name in capability catalog: ' . $name);
			}
			$known[$name] = true;

			$source = $sources[$name] ?? ['id' => '', 'name' => ''];
			$function = is_array($definition['function'] ?? null) ? $definition['function'] : [];
			$title = trim((string)($definition['label'] ?? $function['title'] ?? $name));
			$description = trim((string)($function['description'] ?? $definition['description'] ?? ''));
			$category = trim((string)($definition['category'] ?? ''));
			$tags = $this->normalizeStrings($definition['tags'] ?? []);
			$priority = is_numeric($definition['priority'] ?? null)
				? (int)$definition['priority']
				: 0;
			$alwaysAvailable = $this->toBool(
				$definition['alwaysAvailable'] ?? $definition['always_available'] ?? false
			);

			$capabilities[] = new AgentCapability(
				name: $name,
				title: $title !== '' ? $title : $name,
				description: $description,
				category: $category,
				tags: $tags,
				priority: $priority,
				definition: $definition,
				sourceId: $source['id'],
				sourceName: $source['name'],
				alwaysAvailable: $alwaysAvailable,
				metadata: $this->collectMetadata($definition)
			);
		}

		return new AgentCapabilityCatalog($capabilities);
	}

	/**
	 * @param array<int,IAgentTool> $tools
	 * @param array<string,bool> $activeNames
	 * @return array<string,array{id:string,name:string}>
	 */
	private function indexSources(array $tools, array $activeNames): array {
		$result = [];

		foreach ($tools as $tool) {
			if (!$tool instanceof IAgentTool) {
				continue;
			}

			$sourceId = method_exists($tool, 'getId')
				? trim((string)$tool->getId())
				: '';
			$sourceName = $tool::getName();

			foreach ($tool->getToolDefinitions() as $definition) {
				if (!is_array($definition)) {
					continue;
				}
				$name = trim((string)($definition['function']['name'] ?? ''));
				if ($name === '' || !isset($activeNames[$name])) {
					continue;
				}
				if (isset($result[$name])) {
					$first = $result[$name]['id'] !== '' ? $result[$name]['id'] : $result[$name]['name'];
					$second = $sourceId !== '' ? $sourceId : $sourceName;
					throw new \RuntimeException(
						'Duplicate agent tool name "' . $name . '" exposed by ' . $first . ' and ' . $second . '.'
					);
				}
				$result[$name] = [
					'id' => $sourceId,
					'name' => $sourceName
				];
			}
		}

		return $result;
	}

	/** @param array<string,mixed> $definition @return array<string,mixed> */
	private function withBatchDescription(array $definition): array {
		if (
			($definition['batchable'] ?? false) !== true
			|| ($definition['batchIndependent'] ?? false) !== true
			|| !is_array($definition['function'] ?? null)
		) {
			return $definition;
		}

		$maxBatchSize = is_numeric($definition['maxBatchSize'] ?? null)
			? max(1, (int)$definition['maxBatchSize'])
			: 25;
		$description = trim((string)($definition['function']['description'] ?? ''));
		$hint = 'Batch-enabled: execute_agent_tool_batch may repeat this unchanged single-item operation for up to '
			. $maxBatchSize . ' independent argument sets with one approval.';
		$definition['function']['description'] = $description !== ''
			? $description . ' ' . $hint
			: $hint;

		return $definition;
	}

	/** @return array<int,string> */
	private function normalizeStrings(mixed $values): array {
		if (!is_array($values)) {
			return [];
		}
		$result = [];
		foreach ($values as $value) {
			if (!is_scalar($value)) {
				continue;
			}
			$value = strtolower(trim((string)$value));
			if ($value !== '') {
				$result[$value] = true;
			}
		}
		return array_keys($result);
	}

	/** @return array<string,mixed> */
	private function collectMetadata(array $definition): array {
		$metadata = [];
		foreach ([
			'mutation',
			'requiresApproval',
			'requires_approval',
			'destructiveHint',
			'destructive_hint',
			'sideEffectHint',
			'side_effect_hint',
			'readOnlyHint',
			'read_only_hint',
			'batchable',
			'batchIndependent',
			'maxBatchSize'
		] as $key) {
			if (array_key_exists($key, $definition)) {
				$metadata[$key] = $definition[$key];
			}
		}
		return $metadata;
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_numeric($value)) {
			return (int)$value !== 0;
		}
		return in_array(strtolower(trim((string)$value)), ['true', 'yes', 'on'], true);
	}
}

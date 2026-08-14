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

namespace MissionBay\Service;

use MissionBay\Api\IAgentComponentFlowBuilder;
use MissionBay\Api\IAgentComponentPresetFlowExpander;
use MissionBay\Api\IAgentComponentPresetRepository;

/**
 * AgentComponentFlowBuilder
 *
 * Builds an effective AgentFlow from configured chat-model, tool,
 * conversation-memory and context-contributor component presets.
 */
class AgentComponentFlowBuilder implements IAgentComponentFlowBuilder {

	private const ASSISTANT_NODE_TYPE = 'aiassistantnode';
	private const TOOL_WRAPPER_TYPE = 'configuredagenttoolresource';
	private const MEMORY_WRAPPER_TYPE = 'configuredagentmemoryresource';

	/**
	 * @var array<int,string>
	 */
	private array $warnings = [];

	public function __construct(
		private readonly IAgentComponentPresetRepository $presetRepository,
		private readonly IAgentComponentPresetFlowExpander $presetFlowExpander
	) {}

	public static function getName(): string {
		return 'agentcomponentflowbuilder';
	}

	public function build(array $baseFlow, array $components, string $assistantNodeId = 'assistant'): array {
		$this->warnings = [];

		$flow = $this->normalizeFlow($baseFlow);
		$assistantIndex = $this->findAssistantNodeIndex($flow, $assistantNodeId);

		if ($assistantIndex === null) {
			$this->warnings[] = 'Assistant node not found: ' . $assistantNodeId;
			return $flow;
		}

		foreach ($components as $index => $component) {
			if (!is_array($component)) {
				$this->warnings[] = 'Component entry is not an array at index ' . $index . '.';
				continue;
			}

			$this->applyComponent($flow, $assistantIndex, $component, $index);
		}

		return $flow;
	}

	public function getWarnings(): array {
		return $this->warnings;
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $component
	 */
	private function applyComponent(array &$flow, int $assistantIndex, array $component, int $index): void {
		if (!$this->isEnabled($component)) {
			return;
		}

		$presetId = trim((string)($component['preset'] ?? ''));

		if ($presetId === '') {
			$this->warnings[] = 'Component has no preset at index ' . $index . '.';
			return;
		}

		$preset = $this->presetRepository->getPreset($presetId, []);

		if ($preset === []) {
			$this->warnings[] = 'Component preset not found: ' . $presetId;
			return;
		}

		if (!$this->isEnabled($preset)) {
			$this->warnings[] = 'Component preset is disabled: ' . $presetId;
			return;
		}

		$baseResourceId = $this->ensurePresetResource($flow, $presetId);
		$attachAs = $this->normalizeStringList($component['attach_as'] ?? ($preset['capabilities'] ?? []));

		if (in_array('chatmodel', $attachAs, true)) {
			$this->addChatModel($flow, $assistantIndex, $baseResourceId);
		}

		if (in_array('tool', $attachAs, true)) {
			$this->addConfiguredTool($flow, $assistantIndex, $presetId, $baseResourceId, $component, $index);
		}

		if (in_array('memory', $attachAs, true)) {
			$this->addConfiguredMemory($flow, $assistantIndex, $presetId, $baseResourceId, $component, $index);
		}

		if (in_array('context', $attachAs, true)) {
			$this->addContextContributor($flow, $assistantIndex, $baseResourceId);
		}
	}

	/**
	 * @param array<string,mixed> $flow
	 */
	private function ensurePresetResource(array &$flow, string $presetId): string {
		$result = $this->presetFlowExpander->expand($flow, [$presetId]);
		$flow = is_array($result['flow'] ?? null) ? $result['flow'] : $flow;

		foreach((array)($result['warnings'] ?? []) as $warning) {
			$warning = trim((string)$warning);
			if($warning !== '') {
				$this->warnings[] = $warning;
			}
		}

		$resourceIds = is_array($result['resource_ids'] ?? null) ? $result['resource_ids'] : [];
		$resourceId = trim((string)($resourceIds[$presetId] ?? ''));

		if($resourceId === '') {
			$this->warnings[] = 'Component preset could not be expanded: ' . $presetId;
			return $this->sanitizeId('preset_' . $presetId);
		}

		return $resourceId;
	}

	/**
	 * @param array<string,mixed> $flow
	 */
	private function addChatModel(array &$flow, int $assistantIndex, string $baseResourceId): void {
		$this->addNodeDockResource($flow, $assistantIndex, 'chatmodel', $baseResourceId);
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $component
	 */
	private function addConfiguredTool(array &$flow, int $assistantIndex, string $presetId, string $baseResourceId, array $component, int $index): void {
		$wrapperId = $this->buildUniqueResourceId($flow, 'configured_tool_' . $this->sanitizeId($presetId) . '_' . $index);
		$config = $this->normalizeConfig($component['tool_config'] ?? []);

		if (!isset($config['namespace'])) {
			$config['namespace'] = isset($component['tool_namespace'])
				? $component['tool_namespace']
				: $this->sanitizeId($presetId);
		}

		if (isset($component['enabled']) && !isset($config['enabled'])) {
			$config['enabled'] = $component['enabled'];
		}

		$resource = [
			'id' => $wrapperId,
			'type' => self::TOOL_WRAPPER_TYPE,
			'docks' => [
				'tool' => [
					$baseResourceId
				]
			]
		];

		if ($config !== []) {
			$resource['config'] = $config;
		}

		$flow['resources'][] = $resource;
		$this->addNodeDockResource($flow, $assistantIndex, 'tools', $wrapperId);
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $component
	 */
	private function addConfiguredMemory(array &$flow, int $assistantIndex, string $presetId, string $baseResourceId, array $component, int $index): void {
		$wrapperId = $this->buildUniqueResourceId($flow, 'configured_memory_' . $this->sanitizeId($presetId) . '_' . $index);
		$config = $this->normalizeConfig($component['memory_config'] ?? []);

		if (isset($component['order']) && !isset($config['priority'])) {
			$config['priority'] = $component['order'];
		}

		if (isset($component['enabled']) && !isset($config['enabled'])) {
			$config['enabled'] = $component['enabled'];
		}

		$resource = [
			'id' => $wrapperId,
			'type' => self::MEMORY_WRAPPER_TYPE,
			'docks' => [
				'memory' => [
					$baseResourceId
				]
			]
		];

		if ($config !== []) {
			$resource['config'] = $config;
		}

		$flow['resources'][] = $resource;
		$this->addNodeDockResource($flow, $assistantIndex, 'memory', $wrapperId);
	}

	/**
	 * Context profiles use the configured base resource directly. No memory
	 * adapter, role switch or read/write configuration is involved.
	 *
	 * @param array<string,mixed> $flow
	 */
	private function addContextContributor(array &$flow, int $assistantIndex, string $baseResourceId): void {
		$this->addNodeDockResource($flow, $assistantIndex, 'contextcontributors', $baseResourceId);
	}

	/**
	 * @param array<string,mixed> $flow
	 */
	private function addNodeDockResource(array &$flow, int $nodeIndex, string $dockName, string $resourceId): void {
		if (!isset($flow['nodes'][$nodeIndex]['docks']) || !is_array($flow['nodes'][$nodeIndex]['docks'])) {
			$flow['nodes'][$nodeIndex]['docks'] = [];
		}

		if (!isset($flow['nodes'][$nodeIndex]['docks'][$dockName]) || !is_array($flow['nodes'][$nodeIndex]['docks'][$dockName])) {
			$flow['nodes'][$nodeIndex]['docks'][$dockName] = [];
		}

		if (!in_array($resourceId, $flow['nodes'][$nodeIndex]['docks'][$dockName], true)) {
			$flow['nodes'][$nodeIndex]['docks'][$dockName][] = $resourceId;
		}
	}

	/**
	 * @param array<string,mixed> $flow
	 */
	private function findAssistantNodeIndex(array $flow, string $assistantNodeId): ?int {
		foreach ($flow['nodes'] as $index => $node) {
			if (!is_array($node)) {
				continue;
			}

			if ((string)($node['id'] ?? '') === $assistantNodeId) {
				return (int)$index;
			}
		}

		foreach ($flow['nodes'] as $index => $node) {
			if (!is_array($node)) {
				continue;
			}

			if ((string)($node['type'] ?? '') === self::ASSISTANT_NODE_TYPE) {
				return (int)$index;
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $flow
	 */
	private function resourceExists(array $flow, string $resourceId): bool {
		foreach ($flow['resources'] as $resource) {
			if (is_array($resource) && (string)($resource['id'] ?? '') === $resourceId) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string,mixed> $flow
	 */
	private function buildUniqueResourceId(array $flow, string $baseId): string {
		$baseId = $this->sanitizeId($baseId);
		$resourceId = $baseId;
		$counter = 2;

		while ($this->resourceExists($flow, $resourceId)) {
			$resourceId = $baseId . '_' . $counter;
			$counter++;
		}

		return $resourceId;
	}

	private function sanitizeId(string $id): string {
		$id = trim($id);
		$id = (string)preg_replace('/[^A-Za-z0-9_]+/', '_', $id);
		$id = trim($id, '_');

		if ($id === '') {
			return 'component';
		}

		if (preg_match('/^[0-9]/', $id)) {
			$id = 'component_' . $id;
		}

		return strtolower($id);
	}

	/**
	 * @param array<string,mixed> $flow
	 * @return array<string,mixed>
	 */
	private function normalizeFlow(array $flow): array {
		if (!isset($flow['nodes']) || !is_array($flow['nodes'])) {
			$flow['nodes'] = [];
		}

		if (!isset($flow['resources']) || !is_array($flow['resources'])) {
			$flow['resources'] = [];
		}

		if (!isset($flow['connections']) || !is_array($flow['connections'])) {
			$flow['connections'] = [];
		}

		return $flow;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private function normalizeConfig(mixed $config): array {
		return is_array($config) ? $config : [];
	}

	/**
	 * @return array<int,string>
	 */
	private function normalizeStringList(mixed $value): array {
		if ($value === null || $value === '') {
			return [];
		}

		if (is_string($value)) {
			$value = explode(',', $value);
		}

		if (!is_array($value)) {
			return [];
		}

		$result = [];

		foreach ($value as $item) {
			$item = strtolower(trim((string)$item));

			if ($item === '') {
				continue;
			}

			$result[] = $item;
		}

		return array_values(array_unique($result));
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function isEnabled(array $data): bool {
		if (!array_key_exists('enabled', $data)) {
			return true;
		}

		$value = $data['enabled'];

		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));

		return !in_array($value, ['0', 'false', 'no', 'off'], true);
	}
}

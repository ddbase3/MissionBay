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
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentExecutionService;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Dto\AgentExecutionResult;

/**
 * AgentExecutionService
 *
 * Shared MissionBay agent runtime used by chatbot endpoints, REST calls and
 * scheduled jobs.
 */
class AgentExecutionService implements IAgentExecutionService {

	private const CHAT_LLM_RESOURCE_ID = 'chatllm';
	private const CHAT_LLM_RESOURCE_TYPE = 'configuredchatmodelagentresource';
	private const DEFAULT_ASSISTANT_NODE_ID = 'assistant';

	/**
	 * @var array<int,string>
	 */
	private array $warnings = [];

	public function __construct(
		private readonly IAgentContextFactory $contextFactory,
		private readonly IAgentFlowFactory $flowFactory,
		private readonly IAgentComponentFlowBuilder $componentFlowBuilder
	) {}

	public static function getName(): string {
		return 'agentexecutionservice';
	}

	/**
	 * @param array<string,mixed> $agentSettings
	 * @return array<string,mixed>
	 */
	public function buildEffectiveFlow(array $agentSettings): array {
		$this->warnings = [];

		$flow = $this->normalizeAgentFlow($agentSettings['agent_flow'] ?? []);

		if ($flow === []) {
			throw new \RuntimeException('Invalid Flow JSON');
		}

		$llm = $this->normalizeTechnicalKey((string)($agentSettings['llm'] ?? ''));

		if ($llm !== '') {
			$flow = $this->applyLlmToAgentFlow($flow, $llm);
		}

		$components = $this->normalizeAgentComponents($agentSettings['agent_components'] ?? []);

		if ($components === []) {
			return $flow;
		}

		$assistantNodeId = $this->normalizeAssistantNodeId($agentSettings['agent_components_assistant_node'] ?? self::DEFAULT_ASSISTANT_NODE_ID);
		$flow = $this->componentFlowBuilder->build($flow, $components, $assistantNodeId);
		$this->warnings = $this->componentFlowBuilder->getWarnings();

		return $flow;
	}

	/**
	 * @param array<string,mixed> $agentSettings
	 * @param array<string,mixed> $inputs
	 * @param array<string,mixed> $contextVars
	 */
	public function run(array $agentSettings, array $inputs = [], array $contextVars = []): AgentExecutionResult {
		$effectiveFlow = $this->buildEffectiveFlow($agentSettings);
		$flow = $this->createFlow($effectiveFlow, $contextVars);
		$output = $flow->run($inputs);

		return new AgentExecutionResult($output, $effectiveFlow, $this->warnings);
	}

	/**
	 * @param array<string,mixed> $agentSettings
	 * @param array<string,mixed> $inputs
	 * @param array<string,mixed> $contextVars
	 */
	public function stream(array $agentSettings, array $inputs = [], array $contextVars = []): void {
		$effectiveFlow = $this->buildEffectiveFlow($agentSettings);
		$flow = $this->createFlow($effectiveFlow, $contextVars);
		$flow->run($inputs);
	}

	/**
	 * @return array<int,string>
	 */
	public function getWarnings(): array {
		return $this->warnings;
	}

	/**
	 * @param array<string,mixed> $effectiveFlow
	 * @param array<string,mixed> $contextVars
	 */
	private function createFlow(array $effectiveFlow, array $contextVars) {
		$context = $this->contextFactory->createContext();

		foreach ($contextVars as $key => $value) {
			if (!is_string($key) && !is_int($key)) {
				continue;
			}

			$key = trim((string)$key);

			if ($key === '') {
				continue;
			}

			$context->setVar($key, $value);
		}

		return $this->flowFactory->createFromArray('strictflow', $effectiveFlow, $context);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function normalizeAgentFlow(mixed $value): array {
		if (is_array($value)) {
			return $value;
		}

		if (!is_string($value)) {
			return [];
		}

		$value = trim($value);

		if ($value === '') {
			return [];
		}

		$decoded = json_decode($value, true);

		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function normalizeAgentComponents(mixed $value): array {
		if (!is_array($value)) {
			return [];
		}

		$result = [];

		foreach ($value as $id => $component) {
			if (!is_array($component)) {
				continue;
			}

			if (!isset($component['preset']) && is_string($id)) {
				$component['preset'] = $id;
			}

			$result[] = $component;
		}

		return $result;
	}

	private function normalizeAssistantNodeId(mixed $value): string {
		$nodeId = trim((string)$value);

		return $nodeId !== '' ? $nodeId : self::DEFAULT_ASSISTANT_NODE_ID;
	}

	/**
	 * @param array<string,mixed> $agentFlow
	 * @return array<string,mixed>
	 */
	private function applyLlmToAgentFlow(array $agentFlow, string $llm): array {
		if ($llm === '') {
			return $agentFlow;
		}

		if (!isset($agentFlow['resources']) || !is_array($agentFlow['resources'])) {
			$agentFlow['resources'] = [];
		}

		$resources = $agentFlow['resources'];
		$resourceIndex = $this->findChatLlmResourceIndex($resources);
		$resource = [
			'id' => self::CHAT_LLM_RESOURCE_ID,
			'type' => self::CHAT_LLM_RESOURCE_TYPE,
			'config' => [
				'service' => [
					'mode' => 'fixed',
					'value' => $llm
				]
			]
		];

		if ($resourceIndex !== null && isset($resources[$resourceIndex]) && is_array($resources[$resourceIndex])) {
			$resource = array_merge($resources[$resourceIndex], $resource);
			$resource['config'] = is_array($resources[$resourceIndex]['config'] ?? null)
				? $resources[$resourceIndex]['config']
				: [];
			$resource['config']['service'] = [
				'mode' => 'fixed',
				'value' => $llm
			];
			$resource['type'] = self::CHAT_LLM_RESOURCE_TYPE;
		}

		if ($resourceIndex === null) {
			$resources[] = $resource;
		}
		else {
			$resources[$resourceIndex] = $resource;
		}

		$agentFlow['resources'] = array_values($resources);

		return $agentFlow;
	}

	private function findChatLlmResourceIndex(array $resources): ?int {
		$fallback = null;

		foreach ($resources as $index => $resource) {
			if (!is_array($resource)) {
				continue;
			}

			if ((string)($resource['id'] ?? '') === self::CHAT_LLM_RESOURCE_ID) {
				return (int)$index;
			}

			if ($fallback === null && (string)($resource['type'] ?? '') === self::CHAT_LLM_RESOURCE_TYPE) {
				$fallback = (int)$index;
			}
		}

		return $fallback;
	}

	private function normalizeTechnicalKey(string $value): string {
		$value = strtolower(trim($value));

		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

}

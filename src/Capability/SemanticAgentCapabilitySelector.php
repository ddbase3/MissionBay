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

use AssistantFoundation\Api\IAgentCapabilitySelector;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelection;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use AssistantFoundation\Dto\AiResultMetadata;

/**
 * Uses the active agent model to rerank a deterministic bounded candidate set.
 * Invalid output, unavailable models, and provider failures fall back to the
 * existing deterministic hybrid selector.
 */
final class SemanticAgentCapabilitySelector implements IAgentCapabilitySelector {

	public function __construct(
		private readonly HybridAgentCapabilitySelector $hybridSelector
	) {}

	public function select(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionRequest $request
	): AgentCapabilitySelection {
		$config = $request->getConfig();
		$fallback = $this->hybridSelector->select(
			$catalog,
			$this->withConfig($request, $this->hybridConfig($config, $config->getMaxTools(), $config->getSelectAllThreshold()))
		);

		if (
			!$config->isEnabled()
			|| $fallback->getEligibleSize() <= min($config->getSelectAllThreshold(), $config->getMaxTools())
		) {
			return $this->rewrap($fallback, 'semantic-small-pool');
		}

		$model = $request->getModel();
		if ($model === null) {
			return $this->rewrap($fallback, 'semantic-model-unavailable');
		}

		$candidateLimit = max($config->getMaxTools(), $config->getSemanticCandidateTools());
		$candidates = $this->hybridSelector->select(
			$catalog,
			$this->withConfig(
				$request,
				$this->hybridConfig($config, $candidateLimit, 0),
				false
			)
		);

		try {
			$result = $model->complete($this->buildMessages($request, $candidates), []);
			$selectedNames = $this->parseSelectedToolNames($result->getContent());
			$selection = $this->buildSelection(
				$catalog,
				$request,
				$candidates,
				$selectedNames,
				$result->getMetadata()
			);

			if ($selection !== null) {
				return $selection;
			}

			return $this->rewrap($fallback, 'semantic-invalid-output', $result->getMetadata());
		}
		catch (\Throwable) {
			return $this->rewrap($fallback, 'semantic-provider-fallback');
		}
	}

	private function hybridConfig(
		AgentCapabilitySelectionConfig $config,
		int $maxTools,
		int $selectAllThreshold
	): AgentCapabilitySelectionConfig {
		$data = $config->toArray();
		$data['strategy'] = AgentCapabilitySelectionConfig::STRATEGY_HYBRID;
		$data['max_tools'] = $maxTools;
		$data['select_all_threshold'] = $selectAllThreshold;

		return AgentCapabilitySelectionConfig::fromArray($data);
	}

	private function withConfig(
		AgentCapabilitySelectionRequest $request,
		AgentCapabilitySelectionConfig $config,
		bool $preserveStability = true
	): AgentCapabilitySelectionRequest {
		return new AgentCapabilitySelectionRequest(
			iteration: $request->getIteration(),
			contextText: $request->getContextText(),
			config: $config,
			previousSelectedToolNames: $preserveStability ? $request->getPreviousSelectedToolNames() : [],
			recentToolNames: $preserveStability ? $request->getRecentToolNames() : [],
			requiredToolNames: $request->getRequiredToolNames(),
			model: $request->getModel()
		);
	}

	/** @return array<int,array<string,mixed>> */
	private function buildMessages(
		AgentCapabilitySelectionRequest $request,
		AgentCapabilitySelection $candidates
	): array {
		$config = $request->getConfig();
		$payload = [];

		foreach ($candidates->getCapabilities() as $capability) {
			$payload[] = $this->capabilitySummary($capability);
		}

		$candidateJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($candidateJson)) {
			throw new \RuntimeException('Semantic capability candidates could not be encoded.');
		}

		$contextText = trim($request->getContextText());
		$maxCharacters = $config->getSemanticMaxPromptCharacters();
		$fixedCharacters = strlen($candidateJson) + 3000;
		$availableContextCharacters = max(1000, $maxCharacters - $fixedCharacters);
		if (strlen($contextText) > $availableContextCharacters) {
			$contextText = substr($contextText, -$availableContextCharacters);
		}

		return [
			[
				'role' => 'system',
				'content' => implode("\n", [
					'You are a capability router for an AI agent.',
					'Select only callable tool function names from the supplied candidate list.',
					'Choose the smallest sufficient set for the current user request and likely immediate follow-up steps.',
					'Distinguish resources by source, category, title and description. Do not confuse plugins with cron jobs or similarly named domains.',
					'Return JSON only in this exact shape: {"selected_tools":["tool_name"]}.',
					'Do not explain the choice and do not invent tool names.'
				])
			],
			[
				'role' => 'user',
				'content' => "Current conversation context:\n" . $contextText
					. "\n\nMaximum selected tools: " . $config->getMaxTools()
					. "\n\nCandidate capabilities:\n" . $candidateJson
			]
		];
	}

	/** @return array<string,mixed> */
	private function capabilitySummary(AgentCapability $capability): array {
		$description = trim($capability->getDescription());
		if (strlen($description) > 600) {
			$description = substr($description, 0, 600);
		}

		return [
			'name' => $capability->getName(),
			'title' => $capability->getTitle(),
			'description' => $description,
			'category' => $capability->getCategory(),
			'tags' => $capability->getTags(),
			'source_id' => $capability->getSourceId(),
			'source_name' => $capability->getSourceName(),
			'parameter_names' => array_keys((array)($capability->getDefinition()['function']['parameters']['properties'] ?? []))
		];
	}

	/** @return array<int,string> */
	private function parseSelectedToolNames(string $content): array {
		$content = trim($content);
		if ($content === '') {
			return [];
		}

		$content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content) ?? $content;
		$start = strpos($content, '{');
		$end = strrpos($content, '}');
		if ($start !== false && $end !== false && $end >= $start) {
			$content = substr($content, $start, $end - $start + 1);
		}

		$decoded = json_decode($content, true);
		if (!is_array($decoded) || !is_array($decoded['selected_tools'] ?? null)) {
			return [];
		}

		$result = [];
		foreach ($decoded['selected_tools'] as $name) {
			if (!is_scalar($name)) {
				continue;
			}
			$name = trim((string)$name);
			if ($name !== '') {
				$result[$name] = true;
			}
		}

		return array_keys($result);
	}

	private function buildSelection(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionRequest $request,
		AgentCapabilitySelection $candidates,
		array $selectedNames,
		AiResultMetadata $metadata
	): ?AgentCapabilitySelection {
		$candidateMap = [];
		foreach ($candidates->getCapabilities() as $capability) {
			$candidateMap[$capability->getName()] = $capability;
		}

		$required = $this->requiredNames($candidates->getCapabilities(), $request);
		$orderedNames = [];
		foreach ($required as $name) {
			if (isset($candidateMap[$name])) {
				$orderedNames[$name] = true;
			}
		}
		foreach ($selectedNames as $name) {
			if (isset($candidateMap[$name])) {
				$orderedNames[$name] = true;
			}
		}

		if ($orderedNames === []) {
			return null;
		}

		$capabilities = [];
		$scores = [];
		$reasons = [];
		$position = 0;
		foreach (array_keys($orderedNames) as $name) {
			if (count($capabilities) >= $request->getConfig()->getMaxTools()) {
				break;
			}
			$capabilities[] = $candidateMap[$name];
			$isRequired = in_array($name, $required, true);
			$scores[$name] = $isRequired ? 1000.0 : max(1.0, 100.0 - $position);
			$reasons[$name] = $isRequired
				? ['mandatory', 'semantic-ai']
				: ['semantic-ai'];
			$position++;
		}

		return new AgentCapabilitySelection(
			iteration: $request->getIteration(),
			strategy: AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC,
			catalogSize: count($catalog),
			eligibleSize: $candidates->getEligibleSize(),
			capabilities: $capabilities,
			scores: $scores,
			reasons: $reasons,
			modelMetadata: $metadata
		);
	}

	/**
	 * @param array<int,AgentCapability> $capabilities
	 * @return array<int,string>
	 */
	private function requiredNames(array $capabilities, AgentCapabilitySelectionRequest $request): array {
		$required = [];
		foreach ($request->getConfig()->getAlwaysAvailable() as $name) {
			$required[$name] = true;
		}
		foreach ($request->getRequiredToolNames() as $name) {
			$required[$name] = true;
		}
		foreach ($capabilities as $capability) {
			if ($capability->isAlwaysAvailable()) {
				$required[$capability->getName()] = true;
			}
		}

		return array_keys($required);
	}

	private function rewrap(
		AgentCapabilitySelection $selection,
		string $reason,
		?AiResultMetadata $metadata = null
	): AgentCapabilitySelection {
		$reasons = $selection->getReasons();
		foreach ($selection->getToolNames() as $name) {
			$reasons[$name] = array_values(array_unique(array_merge($reasons[$name] ?? [], [$reason])));
		}

		return new AgentCapabilitySelection(
			iteration: $selection->getIteration(),
			strategy: AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC,
			catalogSize: $selection->getCatalogSize(),
			eligibleSize: $selection->getEligibleSize(),
			capabilities: $selection->getCapabilities(),
			scores: $selection->getScores(),
			reasons: $reasons,
			modelMetadata: $metadata
		);
	}
}

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
		if ($config->selectsSources()) {
			$fallback = $this->expandSelectedSources($catalog, $request, $fallback, 'semantic-source-fallback');
		}

		if (!$config->isEnabled() || $this->isSmallPool($fallback, $config)) {
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
			$result = $model->complete($this->buildMessages($catalog, $request, $candidates), []);
			$selectedNames = $this->parseSelectedNames($result->getContent(), $config->selectsSources());
			if ($selectedNames === null) {
				return $this->rewrap($fallback, 'semantic-invalid-output', $result->getMetadata());
			}

			$selection = $config->selectsSources()
				? $this->buildSourceSelection($catalog, $request, $candidates, $selectedNames, $result->getMetadata())
				: $this->buildFunctionSelection($catalog, $request, $candidates, $selectedNames, $result->getMetadata());

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
			model: $request->getModel(),
			messages: $request->getMessages()
		);
	}

	private function isSmallPool(
		AgentCapabilitySelection $selection,
		AgentCapabilitySelectionConfig $config
	): bool {
		if ($config->getSelectAllThreshold() <= 0) {
			return false;
		}

		if (!$config->selectsSources()) {
			return $selection->getEligibleSize() <= min(
				$config->getSelectAllThreshold(),
				$config->getMaxTools()
			);
		}

		if ($selection->getEligibleSize() > $config->getMaxTools()) {
			return false;
		}

		$sources = [];
		foreach ($selection->getCapabilities() as $capability) {
			$sources[$this->sourceKey($capability)] = true;
		}

		return count($sources) <= min(
			$config->getSelectAllThreshold(),
			$config->getMaxSources()
		);
	}

	/** @return array<int,array<string,mixed>> */
	private function buildMessages(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionRequest $request,
		AgentCapabilitySelection $candidates
	): array {
		return $request->getConfig()->selectsSources()
			? $this->buildSourceMessages($catalog, $request, $candidates)
			: $this->buildFunctionMessages($request, $candidates);
	}

	/** @return array<int,array<string,mixed>> */
	private function buildFunctionMessages(
		AgentCapabilitySelectionRequest $request,
		AgentCapabilitySelection $candidates
	): array {
		$config = $request->getConfig();
		$payload = [];

		foreach ($candidates->getCapabilities() as $capability) {
			$payload[] = $this->capabilitySummary($capability);
		}

		$candidateJson = $this->encodePayload($payload);
		$contextText = trim($request->getContextText());
		$maxCharacters = $config->getSemanticMaxPromptCharacters();
		$fixedCharacters = strlen($candidateJson) + 3000;
		$availableContextCharacters = max(1000, $maxCharacters - $fixedCharacters);
		$contextText = $this->limitText($contextText, $availableContextCharacters);

		return [
			[
				'role' => 'system',
				'content' => implode("\n", [
					'You are a capability router for an AI agent.',
					'Select only callable tool function names from the supplied candidate list.',
					'Choose the smallest dependency-complete set for the current user request and its immediate tool steps.',
					'Cover every independent action in the current user turn.',
					'When an action needs a required argument that is not available in the current context, include an available discovery or lookup capability that can resolve it.',
					'Distinguish resources by source, category, title and description. Do not confuse similarly named domains.',
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

	/** @return array<int,array<string,mixed>> */
	private function buildSourceMessages(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionRequest $request,
		AgentCapabilitySelection $candidates
	): array {
		$config = $request->getConfig();
		$payload = $this->sourcePayload($catalog, $candidates);
		$candidateJson = $this->encodePayload($payload);
		$maxCharacters = $config->getSemanticMaxPromptCharacters();
		$fixedCharacters = strlen($candidateJson) + 3000;
		$availableContextCharacters = max(1000, min(6000, $maxCharacters - $fixedCharacters));
		$contextText = $this->buildSourceContext($request->getMessages(), $request->getContextText());
		$contextText = $this->limitText($contextText, $availableContextCharacters);

		return [
			[
				'role' => 'system',
				'content' => implode("\n", [
					'You are a capability-source router for an AI agent.',
					'Select complete registered tool sources, not individual functions.',
					'Each selected source exposes all functions listed for that source to the next model decision.',
					'Choose the smallest source-complete set that covers every independent request in the current user turn.',
					'Use the supplied recent visible conversation to resolve follow-up requests.',
					'An empty selection is valid when the current turn does not require tools.',
					'Return JSON only in this exact shape: {"selected_sources":["source_id"]}.',
					'Do not explain the choice and do not invent source ids.'
				])
			],
			[
				'role' => 'user',
				'content' => "Recent visible conversation:\n" . $contextText
					. "\n\nSelect the tool sources for the next model decision."
					. "\nMaximum selected sources: " . $config->getMaxSources()
					. "\nMaximum exposed functions: " . $config->getMaxTools()
					. "\nCandidate sources:\n" . $candidateJson
			]
		];
	}

	/** @param array<int,mixed> $messages */
	private function buildSourceContext(array $messages, string $fallback): string {
		$rows = [];
		foreach (array_slice($messages, -12) as $message) {
			if (!is_array($message)) {
				continue;
			}

			$role = strtolower(trim((string)($message['role'] ?? '')));
			if (!in_array($role, ['user', 'assistant'], true)) {
				continue;
			}

			$content = $message['content'] ?? '';
			if (!is_scalar($content) || trim((string)$content) === '') {
				continue;
			}

			$rows[] = $role . ': ' . $this->limitText(trim((string)$content), 1000);
		}

		$context = trim(implode("\n", $rows));

		return $context !== '' ? $context : trim($fallback);
	}

	/** @return array<int,array<string,mixed>> */
	private function sourcePayload(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelection $candidates
	): array {
		$candidateSources = [];
		foreach ($candidates->getCapabilities() as $capability) {
			$candidateSources[$this->sourceKey($capability)] = true;
		}

		$sources = [];
		foreach ($catalog->all() as $capability) {
			$sourceKey = $this->sourceKey($capability);
			if (!isset($candidateSources[$sourceKey])) {
				continue;
			}
			if (!isset($sources[$sourceKey])) {
				$sources[$sourceKey] = [
					'source_id' => $sourceKey,
					'source_name' => $capability->getSourceName(),
					'functions' => []
				];
			}
			$sources[$sourceKey]['functions'][] = $this->capabilitySummary($capability);
		}

		return array_values($sources);
	}

	/** @return array<string,mixed> */
	private function capabilitySummary(AgentCapability $capability): array {
		$description = trim($capability->getDescription());
		if (strlen($description) > 600) {
			$description = substr($description, 0, 600);
		}

		$parameters = (array)($capability->getDefinition()['function']['parameters'] ?? []);

		return [
			'name' => $capability->getName(),
			'title' => $capability->getTitle(),
			'description' => $description,
			'category' => $capability->getCategory(),
			'tags' => $capability->getTags(),
			'source_id' => $capability->getSourceId(),
			'source_name' => $capability->getSourceName(),
			'parameter_names' => array_keys((array)($parameters['properties'] ?? [])),
			'required_parameter_names' => array_values(array_filter(
				(array)($parameters['required'] ?? []),
				static fn(mixed $name): bool => is_string($name) && trim($name) !== ''
			))
		];
	}

	/** @param array<int,mixed> $payload */
	private function encodePayload(array $payload): string {
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($json)) {
			throw new \RuntimeException('Semantic capability candidates could not be encoded.');
		}
		return $json;
	}

	private function limitText(string $text, int $maxCharacters): string {
		if (strlen($text) <= $maxCharacters) {
			return $text;
		}

		$separator = "\n...\n";
		$available = $maxCharacters - strlen($separator);
		$headLength = intdiv($available, 2);
		$tailLength = $available - $headLength;

		return substr($text, 0, $headLength) . $separator . substr($text, -$tailLength);
	}

	/** @return ?array<int,string> */
	private function parseSelectedNames(string $content, bool $sources): ?array {
		$content = trim($content);
		if ($content === '') {
			return null;
		}

		$content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content) ?? $content;
		$start = strpos($content, '{');
		$end = strrpos($content, '}');
		if ($start !== false && $end !== false && $end >= $start) {
			$content = substr($content, $start, $end - $start + 1);
		}

		$decoded = json_decode($content, true);
		$key = $sources ? 'selected_sources' : 'selected_tools';
		if (!is_array($decoded) || !is_array($decoded[$key] ?? null)) {
			return null;
		}

		$result = [];
		foreach ($decoded[$key] as $name) {
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

	private function buildFunctionSelection(
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

	private function buildSourceSelection(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionRequest $request,
		AgentCapabilitySelection $candidates,
		array $selectedSources,
		AiResultMetadata $metadata
	): ?AgentCapabilitySelection {
		$candidateSources = [];
		foreach ($candidates->getCapabilities() as $capability) {
			$candidateSources[$this->sourceKey($capability)] = true;
		}
		foreach ($selectedSources as $sourceName) {
			if (!isset($candidateSources[$sourceName])) {
				return null;
			}
		}

		$requiredSources = $this->sourceKeysForToolNames($catalog, $this->requiredNames($catalog->all(), $request));
		$previousSources = $request->getConfig()->isSticky()
			? $this->sourceKeysForToolNames($catalog, $request->getPreviousSelectedToolNames())
			: [];
		$orderedSources = [];
		foreach ([$requiredSources, $previousSources, $selectedSources] as $sourceNames) {
			foreach ($sourceNames as $sourceName) {
				if (isset($candidateSources[$sourceName]) || in_array($sourceName, $requiredSources, true) || in_array($sourceName, $previousSources, true)) {
					$orderedSources[$sourceName] = true;
				}
			}
		}

		return $this->selectionForSources(
			$catalog,
			$request,
			array_keys($orderedSources),
			$candidates->getEligibleSize(),
			$metadata,
			$requiredSources,
			$previousSources,
			'semantic-source',
			true
		);
	}

	private function expandSelectedSources(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionRequest $request,
		AgentCapabilitySelection $selection,
		string $reason
	): AgentCapabilitySelection {
		$sources = [];
		foreach ($selection->getCapabilities() as $capability) {
			$sources[$this->sourceKey($capability)] = true;
		}
		$requiredSources = $this->sourceKeysForToolNames($catalog, $this->requiredNames($catalog->all(), $request));
		$previousSources = $request->getConfig()->isSticky()
			? $this->sourceKeysForToolNames($catalog, $request->getPreviousSelectedToolNames())
			: [];
		foreach (array_merge($requiredSources, $previousSources) as $source) {
			$sources[$source] = true;
		}

		return $this->selectionForSources(
			$catalog,
			$request,
			array_keys($sources),
			$selection->getEligibleSize(),
			$selection->getModelMetadata(),
			$requiredSources,
			$previousSources,
			$reason,
			false
		);
	}

	/**
	 * @param array<int,string> $sourceKeys
	 * @param array<int,string> $requiredSources
	 * @param array<int,string> $previousSources
	 */
	private function selectionForSources(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionRequest $request,
		array $sourceKeys,
		int $eligibleSize,
		?AiResultMetadata $metadata,
		array $requiredSources,
		array $previousSources,
		string $reason,
		bool $strict
	): AgentCapabilitySelection {
		$sourceMap = [];
		foreach ($catalog->all() as $capability) {
			$sourceMap[$this->sourceKey($capability)][] = $capability;
		}

		$sourceKeys = array_values(array_unique($sourceKeys));
		if ($strict && count($sourceKeys) > $request->getConfig()->getMaxSources()) {
			throw new \RuntimeException(
				'Source-complete capability selection requires ' . count($sourceKeys)
				. ' sources but maxSources is ' . $request->getConfig()->getMaxSources() . '.'
			);
		}

		$requiredFunctionCount = 0;
		foreach ($sourceKeys as $sourceKey) {
			if (!isset($sourceMap[$sourceKey])) {
				throw new \RuntimeException('Selected capability source is unavailable: ' . $sourceKey);
			}
			$requiredFunctionCount += count($sourceMap[$sourceKey]);
		}
		if ($strict && $requiredFunctionCount > $request->getConfig()->getMaxTools()) {
			throw new \RuntimeException(
				'Source-complete capability selection requires ' . $requiredFunctionCount
				. ' functions but maxTools is ' . $request->getConfig()->getMaxTools() . '.'
			);
		}

		$capabilities = [];
		$scores = [];
		$reasons = [];
		$sourceCount = 0;
		$position = 0;
		foreach ($sourceKeys as $sourceKey) {
			$sourceCapabilities = $sourceMap[$sourceKey];
			if (!$strict && $sourceCount >= $request->getConfig()->getMaxSources()) {
				break;
			}
			if (!$strict && count($sourceCapabilities) > $request->getConfig()->getMaxTools()) {
				throw new \RuntimeException(
					'Capability source "' . $sourceKey . '" exposes ' . count($sourceCapabilities)
					. ' functions but maxTools is ' . $request->getConfig()->getMaxTools() . '.'
				);
			}
			if (!$strict && count($capabilities) + count($sourceCapabilities) > $request->getConfig()->getMaxTools()) {
				continue;
			}

			$isRequired = in_array($sourceKey, $requiredSources, true);
			$isPrevious = in_array($sourceKey, $previousSources, true);
			foreach ($sourceCapabilities as $capability) {
				$name = $capability->getName();
				$capabilities[] = $capability;
				$scores[$name] = $isRequired ? 1000.0 : max(1.0, 100.0 - $position);
				$capabilityReasons = [$reason, 'source:' . $sourceKey];
				if ($isRequired) {
					$capabilityReasons[] = 'mandatory-source';
				}
				if ($isPrevious) {
					$capabilityReasons[] = 'sticky-source';
				}
				$reasons[$name] = $capabilityReasons;
				$position++;
			}
			$sourceCount++;
		}

		return new AgentCapabilitySelection(
			iteration: $request->getIteration(),
			strategy: AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC,
			catalogSize: count($catalog),
			eligibleSize: $eligibleSize,
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

	/** @param array<int,string> $toolNames @return array<int,string> */
	private function sourceKeysForToolNames(AgentCapabilityCatalog $catalog, array $toolNames): array {
		$result = [];
		foreach ($toolNames as $toolName) {
			$capability = $catalog->get($toolName);
			if ($capability !== null) {
				$result[$this->sourceKey($capability)] = true;
			}
		}
		return array_keys($result);
	}

	private function sourceKey(AgentCapability $capability): string {
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

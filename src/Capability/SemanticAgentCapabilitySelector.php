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
 * Uses the active agent model to select complete tool sources or rerank a
 * bounded function candidate set. Invalid output, unavailable models, and
 * provider failures fall back to the deterministic hybrid selector.
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
		if ($config->selectsSources()) {
			return $this->selectSources($catalog, $request);
		}

		$fallback = $this->hybridSelector->select(
			$catalog,
			$this->withConfig($request, $this->hybridConfig($config, $config->getMaxTools(), $config->getSelectAllThreshold()))
		);

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
			$result = $model->complete($this->buildFunctionMessages($request, $candidates), []);
			$selectedNames = $this->parseSelectedNames($result->getContent(), false);
			if ($selectedNames === null) {
				return $this->rewrap($fallback, 'semantic-invalid-output', $result->getMetadata());
			}

			$selection = $this->buildFunctionSelection(
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

	private function selectSources(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionRequest $request
	): AgentCapabilitySelection {
		$config = $request->getConfig();
		$candidates = $this->hybridSelector->select(
			$catalog,
			$this->withConfig($request, $this->sourceCatalogConfig($config), false)
		);

		if (!$config->isEnabled()) {
			return $this->rewrap($candidates, 'semantic-source-disabled');
		}

		if ($this->isSmallPool($candidates, $config)) {
			return $this->selectionForSources(
				$catalog,
				$request,
				$this->sourceKeys($candidates->getCapabilities()),
				$candidates->getEligibleSize(),
				null,
				$this->sourceKeysForToolNames($catalog, $this->requiredNames($catalog->all(), $request)),
				[],
				'semantic-small-pool',
				false
			);
		}

		$model = $request->getModel();
		if ($model === null) {
			return $this->sourceFallback($catalog, $request, 'semantic-model-unavailable');
		}

		try {
			$result = $model->complete($this->buildSourceMessages($catalog, $request, $candidates), []);
			$selectedSources = $this->parseSelectedNames($result->getContent(), true);
			if ($selectedSources === null) {
				return $this->sourceFallback(
					$catalog,
					$request,
					'semantic-invalid-output',
					$result->getMetadata()
				);
			}

			$selection = $this->buildSourceSelection(
				$catalog,
				$request,
				$candidates,
				$selectedSources,
				$result->getMetadata()
			);
			if ($selection !== null) {
				return $selection;
			}

			return $this->sourceFallback(
				$catalog,
				$request,
				'semantic-invalid-output',
				$result->getMetadata()
			);
		}
		catch (\Throwable) {
			return $this->sourceFallback($catalog, $request, 'semantic-provider-fallback');
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

	private function sourceCatalogConfig(AgentCapabilitySelectionConfig $config): AgentCapabilitySelectionConfig {
		$data = $config->toArray();
		$data['strategy'] = AgentCapabilitySelectionConfig::STRATEGY_ALL;
		$data['select_all_threshold'] = 0;

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
					'Choose the smallest dependency-complete set for the current user request and the tool steps that can reasonably be anticipated now.',
					'Cover every independent factual need and requested action in the current user turn.',
					'Use recent conversation only to resolve intent, references, corrections, and the immediate active subject. For short, elliptical, misspelled, or ambiguous follow-ups, prefer the current active topic unless the user clearly changes it.',
					'Earlier assistant statements are not factual evidence. A request to check or verify a current runtime state still requires an authoritative capability when one is available.',
					'If a likely action or lookup depends on an identifier, state, candidate, schema, or other value not already available, include an available capability that can establish that prerequisite.',
					'Prefer authoritative domain capabilities over generic substitutes when both are available for the same material fact or action.',
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
		$maxCharacters = $config->getSemanticMaxPromptCharacters();
		$contextText = $this->buildSourceContext($request->getMessages(), $request->getContextText());
		$contextText = $this->limitText($contextText, max(1000, min(6000, intdiv($maxCharacters, 4))));
		$payloadBudget = max(4000, $maxCharacters - strlen($contextText) - 5000);
		$payload = $this->sourcePayload($catalog, $candidates, $contextText, $payloadBudget);
		$candidateJson = $this->encodePayload($payload);

		return [
			[
				'role' => 'system',
				'content' => implode("\n", [
					'You are a capability-source router for an AI agent.',
					'Select complete registered tool sources, not individual functions.',
					'Each selected source exposes its complete registered function set to the next model decision. Candidate metadata may show only representative functions when the catalog is large; function_count remains the total source size.',
					'Choose the smallest dependency-complete source set that covers every independent factual need and requested action in the current user turn.',
					'If an operation is likely to depend on identifiers, state, candidates, schemas, or other information supplied by another source, include that prerequisite source as well.',
					'Prefer the source that owns or authoritatively establishes the requested domain fact or action instead of a generic substitute.',
					'The current user message is authoritative. Use older messages only to resolve follow-up references.',
					'For short, elliptical, misspelled, or ambiguous follow-ups, resolve the message against the immediate active subject before selecting an unrelated source.',
					'Earlier assistant statements are context only and are not proof of current runtime state or successful actions. Verification requests still require an authoritative source when available.',
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
					. "\nCandidate sources:\n" . $candidateJson
			]
		];
	}

	/** @param array<int,mixed> $messages */
	private function buildSourceContext(array $messages, string $fallback): string {
		$rows = [];
		foreach ($messages as $message) {
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

			$row = $role . ': ' . $this->limitText(trim((string)$content), 1000);
			if ($rows !== [] && $rows[array_key_last($rows)] === $row) {
				continue;
			}

			$rows[] = $row;
		}
		$rows = array_slice($rows, -3);

		$context = trim(implode("\n", $rows));

		return $context !== '' ? $context : trim($fallback);
	}

	/** @return array<int,array<string,mixed>> */
	private function sourcePayload(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelection $candidates,
		string $contextText,
		int $maxCharacters
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
					'categories' => [],
					'tags' => [],
					'function_count' => 0,
					'capabilities' => []
				];
			}
			$category = trim($capability->getCategory());
			if ($category !== '' && !in_array($category, $sources[$sourceKey]['categories'], true)) {
				$sources[$sourceKey]['categories'][] = $category;
			}
			foreach ($capability->getTags() as $tag) {
				$tag = trim((string)$tag);
				if ($tag !== '' && !in_array($tag, $sources[$sourceKey]['tags'], true)) {
					$sources[$sourceKey]['tags'][] = $tag;
				}
			}
			$sources[$sourceKey]['function_count']++;
			$sources[$sourceKey]['capabilities'][] = $capability;
		}

		$full = [];
		foreach ($sources as $source) {
			$functions = [];
			foreach ($source['capabilities'] as $capability) {
				$functions[] = $this->sourceCapabilitySummary($capability);
			}
			$full[] = [
				'source_id' => $source['source_id'],
				'source_name' => $source['source_name'],
				'categories' => $source['categories'],
				'tags' => $source['tags'],
				'function_count' => $source['function_count'],
				'functions' => $functions
			];
		}

		if (strlen($this->encodePayload($full)) <= $maxCharacters) {
			return $full;
		}

		$sourceCount = max(1, count($sources));
		$perSourceCharacters = max(120, intdiv(max(1000, $maxCharacters - (2 * $sourceCount)), $sourceCount));
		$context = $this->normalizeSelectionText($contextText);
		$contextTokens = $this->selectionTokens($context);
		$compact = [];

		foreach ($sources as $source) {
			$base = [
				'source_id' => $source['source_id'],
				'source_name' => $source['source_name'],
				'function_count' => $source['function_count']
			];

			$ranked = $source['capabilities'];
			usort($ranked, function(AgentCapability $left, AgentCapability $right) use ($context, $contextTokens): int {
				$score = $this->sourceCapabilityRelevance($right, $context, $contextTokens)
					<=> $this->sourceCapabilityRelevance($left, $context, $contextTokens);
				if ($score !== 0) {
					return $score;
				}
				return strcmp($left->getName(), $right->getName());
			});

			$summary = $base;
			$summary['representative_functions'] = [];
			foreach ($ranked as $capability) {
				$function = [
					'name' => $capability->getName(),
					'title' => $capability->getTitle()
				];
				$candidate = $summary;
				$candidate['representative_functions'][] = $function;
				if (strlen($this->encodePayload([$candidate])) > $perSourceCharacters) {
					$function = ['name' => $capability->getName()];
					$candidate = $summary;
					$candidate['representative_functions'][] = $function;
					if (strlen($this->encodePayload([$candidate])) > $perSourceCharacters) {
						break;
					}
				}
				$summary = $candidate;
			}
			if ($summary['representative_functions'] === []) {
				unset($summary['representative_functions']);
			}

			$categories = array_slice($source['categories'], 0, 6);
			if ($categories !== []) {
				$candidate = $summary;
				$candidate['categories'] = $categories;
				if (strlen($this->encodePayload([$candidate])) <= $perSourceCharacters) {
					$summary = $candidate;
				}
			}

			$tags = array_slice($source['tags'], 0, 8);
			if ($tags !== []) {
				$candidate = $summary;
				$candidate['tags'] = $tags;
				if (strlen($this->encodePayload([$candidate])) <= $perSourceCharacters) {
					$summary = $candidate;
				}
			}

			$compact[] = $summary;
		}

		if (strlen($this->encodePayload($compact)) <= $maxCharacters) {
			return $compact;
		}

		// Preserve every candidate source when the function catalog is extremely
		// large. Metadata is reduced before any source id is omitted.
		$minimal = [];
		foreach ($sources as $source) {
			$minimal[] = [
				'source_id' => $source['source_id'],
				'source_name' => $source['source_name'],
				'function_count' => $source['function_count']
			];
		}

		if (strlen($this->encodePayload($minimal)) <= $maxCharacters) {
			return $minimal;
		}

		return [[
			'source_ids' => array_values(array_map(
				static fn(array $source): string => (string)$source['source_id'],
				$sources
			))
		]];
	}

	/** @param array<string,bool> $contextTokens */
	private function sourceCapabilityRelevance(
		AgentCapability $capability,
		string $context,
		array $contextTokens
	): int {
		$score = 0;
		$name = $this->normalizeSelectionText($capability->getName());
		$title = $this->normalizeSelectionText($capability->getTitle());
		if ($name !== '' && str_contains($context, $name)) {
			$score += 100;
		}
		if ($title !== '' && str_contains($context, $title)) {
			$score += 60;
		}

		$searchable = implode(' ', [
			$capability->getName(),
			$capability->getTitle(),
			$capability->getDescription(),
			$capability->getCategory(),
			implode(' ', $capability->getTags())
		]);
		foreach ($this->selectionTokens($this->normalizeSelectionText($searchable)) as $token => $_) {
			if (isset($contextTokens[$token])) {
				$score += 4;
			}
		}

		return $score;
	}

	/** @return array<string,bool> */
	private function selectionTokens(string $text): array {
		$parts = preg_split('/[^\\p{L}\\p{N}_]+/u', $text) ?: [];
		$result = [];
		foreach ($parts as $part) {
			if (strlen($part) >= 2) {
				$result[$part] = true;
			}
		}
		return $result;
	}

	private function normalizeSelectionText(string $text): string {
		$text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
		$text = str_replace(['-', '.', '/', '\\'], ' ', $text);
		return trim(preg_replace('/\\s+/', ' ', $text) ?? $text);
	}

	/** @return array<string,mixed> */
	private function sourceCapabilitySummary(AgentCapability $capability): array {
		$description = trim($capability->getDescription());
		if (strlen($description) > 160) {
			$description = substr($description, 0, 160);
		}

		return [
			'name' => $capability->getName(),
			'title' => $capability->getTitle(),
			'description' => $description
		];
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

	private function sourceFallback(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionRequest $request,
		string $reason,
		?AiResultMetadata $metadata = null
	): AgentCapabilitySelection {
		$config = $request->getConfig();
		$fallback = $this->hybridSelector->select(
			$catalog,
			$this->withConfig(
				$request,
				$this->hybridConfig($config, $config->getMaxTools(), $config->getSelectAllThreshold())
			)
		);
		$fallback = $this->expandSelectedSources($catalog, $request, $fallback, 'semantic-source-fallback');

		return $this->rewrap($fallback, $reason, $metadata);
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

		foreach ($sourceKeys as $sourceKey) {
			if (!isset($sourceMap[$sourceKey])) {
				throw new \RuntimeException('Selected capability source is unavailable: ' . $sourceKey);
			}
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

	/** @param array<int,AgentCapability> $capabilities @return array<int,string> */
	private function sourceKeys(array $capabilities): array {
		$result = [];
		foreach ($capabilities as $capability) {
			$result[$this->sourceKey($capability)] = true;
		}

		return array_keys($result);
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

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

/**
 * Deterministic selector using hard agent filters plus lexical relevance,
 * tool metadata, priority, and short-term selection stability.
 */
final class HybridAgentCapabilitySelector implements IAgentCapabilitySelector {

	public function select(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionRequest $request
	): AgentCapabilitySelection {
		$config = $request->getConfig();
		$eligible = $this->eligibleCapabilities($catalog, $config);
		$required = $this->requiredNames($eligible, $request);

		if (
			!$config->isEnabled()
			|| $config->getStrategy() === AgentCapabilitySelectionConfig::STRATEGY_ALL
			|| (
				$config->getSelectAllThreshold() > 0
				&& count($eligible) <= min($config->getSelectAllThreshold(), $config->getMaxTools())
			)
		) {
			return $this->selectAll($catalog, $eligible, $request, $config->isEnabled() ? 'small-pool-or-all' : 'disabled');
		}

		if (count($required) > $config->getMaxTools()) {
			throw new \RuntimeException(
				'Capability selection requires ' . count($required) . ' mandatory tools but maxTools is ' . $config->getMaxTools() . '.'
			);
		}

		$contextText = $this->normalizeText($request->getContextText());
		$contextTokens = $this->tokenize($contextText);
		$previous = array_fill_keys($request->getPreviousSelectedToolNames(), true);
		$recent = array_fill_keys($request->getRecentToolNames(), true);
		$requiredMap = array_fill_keys($required, true);
		$ranked = [];

		foreach ($eligible as $capability) {
			[$score, $reasons] = $this->score(
				$capability,
				$contextText,
				$contextTokens,
				isset($previous[$capability->getName()]) && $config->isSticky(),
				isset($recent[$capability->getName()]),
				isset($requiredMap[$capability->getName()])
			);
			$ranked[] = [
				'capability' => $capability,
				'score' => $score,
				'reasons' => $reasons
			];
		}

		usort($ranked, static function(array $left, array $right): int {
			$score = $right['score'] <=> $left['score'];
			if ($score !== 0) {
				return $score;
			}
			$priority = $right['capability']->getPriority() <=> $left['capability']->getPriority();
			if ($priority !== 0) {
				return $priority;
			}
			return strcmp($left['capability']->getName(), $right['capability']->getName());
		});

		$selected = [];
		$scores = [];
		$reasons = [];

		foreach ($ranked as $entry) {
			if (count($selected) >= $config->getMaxTools()) {
				break;
			}
			$capability = $entry['capability'];
			$selected[] = $capability;
			$scores[$capability->getName()] = round((float)$entry['score'], 3);
			$reasons[$capability->getName()] = $entry['reasons'];
		}

		return new AgentCapabilitySelection(
			iteration: $request->getIteration(),
			strategy: AgentCapabilitySelectionConfig::STRATEGY_HYBRID,
			catalogSize: count($catalog),
			eligibleSize: count($eligible),
			capabilities: $selected,
			scores: $scores,
			reasons: $reasons
		);
	}

	/** @return array<int,AgentCapability> */
	private function eligibleCapabilities(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionConfig $config
	): array {
		$includeTools = array_fill_keys($config->getIncludeTools(), true);
		$excludeTools = array_fill_keys($config->getExcludeTools(), true);
		$includeTags = array_fill_keys(array_map('strtolower', $config->getIncludeTags()), true);
		$excludeTags = array_fill_keys(array_map('strtolower', $config->getExcludeTags()), true);
		$includeCategories = array_fill_keys(array_map('strtolower', $config->getIncludeCategories()), true);
		$excludeCategories = array_fill_keys(array_map('strtolower', $config->getExcludeCategories()), true);
		$result = [];

		foreach ($catalog->all() as $capability) {
			$name = $capability->getName();
			$category = strtolower($capability->getCategory());
			$tags = array_fill_keys(array_map('strtolower', $capability->getTags()), true);

			if ($includeTools !== [] && !isset($includeTools[$name])) {
				continue;
			}
			if (isset($excludeTools[$name])) {
				continue;
			}
			if ($includeCategories !== [] && !isset($includeCategories[$category])) {
				continue;
			}
			if (isset($excludeCategories[$category])) {
				continue;
			}
			if ($includeTags !== [] && !$this->mapsIntersect($tags, $includeTags)) {
				continue;
			}
			if ($excludeTags !== [] && $this->mapsIntersect($tags, $excludeTags)) {
				continue;
			}
			$result[] = $capability;
		}

		return $result;
	}

	/**
	 * @param array<int,AgentCapability> $eligible
	 * @return array<int,string>
	 */
	private function requiredNames(array $eligible, AgentCapabilitySelectionRequest $request): array {
		$eligibleMap = [];
		foreach ($eligible as $capability) {
			$eligibleMap[$capability->getName()] = $capability;
		}

		$required = [];
		foreach ($request->getConfig()->getAlwaysAvailable() as $name) {
			$required[$name] = true;
		}
		foreach ($request->getRequiredToolNames() as $name) {
			$required[$name] = true;
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
	 * @param array<int,AgentCapability> $eligible
	 */
	private function selectAll(
		AgentCapabilityCatalog $catalog,
		array $eligible,
		AgentCapabilitySelectionRequest $request,
		string $reason
	): AgentCapabilitySelection {
		$scores = [];
		$reasons = [];
		foreach ($eligible as $capability) {
			$scores[$capability->getName()] = (float)$capability->getPriority();
			$reasons[$capability->getName()] = [$reason];
		}
		return new AgentCapabilitySelection(
			$request->getIteration(),
			AgentCapabilitySelectionConfig::STRATEGY_ALL,
			count($catalog),
			count($eligible),
			$eligible,
			$scores,
			$reasons
		);
	}

	/** @return array{0:float,1:array<int,string>} */
	private function score(
		AgentCapability $capability,
		string $contextText,
		array $contextTokens,
		bool $previous,
		bool $recent,
		bool $required
	): array {
		$score = max(-100, min(100, $capability->getPriority())) / 10;
		$reasons = ['priority'];
		$name = strtolower($capability->getName());
		$title = strtolower($capability->getTitle());
		$category = strtolower($capability->getCategory());

		if ($required) {
			$score += 1000;
			$reasons[] = 'mandatory';
		}
		if ($recent) {
			$score += 90;
			$reasons[] = 'recent-tool';
		}
		if ($previous) {
			$score += 18;
			$reasons[] = 'sticky';
		}
		if ($name !== '' && str_contains($contextText, $name)) {
			$score += 90;
			$reasons[] = 'name-match';
		}
		if ($title !== '' && str_contains($contextText, $title)) {
			$score += 45;
			$reasons[] = 'title-match';
		}
		if ($category !== '' && isset($contextTokens[$category])) {
			$score += 30;
			$reasons[] = 'category-match';
		}

		foreach ($capability->getTags() as $tag) {
			$tag = strtolower($tag);
			if ($tag !== '' && (isset($contextTokens[$tag]) || str_contains($contextText, $tag))) {
				$score += 24;
				$reasons[] = 'tag:' . $tag;
			}
		}

		$searchable = implode(' ', [
			$capability->getName(),
			$capability->getTitle(),
			$capability->getDescription(),
			$capability->getCategory(),
			implode(' ', $capability->getTags()),
			implode(' ', array_keys((array)($capability->getDefinition()['function']['parameters']['properties'] ?? [])))
		]);
		$overlap = 0;
		foreach ($this->tokenize($this->normalizeText($searchable)) as $token => $_) {
			if (isset($contextTokens[$token])) {
				$overlap++;
			}
		}
		if ($overlap > 0) {
			$score += min(36, $overlap * 4);
			$reasons[] = 'token-overlap:' . $overlap;
		}

		return [$score, array_values(array_unique($reasons))];
	}

	/** @return array<string,bool> */
	private function tokenize(string $text): array {
		$parts = preg_split('/[^\p{L}\p{N}_]+/u', $text) ?: [];
		$result = [];
		foreach ($parts as $part) {
			if (strlen($part) >= 2) {
				$result[$part] = true;
			}
		}
		return $result;
	}

	private function normalizeText(string $text): string {
		$text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
		$text = str_replace(['-', '.', '/', '\\'], ' ', $text);
		return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
	}

	/** @param array<string,bool> $left @param array<string,bool> $right */
	private function mapsIntersect(array $left, array $right): bool {
		foreach ($left as $key => $_) {
			if (isset($right[$key])) {
				return true;
			}
		}
		return false;
	}
}

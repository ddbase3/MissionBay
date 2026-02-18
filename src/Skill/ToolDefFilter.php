<?php declare(strict_types=1);

namespace MissionBay\Skill;

use MissionBay\Api\IAgentTool;

final class ToolDefFilter {

	/**
	 * @param IAgentTool[] $tools
	 * @return array{toolDefs: array, report: ToolFilterReport, allowedToolNames: ?array}
	 */
	public function filter(array $tools, SkillPlan $plan): array {
		$available = $this->collectAvailableToolNames($tools);

		$allowed = $plan->getAllowedTools();
		$allowedEffective = $allowed === null
			? null
			: $this->intersectNames($available, $allowed);

		$missingRequired = $this->diffNames($plan->getRequiredTools(), $available);

		$toolDefs = $this->collectToolDefinitions($tools, $allowedEffective);

		return [
			'toolDefs' => $toolDefs,
			'report' => new ToolFilterReport($missingRequired),
			'allowedToolNames' => $allowedEffective
		];
	}

	/**
	 * @param IAgentTool[] $tools
	 * @return string[]
	 */
	private function collectAvailableToolNames(array $tools): array {
		$out = [];

		foreach ($tools as $tool) {
			foreach ($tool->getToolDefinitions() as $def) {
				$name = (string)($def['function']['name'] ?? '');
				if ($name !== '') {
					$out[$name] = true;
				}
			}
		}

		$names = array_keys($out);
		sort($names);
		return $names;
	}

	/**
	 * @param IAgentTool[] $tools
	 * @param string[]|null $allowedToolNames If null, no filtering is applied.
	 * @return array
	 */
	private function collectToolDefinitions(array $tools, ?array $allowedToolNames): array {
		$allowedMap = null;

		if (is_array($allowedToolNames)) {
			$allowedMap = array_fill_keys($allowedToolNames, true);
		}

		$out = [];

		foreach ($tools as $tool) {
			foreach ($tool->getToolDefinitions() as $def) {
				$name = (string)($def['function']['name'] ?? '');
				if ($name === '') {
					continue;
				}
				if ($allowedMap !== null && !isset($allowedMap[$name])) {
					continue;
				}
				$out[] = $def;
			}
		}

		return $out;
	}

	/**
	 * @param string[] $have
	 * @param string[] $want
	 * @return string[]
	 */
	private function intersectNames(array $have, array $want): array {
		$haveMap = array_fill_keys($have, true);
		$out = [];

		foreach ($want as $name) {
			$name = (string)$name;
			if ($name !== '' && isset($haveMap[$name])) {
				$out[$name] = true;
			}
		}

		$names = array_keys($out);
		sort($names);
		return $names;
	}

	/**
	 * @param string[] $needles
	 * @param string[] $haystack
	 * @return string[]
	 */
	private function diffNames(array $needles, array $haystack): array {
		$hayMap = array_fill_keys($haystack, true);
		$out = [];

		foreach ($needles as $name) {
			$name = (string)$name;
			if ($name !== '' && !isset($hayMap[$name])) {
				$out[$name] = true;
			}
		}

		$names = array_keys($out);
		sort($names);
		return $names;
	}
}

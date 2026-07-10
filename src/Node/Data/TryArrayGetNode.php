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

namespace MissionBay\Node\Data;

use MissionBay\Api\IAgentNode;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class TryArrayGetNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'tryarraygetnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'array',
				description: 'The nested array to search in.',
				type: 'array',
				required: true
			),
			new AgentNodePort(
				name: 'path',
				description: 'Dot-notation path to the desired value (e.g., "user.profile.name").',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'value',
				description: 'The resolved value at the specified path, if it exists.',
				type: 'mixed',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$array = $inputs['array'] ?? [];
		$path = $inputs['path'] ?? '';

		if (!is_array($array) || !is_string($path) || $path === '') {
			return [];
		}

		$keys = explode('.', $path);
		$current = $array;

		foreach ($keys as $key) {
			if (!is_array($current) || !array_key_exists($key, $current)) {
				return [];
			}
			if (!array_key_exists($key, $current)) {
				return [];
			}
			$current = $current[$key];
		}

		return ['value' => $current];
	}

	public function getDescription(): string {
		return 'Tries to retrieve a value from a nested array using dot-notation path syntax. If the path does not exist, no output is returned.';
	}
}


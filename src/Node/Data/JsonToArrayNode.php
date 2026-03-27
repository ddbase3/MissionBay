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

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class JsonToArrayNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'jsontoarraynode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'json',
				description: 'A valid JSON string to be parsed.',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'array',
				description: 'The resulting associative array parsed from the JSON input.',
				type: 'array',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if the JSON is invalid or cannot be parsed.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$json = $inputs['json'] ?? '';

		$data = json_decode($json, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return ['error' => $this->error('Invalid JSON: ' . json_last_error_msg())];
		}

		return ['array' => $data];
	}

	public function getDescription(): string {
		return 'Parses a JSON string and converts it into an associative PHP array. Useful for processing API responses or any structured JSON data.';
	}
}


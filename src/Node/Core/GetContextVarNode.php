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

namespace MissionBay\Node\Core;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class GetContextVarNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'getcontextvarnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'key',
				description: 'The name of the context variable to retrieve.',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'value',
				description: 'The value retrieved from context, if found.',
				type: 'mixed',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if key is invalid or value not found.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$key = $inputs['key'] ?? null;

		if (!is_string($key)) {
			return ['error' => $this->error('GetContextVarNode: "key" must be a string')];
		}

		$value = $context->getVar($key);

		if ($value === null) {
			return ['error' => $this->error('Context variable not found: ' . $key)];
		}

		return ['value' => $value];
	}

	public function getDescription(): string {
		return 'Retrieves a variable from the AgentContext by key. Useful for accessing shared values across multiple nodes during flow execution.';
	}
}


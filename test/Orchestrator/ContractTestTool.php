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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentToolContractValidation;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\CoreFoundation\Api\IOutputSchemaProvider;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use PHPUnit\Framework\TestCase;

final class ContractTestTool implements IAgentTool, IOutputSchemaProvider {

	public static function getName(): string {
		return 'contracttesttool';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'lookup',
				'description' => 'Looks up a record.',
				'parameters' => [
					'type' => 'object',
					'required' => ['query'],
					'properties' => [
						'query' => ['type' => 'string'],
						'limit' => ['type' => 'integer']
					]
				]
			]
		]];
	}

	public function getOutputSchemas(): array {
		return [
			'lookup' => [
				'type' => 'object',
				'required' => ['found'],
				'properties' => [
					'found' => ['type' => 'boolean']
				]
			]
		];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return ['found' => true];
	}
}

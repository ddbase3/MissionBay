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

use Base3\AssistantFoundation\Api\IAgentActionPolicy;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionDecision;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use PHPUnit\Framework\TestCase;

final class PolicyContractTool implements IAgentTool {

	public static function getName(): string {
		return 'policycontracttool';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'lookup',
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

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		throw new \RuntimeException('This tool must not be called for invalid input.');
	}
}

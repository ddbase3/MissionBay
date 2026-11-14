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

namespace Base3\MissionBay\Test\Capability;

use Base3\AssistantFoundation\Api\IAgentCapabilityProvider;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\AssistantFoundation\Api\IAgentModule;
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use Base3\AssistantFoundation\Dto\AgentModuleActivation;
use Base3\AssistantFoundation\Dto\AgentModuleManifest;
use Base3\AssistantFoundation\Dto\AgentStageMount;
use Base3\AssistantFoundation\Dto\AgentStageResult;
use Base3\AssistantFoundation\Dto\AgentStageSlot;
use Base3\CoreFoundation\Api\IComponent;
use Base3\CoreFoundation\Api\IComponentResolver;
use Base3\MissionBay\Api\IAgentPromptProvider;
use Base3\MissionBay\Api\IAgentResourceProvider;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Capability\AgentCapabilityDiscoveryService;
use PHPUnit\Framework\TestCase;

final class DiscoveryTool implements IAgentTool, IComponent {

	public function __construct(private string $id, private string $toolName) {}
	public static function getName(): string { return 'discoverytool'; }
	public function id(): string { return $this->id; }
	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => $this->toolName,
				'description' => 'Test tool.',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		]];
	}
	public function callTool(string $name, array $arguments, IAgentContext $context): mixed { return ['ok' => true]; }
}

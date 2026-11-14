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

final class DiscoveryContext implements IAgentContext {

	private array $vars = [];
	private IAgentMemory $memory;

	public function __construct() {
		$this->memory = new DiscoveryMemory();
	}
	public static function getName(): string { return 'discoverycontext'; }
	public function getMemory(): IAgentMemory { return $this->memory; }
	public function setMemory(IAgentMemory $memory): void { $this->memory = $memory; }
	public function setVar(string $key, mixed $value): void { $this->vars[$key] = $value; }
	public function getVar(string $key): mixed { return $this->vars[$key] ?? null; }
	public function forgetVar(string $key): void { unset($this->vars[$key]); }
	public function listVars(): array { return array_keys($this->vars); }
}

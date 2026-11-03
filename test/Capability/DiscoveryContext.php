<?php declare(strict_types=1);

namespace MissionBay\Test\Capability;

use AssistantFoundation\Api\IAgentCapabilityProvider;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Api\IAgentModule;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use AssistantFoundation\Dto\AgentModuleActivation;
use AssistantFoundation\Dto\AgentModuleManifest;
use AssistantFoundation\Dto\AgentStageMount;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentStageSlot;
use Base3\Api\IComponent;
use Base3\Api\IComponentResolver;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Capability\AgentCapabilityDiscoveryService;
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

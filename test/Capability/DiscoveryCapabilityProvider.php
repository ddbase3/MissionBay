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

final class DiscoveryCapabilityProvider implements IAgentCapabilityProvider {

	/** @param array<int,IAgentTool> $tools @param array<int,IAgentResourceProvider> $resources @param array<int,IAgentPromptProvider> $prompts */
	public function __construct(private string $id, private array $toolValues, private array $resources, private array $prompts) {}
	public static function getName(): string { return 'discoverycapabilityprovider'; }
	public function id(): string { return $this->id; }
	public function name(): string { return $this->id; }
	public function tools(IAgentContext $context): iterable { return $this->toolValues; }
	public function resourceProviders(IAgentContext $context): iterable { return $this->resources; }
	public function promptProviders(IAgentContext $context): iterable { return $this->prompts; }
}

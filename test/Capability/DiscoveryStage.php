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

final class DiscoveryStage implements IAgentStage {

	public function __construct(private string $id) {}
	public static function getName(): string { return 'discoverystage'; }
	public function id(): string { return $this->id; }
	public function name(): string { return $this->id; }
	public function getDescription(): string { return 'Test module stage.'; }
	public function getAiUsage(): string { return IAgentStage::AI_USAGE_NONE; }
	public function supports(IAgentContext $context): bool { return false; }
	public function process(IAgentContext $context): AgentStageResult { return AgentStageResult::none(); }
}

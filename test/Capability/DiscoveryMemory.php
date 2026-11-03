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

final class DiscoveryMemory implements IAgentMemory {
	public static function getName(): string { return 'discoverymemory'; }
	public function loadNodeHistory(string $nodeId): array { return []; }
	public function appendNodeHistory(string $nodeId, array $message): void {}
	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
	public function resetNodeHistory(string $nodeId): void {}
	public function getPriority(): int { return 0; }
}

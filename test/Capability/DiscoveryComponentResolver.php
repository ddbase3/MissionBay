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

final class DiscoveryComponentResolver implements IComponentResolver {

	/** @param array<string,array<string,IComponent>> $components */
	public function __construct(private array $components) {}

	public function has(string $interfaceName, string $id): bool {
		return isset($this->components[$interfaceName][$id]);
	}

	public function get(string $interfaceName, string $id): ?IComponent {
		return $this->components[$interfaceName][$id] ?? null;
	}

	public function all(string $interfaceName): iterable {
		return array_values($this->components[$interfaceName] ?? []);
	}
}

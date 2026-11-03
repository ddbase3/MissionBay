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

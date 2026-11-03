<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentCapabilitySourceMetadata;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\AbstractAgentResource;
use MissionBay\Resource\ConfiguredAgentToolResource;
use MissionBay\Service\AgentComponentPresetMaterializer;
use PHPUnit\Framework\TestCase;

final class MaterializerResourceFactoryDouble implements IAgentResourceFactory {

	public function __construct(private readonly MaterializerRemoteToolDouble $remoteTool) {}

	public function createResource(string $type): ?IAgentResource {
		return match($type) {
			MaterializerRemoteToolDouble::getName() => $this->remoteTool,
			ConfiguredAgentToolResource::getName() => new ConfiguredAgentToolResource(
				new MaterializerIdentityResolverDouble(),
				new MaterializerEventManagerDouble()
			),
			default => null
		};
	}
}

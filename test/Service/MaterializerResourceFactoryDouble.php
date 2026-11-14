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

namespace Base3\MissionBay\Test\Service;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentCapabilitySourceMetadata;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IAgentContextFactory;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\AbstractAgentResource;
use Base3\MissionBay\Resource\ConfiguredAgentToolResource;
use Base3\MissionBay\Service\AgentComponentPresetMaterializer;
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

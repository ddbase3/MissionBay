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

namespace Base3\MissionBay\Test\Mcp;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentPromptProvider;
use Base3\MissionBay\Api\IAgentResourceProvider;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;
use Base3\MissionBay\Mcp\McpToolPresetMaterializer;
use Base3\MissionBay\Resource\AbstractAgentResource;
use PHPUnit\Framework\TestCase;

final class McpPresetMaterializerDouble implements IAgentComponentPresetMaterializer {

	private int $materializeCalls = 0;

	public function __construct(private readonly McpPresetCapabilityResource $resource) {}

	public function createContext(array $vars = []): IAgentContext {
		return new AgentContext(null, $vars);
	}

	public function materialize(string $presetId, IAgentContext $context): AgentComponentPresetMaterialization {
		$this->materializeCalls++;

		return new AgentComponentPresetMaterialization(
			$presetId,
			[
				'id' => $presetId,
				'type' => McpPresetCapabilityResource::getName(),
				'enabled' => true,
				'capabilities' => ['tool']
			],
			$this->resource,
			$this->resource,
			null,
			null,
			['tool'],
			[],
			[]
		);
	}

	public function getMaterializeCalls(): int {
		return $this->materializeCalls;
	}
}

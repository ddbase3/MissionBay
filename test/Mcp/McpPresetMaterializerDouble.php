<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Mcp\McpToolPresetMaterializer;
use MissionBay\Resource\AbstractAgentResource;
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

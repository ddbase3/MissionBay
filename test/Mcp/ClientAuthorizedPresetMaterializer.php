<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Api\IClassMap;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentTool;
use MissionBay\Api\IConfirmableAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Mcp\McpJsonRpcHandler;
use MissionBay\Mcp\McpToolDefinitionMapper;
use MissionBay\Mcp\McpToolPresetMaterializer;
use MissionBay\Mcp\McpToolProfileRepository;
use MissionBay\Mcp\McpToolResultMapper;
use PHPUnit\Framework\TestCase;

final class ClientAuthorizedPresetMaterializer implements IAgentComponentPresetMaterializer {

	public function __construct(private readonly ClientAuthorizedToolTestDouble $tool) {}

	public function createContext(array $vars = []): IAgentContext {
		return new AgentContext(null, $vars);
	}

	public function materialize(string $presetId, IAgentContext $context): AgentComponentPresetMaterialization {
		return new AgentComponentPresetMaterialization(
			$presetId,
			[
				'id' => $presetId,
				'type' => ClientAuthorizedToolTestDouble::getName(),
				'enabled' => true,
				'capabilities' => ['tool']
			],
			null,
			$this->tool,
			null,
			null,
			['tool']
		);
	}
}

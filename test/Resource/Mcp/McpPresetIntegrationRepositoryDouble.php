<?php declare(strict_types=1);

namespace MissionBay\Test\Resource\Mcp;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IMcpClient;
use MissionBay\Api\IMcpClientFactory;
use MissionBay\Capability\AgentCapabilityCatalogBuilder;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Mcp\Client\McpRemoteToolDefinitionMapper;
use MissionBay\Mcp\Client\McpRemoteToolResultMapper;
use MissionBay\Profile\AgentAssistantToolSetupFactory;
use MissionBay\Resource\ConfiguredAgentToolResource;
use MissionBay\Resource\Mcp\McpClientAgentResource;
use MissionBay\Service\AgentComponentPresetMaterializer;
use PHPUnit\Framework\TestCase;

final class McpPresetIntegrationRepositoryDouble implements IAgentComponentPresetRepository {

	/** @var array<string,array<string,mixed>> */
	private array $presets = [
		'deepwiki' => [
			'id' => 'deepwiki',
			'label' => 'DeepWiki',
			'type' => 'mcpclientagentresource',
			'enabled' => true,
			'capabilities' => ['tool'],
			'config' => [
				'endpoint' => 'https://mcp.deepwiki.com/mcp',
				'auth_type' => 'none'
			],
			'docks' => []
		]
	];

	public function getPresets(): array {
		return $this->presets;
	}

	public function getPreset(string $id, array $default = []): array {
		return $this->presets[$id] ?? $default;
	}

	public function hasPreset(string $id): bool {
		return isset($this->presets[$id]);
	}

	public function savePreset(string $id, array $preset): void {
		$this->presets[$id] = $preset;
	}

	public function removePreset(string $id): void {
		unset($this->presets[$id]);
	}
}

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

final class ClientAuthorizedSettingsStore implements ISettingsStore {

	/** @param array<string,array<string,array<string,mixed>>> $data */
	public function __construct(private array $data) {}

	public function get(string $group, string $name, array $default = []): array {
		return $this->data[$group][$name] ?? $default;
	}

	public function set(string $group, string $name, array $settings): void {
		$this->data[$group][$name] = $settings;
	}

	public function has(string $group, string $name): bool {
		return isset($this->data[$group][$name]);
	}

	public function remove(string $group, string $name): void {
		unset($this->data[$group][$name]);
	}

	public function getGroup(string $group): array {
		return $this->data[$group] ?? [];
	}

	public function save(): void {
	}

	public function reload(): void {
	}
}

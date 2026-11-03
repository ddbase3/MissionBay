<?php declare(strict_types=1);

namespace MissionBay\Test\ParserService;

use AssistantFoundation\Api\IServiceDriverDefinition;
use AssistantFoundation\Dto\ParserFileRequest;
use AssistantFoundation\Dto\ParserServiceResult;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IParserService;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;
use MissionBay\ParserService\ConfiguredParserServiceResolver;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use PHPUnit\Framework\TestCase;

final class ResolverTestSettingsStore implements ISettingsStore {

	/**
	 * @param array<string,array<string,array<string,mixed>>> $data
	 */
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

	public function save(): void {}
	public function reload(): void {}
}

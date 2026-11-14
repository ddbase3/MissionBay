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

namespace Base3\MissionBay\Test\ParserService;

use Base3\AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\AssistantFoundation\Dto\ParserFileRequest;
use Base3\AssistantFoundation\Dto\ParserServiceResult;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IParserService;
use Base3\MissionBay\Dto\AgentContentItem;
use Base3\MissionBay\Dto\AgentParsedContent;
use Base3\MissionBay\ParserService\ConfiguredParserServiceResolver;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
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

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

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\CoreFoundation\Api\IOutputSchemaProvider;
use Base3\MissionBay\Api\IAgentTool;

final class ConfiguredAgentToolCoverageTool implements IAgentTool, IOutputSchemaProvider {

	/** @var array<int,array<string,mixed>|string> */
	public array $definitions = [
		['type' => 'function', 'label' => 'Read', 'function' => ['name' => 'lookup', 'description' => 'Read item', 'parameters' => ['type' => 'object']]],
		['type' => 'function', 'function' => ['name' => 'ping', 'parameters' => ['type' => 'object']]]
	];
	/** @var array<string,array<string,mixed>> */
	public array $schemas = ['lookup' => ['type' => 'string'], 'ping' => ['type' => 'boolean'], 'not_visible' => ['type' => 'null']];
	/** @var array<int,array{string,array}> */
	public array $calls = [];
	public bool $throwOnCall = false;

	public static function getName(): string {
		return 'configuredagenttoolcoveragetool';
	}

	public function getToolDefinitions(): array {
		return $this->definitions;
	}

	public function getOutputSchemas(): array {
		return $this->schemas;
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->calls[] = [$name, $arguments];
		if ($this->throwOnCall) {
			throw new \RuntimeException('Tool execution failed');
		}
		return ['called' => $name, 'arguments' => $arguments];
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use Base3\Api\IOutputSchemaProvider;
use MissionBay\Api\IAgentTool;

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

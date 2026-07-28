<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Api\IOutputSchemaProvider;
use Base3\Logger\Api\ILogger;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentTool;

/**
 * McpToolCatalog
 *
 * Holds materialized tools and maps effective tool names to their executor.
 */
class McpToolCatalog {

	private const LOG_SCOPE = 'missionbay_mcp';

	/**
	 * @var array<int,array<string,mixed>>
	 */
	private array $definitions = [];

	/**
	 * @var array<string,array<string,mixed>>
	 */
	private array $definitionsByName = [];

	/**
	 * @var array<string,IAgentTool>
	 */
	private array $toolsByName = [];

	/**
	 * @param IAgentTool[] $tools
	 */
	public function __construct(
		array $tools,
		private readonly McpToolDefinitionMapper $definitionMapper,
		private readonly ILogger $logger
	) {
		$this->build($tools);
	}

	public static function getName(): string {
		return 'mcptoolcatalog';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function listTools(): array {
		return array_map(fn(array $definition): array => $this->definitionMapper->toMcpTool($definition), $this->definitions);
	}

	/**
	 * @param array<string,mixed> $arguments
	 */
	public function call(string $name, array $arguments, IAgentContext $context): mixed {
		return $this->getTool($name)->callTool($name, $arguments, $context);
	}

	public function getTool(string $name): IAgentTool {
		if(!isset($this->toolsByName[$name])) {
			throw new \InvalidArgumentException('Unknown MCP tool: ' . $name);
		}

		return $this->toolsByName[$name];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getDefinition(string $name): array {
		if(!isset($this->definitionsByName[$name])) {
			throw new \InvalidArgumentException('Unknown MCP tool definition: ' . $name);
		}

		return $this->definitionsByName[$name];
	}

	/**
	 * @param IAgentTool[] $tools
	 */
	private function build(array $tools): void {
		foreach($tools as $tool) {
			if(!$tool instanceof IAgentTool) {
				continue;
			}

			$outputSchemas = $this->getOutputSchemas($tool);

			foreach($tool->getToolDefinitions() as $definition) {
				if(!is_array($definition)) {
					continue;
				}

				$name = $this->getToolName($definition);

				if($name === '') {
					continue;
				}

				if(isset($this->toolsByName[$name])) {
					$this->logger->logLevel(ILogger::WARNING, 'Duplicate MCP tool name. Later tool wins.', [
						'scope' => self::LOG_SCOPE,
						'tool' => $name
					]);
				}

				$definition = $this->withOutputSchema($definition, $outputSchemas[$name] ?? []);

				$this->definitions[] = $definition;
				$this->definitionsByName[$name] = $definition;
				$this->toolsByName[$name] = $tool;
			}
		}
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function getOutputSchemas(IAgentTool $tool): array {
		if(!$tool instanceof IOutputSchemaProvider) {
			return [];
		}

		$schemas = [];

		foreach($tool->getOutputSchemas() as $name => $schema) {
			$name = trim((string)$name);

			if($name === '' || !is_array($schema)) {
				continue;
			}

			$schemas[$name] = $schema;
		}

		return $schemas;
	}

	/**
	 * @param array<string,mixed> $definition
	 * @param array<string,mixed> $outputSchema
	 * @return array<string,mixed>
	 */
	private function withOutputSchema(array $definition, array $outputSchema): array {
		if($outputSchema === []) {
			return $definition;
		}

		if(isset($definition['outputSchema']) && is_array($definition['outputSchema'])) {
			return $definition;
		}

		if(isset($definition['function']) && is_array($definition['function'])) {
			if(isset($definition['function']['outputSchema']) && is_array($definition['function']['outputSchema'])) {
				return $definition;
			}

			$definition['function']['outputSchema'] = $outputSchema;
			return $definition;
		}

		$definition['outputSchema'] = $outputSchema;

		return $definition;
	}

	/**
	 * @param array<string,mixed> $definition
	 */
	private function getToolName(array $definition): string {
		if(isset($definition['function']) && is_array($definition['function'])) {
			return trim((string)($definition['function']['name'] ?? ''));
		}

		return trim((string)($definition['name'] ?? ''));
	}
}

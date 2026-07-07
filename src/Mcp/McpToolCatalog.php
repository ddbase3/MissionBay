<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
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
		if(!isset($this->toolsByName[$name])) {
			throw new \InvalidArgumentException('Unknown MCP tool: ' . $name);
		}

		return $this->toolsByName[$name]->callTool($name, $arguments, $context);
	}

	/**
	 * @param IAgentTool[] $tools
	 */
	private function build(array $tools): void {
		foreach($tools as $tool) {
			if(!$tool instanceof IAgentTool) {
				continue;
			}

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

				$this->definitions[] = $definition;
				$this->toolsByName[$name] = $tool;
			}
		}
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

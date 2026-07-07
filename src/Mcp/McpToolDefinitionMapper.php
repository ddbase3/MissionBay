<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

/**
 * McpToolDefinitionMapper
 *
 * Converts MissionBay/OpenAI-style tool definitions to MCP tool definitions.
 */
class McpToolDefinitionMapper {

	public static function getName(): string {
		return 'mcptooldefinitionmapper';
	}

	/**
	 * @param array<string,mixed> $definition
	 * @return array<string,mixed>
	 */
	public function toMcpTool(array $definition): array {
		$function = is_array($definition['function'] ?? null) ? $definition['function'] : $definition;
		$name = trim((string)($function['name'] ?? $definition['name'] ?? ''));
		$description = trim((string)($function['description'] ?? $definition['description'] ?? ''));
		$inputSchema = is_array($function['parameters'] ?? null) ? $function['parameters'] : [];

		if($inputSchema === []) {
			$inputSchema = [
				'type' => 'object',
				'properties' => new \stdClass()
			];
		}

		$tool = [
			'name' => $name,
			'description' => $description,
			'inputSchema' => $inputSchema
		];

		$label = trim((string)($definition['label'] ?? ''));

		if($label !== '') {
			$tool['title'] = $label;
		}

		return $tool;
	}
}

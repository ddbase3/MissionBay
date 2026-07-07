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
		$outputSchema = $this->getOutputSchema($definition, $function);

		if($label !== '') {
			$tool['title'] = $label;
		}

		if($outputSchema !== []) {
			$tool['outputSchema'] = $outputSchema;
		}

		$annotations = $this->getAnnotations($definition, $function);

		if($annotations !== []) {
			$tool['annotations'] = $annotations;
		}

		return $tool;
	}

	/**
	 * @param array<string,mixed> $definition
	 * @param array<string,mixed> $function
	 * @return array<string,mixed>
	 */
	private function getOutputSchema(array $definition, array $function): array {
		if(is_array($definition['outputSchema'] ?? null)) {
			return $definition['outputSchema'];
		}

		if(is_array($function['outputSchema'] ?? null)) {
			return $function['outputSchema'];
		}

		return [];
	}

	/**
	 * @param array<string,mixed> $definition
	 * @param array<string,mixed> $function
	 * @return array<string,mixed>
	 */
	private function getAnnotations(array $definition, array $function): array {
		if(is_array($definition['annotations'] ?? null)) {
			return $definition['annotations'];
		}

		if(is_array($function['annotations'] ?? null)) {
			return $function['annotations'];
		}

		return [];
	}
}

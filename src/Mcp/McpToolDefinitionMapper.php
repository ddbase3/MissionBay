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

	/** @var array<int,string> */
	private const MCP_ANNOTATION_KEYS = [
		'readOnlyHint',
		'destructiveHint',
		'idempotentHint',
		'openWorldHint'
	];

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

		$tool['annotations'] = $this->getAnnotations($definition, $function);

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
		$annotations = is_array($definition['annotations'] ?? null) ? $definition['annotations'] : [];

		if(is_array($function['annotations'] ?? null)) {
			$annotations = array_merge($annotations, $function['annotations']);
		}

		foreach(self::MCP_ANNOTATION_KEYS as $key) {
			if(array_key_exists($key, $definition) && is_bool($definition[$key])) {
				$annotations[$key] = $definition[$key];
			}

			if(array_key_exists($key, $function) && is_bool($function[$key])) {
				$annotations[$key] = $function[$key];
			}
		}

		$readOnly = ($annotations['readOnlyHint'] ?? false) === true;
		$annotations['readOnlyHint'] = $readOnly;
		$annotations['destructiveHint'] = $this->boolAnnotation(
			$annotations,
			'destructiveHint',
			!$readOnly
		);
		$annotations['idempotentHint'] = $this->boolAnnotation(
			$annotations,
			'idempotentHint',
			$readOnly
		);
		$annotations['openWorldHint'] = $this->boolAnnotation(
			$annotations,
			'openWorldHint',
			true
		);

		return $annotations;
	}

	/** @param array<string,mixed> $annotations */
	private function boolAnnotation(array $annotations, string $key, bool $default): bool {
		return is_bool($annotations[$key] ?? null) ? $annotations[$key] : $default;
	}
}

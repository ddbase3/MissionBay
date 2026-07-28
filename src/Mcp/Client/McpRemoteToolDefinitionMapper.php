<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp\Client;

/**
 * Converts one remote MCP tool definition into the normal MissionBay tool
 * definition contract.
 *
 * Remote names are preserved. ConfiguredAgentToolResource owns the external
 * preset prefix and translates effective names back before execution.
 */
final class McpRemoteToolDefinitionMapper {

	private const HINT_DEFAULTS = [
		'readOnlyHint' => false,
		'destructiveHint' => true,
		'idempotentHint' => false,
		'openWorldHint' => true
	];

	/**
	 * @param array<string,mixed> $tool
	 * @return array<string,mixed>
	 */
	public function toAgentTool(array $tool): array {
		$remoteName = trim((string)($tool['name'] ?? ''));

		if($remoteName === '') {
			throw new \InvalidArgumentException('Remote MCP tool definition has no valid name.');
		}

		$remoteAnnotations = is_array($tool['annotations'] ?? null) ? $tool['annotations'] : [];
		$safety = $this->normalizeSafetyHints($remoteAnnotations);
		$readOnly = $safety['complete']
			&& !$safety['contradictory']
			&& $safety['hints']['readOnlyHint'] === true;
		$mutation = !$readOnly;
		$inputSchema = is_array($tool['inputSchema'] ?? null) ? $tool['inputSchema'] : [];

		if($inputSchema === []) {
			$inputSchema = [
				'type' => 'object',
				'properties' => new \stdClass()
			];
		}

		$title = trim((string)($tool['title'] ?? $remoteAnnotations['title'] ?? $remoteName));
		$description = trim((string)($tool['description'] ?? ''));
		$annotations = array_merge($remoteAnnotations, $safety['hints'], [
			'requiresApproval' => $mutation,
			'commitGuardRequired' => false,
			'sideEffectHint' => $mutation,
			'mcpHintsComplete' => $safety['complete'],
			'mcpHintsContradictory' => $safety['contradictory'],
			'mcpMissingHints' => $safety['missing'],
			'riskHint' => $safety['risk']
		]);
		$definition = [
			'type' => 'function',
			'label' => $title !== '' ? $title : $remoteName,
			'category' => 'integration',
			'tags' => ['mcp', 'remote'],
			'priority' => 50,
			'readOnlyHint' => $readOnly,
			'mutation' => $mutation,
			'requiresApproval' => $mutation,
			'commitGuardRequired' => false,
			'sideEffectHint' => $mutation,
			'destructiveHint' => $safety['hints']['destructiveHint'],
			'idempotentHint' => $safety['hints']['idempotentHint'],
			'openWorldHint' => $safety['hints']['openWorldHint'],
			'annotations' => $annotations,
			'function' => [
				'name' => $remoteName,
				'description' => $description !== '' ? $description : ('Remote MCP tool ' . $remoteName . '.'),
				'parameters' => $inputSchema
			]
		];

		if(is_array($tool['outputSchema'] ?? null)) {
			$definition['outputSchema'] = $tool['outputSchema'];
			$definition['function']['outputSchema'] = $tool['outputSchema'];
		}

		return $definition;
	}

	/**
	 * @param array<string,mixed> $annotations
	 * @return array{hints:array<string,bool>,missing:array<int,string>,complete:bool,contradictory:bool,risk:string}
	 */
	private function normalizeSafetyHints(array $annotations): array {
		$hints = [];
		$missing = [];

		foreach(self::HINT_DEFAULTS as $name => $default) {
			$value = $annotations[$name] ?? null;
			if(!array_key_exists($name, $annotations) || !is_bool($value)) {
				$missing[] = $name;
				$hints[$name] = $default;
				continue;
			}

			$hints[$name] = $value;
		}

		$complete = $missing === [];
		$contradictory = $hints['readOnlyHint'] === true && $hints['destructiveHint'] === true;
		$risk = 'high';

		if($complete && !$contradictory) {
			if($hints['readOnlyHint'] === true) {
				$risk = $hints['openWorldHint'] === true ? 'medium' : 'low';
			}
			elseif(
				$hints['destructiveHint'] === false
				&& $hints['idempotentHint'] === true
				&& $hints['openWorldHint'] === false
			) {
				$risk = 'medium';
			}
		}

		return [
			'hints' => $hints,
			'missing' => $missing,
			'complete' => $complete,
			'contradictory' => $contradictory,
			'risk' => $risk
		];
	}
}

<?php declare(strict_types=1);

namespace Base3\MissionBay\Orchestrator\Program;

use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use Base3\MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;

/** Renders the existing active contracts for code generation, not a tool registry. */
final class ToolProgramDefinitionService {

	public const TOOL_NAME = 'missionbay_execute_program';

	public function __construct(
		private readonly AgentToolContractValidationService $contracts = new AgentToolContractValidationService(),
		private readonly AgentToolDefinitionSemantics $semantics = new AgentToolDefinitionSemantics()
	) {}

	/** @return array<string,array{input:mixed,output:mixed,fingerprint:string}> */
	public function getContracts(array $definitions, array $tools): array {
		$names = [];
		foreach ($definitions as $definition) {
			$name = $this->semantics->getToolName($definition);
			if ($name !== '' && $name !== self::TOOL_NAME) { $names[] = $name; }
		}
		return $this->contracts->getProgramContracts($names, $tools);
	}

	/** Bounds the serialized active definitions plus program instruction, including output contracts. */
	public function fitsModelBudget(array $definitions, array $contracts, AgentCapabilitySelectionConfig $config): bool {
		return strlen(json_encode($definitions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
			+ strlen($this->getInstruction($contracts, true)) <= $config->getSemanticMaxPromptCharacters();
	}

	public function getDefinition(): array {
		return [
			'type' => 'function',
			'function' => [
				'name' => self::TOOL_NAME,
				'description' => 'Execute one bounded program over the active tool functions, or explore the eligible function catalog when instructed. The orchestrator handles approval and resume; never ask for action approval in chat. Each concrete call remains subject to normal host authorization. Return a compact structured summary.',
				'parameters' => [
					'type' => 'object',
					'properties' => ['program' => ['type' => 'string', 'maxLength' => ToolProgram::MAX_SOURCE_BYTES]],
					'required' => ['program'],
					'additionalProperties' => false
				]
			]
		];
	}

	public function getInstruction(array $contracts, bool $catalogAvailable = false): string {
		$outputs = [];
		foreach ($contracts as $name => $contract) { $outputs[$name] = $contract['output']; }
		return implode("\n", [
			'<BASE3-TOOL-PROGRAM>',
			'Prefer ' . self::TOOL_NAME . ' for coherent groups of dependent calls, loops and local processing. Submit one program alone per decision. Ordinary calls remain available.',
			'Restricted JavaScript, not full JS. Use let x = expression; x = expression; if (condition) { ... } else { ... } for (let item of array) { ... } for (let i = 0; i < array.length; i++) { ... } break; continue; return expression;. Every path must return a JSON value. Braces and statement semicolons required. Local statement/loop updates: ++ -- += -= *= /= %=.',
			'JSON literals with explicit object keys, property/index reads, array.length, arrayVariable.push(value);, array.join(separator), array.includes(scalar), array.find(x => x.id === id), array.map(x => x.id), array.filter(x => x.active), array.some(x => x.active), array.every(x => x.active). Callbacks: pure single expressions, x or (x, index), no tool/catalog calls; use loops for those. find returns null if unmatched. Use JSON.stringify(value) for JSON serialization and `Count: ${count}` for scalar interpolation. arrayVariable.sort() sorts local scalar arrays by UTF-8 scalar text (no comparator, not in callbacks). No arbitrary methods, property writes, standalone functions, imports, host code or dynamic tool names.',
			'Numeric + - * / %, string/finite-number concatenation with +, === !== < <= > >=, ! && ||, condition ? whenTrue : whenFalse. JSON truthiness; && and || short-circuit and return the chosen operand. Missing properties are errors, not undefined. join uses strings/finite numbers and a string separator (default comma).',
			'HITL is solely the orchestrator\'s responsibility. Never ask for action approval in chat or implement approval logic in code. Submit the requested action when arguments are known; the host pauses for approval and resumes the same program. Respect denial; never retry denied mutations. Ask only for missing task input.',
			'Inside programs, call tools.NAME({key: value}) or tools["exact-name"]({key: value}) synchronously, without await. Tool and catalog result: {call_id, tool, arguments, status, output, error_code, error_message, metadata}. Check r.status === "success" (technical), then r.output for the domain outcome. Domain ok/items/result fields, if declared, are inside output. Do not confuse this with the parent program observation (status, result, execution). Use registered schemas and exact names in the output schema map, even in dead branches. For nested capabilities, search then describe their actual contracts; never invent names or fields. Syntax/contract errors do not prove unavailability or denial.',
			'Limits: 100 tool calls, 10000 instructions, 2 MiB state, 8 MiB cumulative tool results, 64 KiB return, 256 MiB state processing. Return compact evidence. Failed programs may have committed actions; inspect results before repair, do not replay committed actions.',
			...($catalogAvailable ? [
				'Explore the eligible catalog in a separate program. let r = catalog.search({query: "keywords", limit: 8}); return r.output; output is [{name, title, description, category, source_id}]. Query 1-512 bytes, limit 1-25, optional offset within catalog size; limit optional. Search does not change selection.',
				'let s = catalog.select({names: ["exact_name"]}); return s.output; output.selected_tool_names is the selected list. Selection replaces the active set only on successful return, retaining mandatory functions. Next decision gets selected contracts. Invalid catalog operations abort. Max 20 operations; never mix catalog and domain calls. Discovery is not authorization.'
			] : []),
			'Available program output schemas (native inputs are already registered):',
			json_encode((object)$outputs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'</BASE3-TOOL-PROGRAM>'
		]);
	}
}

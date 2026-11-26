<?php declare(strict_types=1);

namespace Base3\MissionBay\Orchestrator\Program;

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
		$result = [];
		foreach ($definitions as $definition) {
			$name = $this->semantics->getToolName($definition);
			if ($name === '' || $name === self::TOOL_NAME) { continue; }
			$contract = $this->contracts->getProgramContract($name, $tools);
			if ($contract !== null) { $result[$name] = $contract; }
		}
		return $result;
	}

	public function getDefinition(): array {
		return [
			'type' => 'function',
			'function' => [
				'name' => self::TOOL_NAME,
				'description' => 'Execute one bounded program over the current selected tool functions. Each concrete call is subject to normal host authorization and approval. Return a compact structured summary.',
				'parameters' => [
					'type' => 'object',
					'properties' => ['program' => ['type' => 'string', 'maxLength' => ToolProgram::MAX_SOURCE_BYTES]],
					'required' => ['program'],
					'additionalProperties' => false
				]
			]
		];
	}

	public function getInstruction(array $contracts): string {
		$outputs = [];
		foreach ($contracts as $name => $contract) { $outputs[$name] = $contract['output']; }
		return implode("\n", [
			'<BASE3-TOOL-PROGRAM>',
			'Plan the complete next coherent part of the user task, not just the next call. Select all capability sources required by that program using the normal source selection function first.',
			'Prefer ' . self::TOOL_NAME . ' for multiple dependent calls, loops and data processing. Submit only one program call per decision; do not mix it with ordinary tool calls or source selection. Normal individual calls remain available.',
			'Programs use this restricted JavaScript syntax: let x = expression; x = expression; if (boolean) { ... } else { ... } for (let x of array) { ... } break; continue; return expression;. Use semicolons after statements, not after blocks. Braces are required. Arrays, object literals with explicit keys, scalar literals, variable access, property and index reads, array.length, arrayVariable.push(value);, numeric + - * / %, string concatenation with + (string + string, string + finite number, finite number + string; numbers use JSON number notation; no other implicit conversions), === !== < <= > >=, boolean ! && || are allowed. Conditions must be booleans. No other syntax, imports, host functions, method calls, network, nested programs or dynamic tool names.',
			'Call tools.NAME({key: value}) or tools["exact-name"]({key: value}). The native function parameter schemas are the input contracts. Every concrete call returns {call_id, tool, arguments, status, output, error_code, error_message, metadata}. status is success or failure. Check status before accessing output. A successful call is not necessarily a successful domain operation; check the returned domain fields. Never retry a denied or failed mutation blindly.',
			'Only the functions listed in the following output schema map can be called inside a program. Other registered functions remain available as ordinary calls. All named functions, even in conditional branches, must be selected before execution. Do not guess tool names or result fields.',
			'Use at most 100 tool calls and 10000 VM instructions; keep local state below 2 MiB, cumulative tool results below 8 MiB and return value below 64 KiB. Cumulative state processing is bounded to 256 MiB. Return concise counts, outcomes and relevant evidence. A program failure may include already committed actions; do not repeat them when repairing or continuing the task.',
			'Available program output schemas (native inputs are already registered):',
			json_encode((object)$outputs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'</BASE3-TOOL-PROGRAM>'
		]);
	}
}

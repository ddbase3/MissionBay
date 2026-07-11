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

namespace MissionBay\Orchestrator\Service;

use AssistantFoundation\Dto\AgentToolContractValidation;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Api\IOutputSchemaProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Orchestrator\Validation\JsonSchemaValidator;

/**
 * AgentToolContractValidationService
 *
 * Resolves the declared input/output schema for one concrete tool function and
 * validates provider-neutral call arguments or runtime output. This mechanism
 * belongs inside action policy and tool execution; it is not a separately
 * reorderable agent stage.
 */
final class AgentToolContractValidationService {

	public function __construct(
		private readonly JsonSchemaValidator $validator = new JsonSchemaValidator()
	) {}

	/**
	 * @param array<int,mixed> $tools
	 */
	public function validateInput(AiToolCall $call, array $tools): AgentToolContractValidation {
		return $this->validate(
			$call,
			$call->getArguments(),
			$tools,
			AgentToolContractValidation::DIRECTION_INPUT
		);
	}

	/**
	 * @param array<int,mixed> $tools
	 */
	public function validateOutput(AiToolCall $call, mixed $output, array $tools): AgentToolContractValidation {
		return $this->validate(
			$call,
			$output,
			$tools,
			AgentToolContractValidation::DIRECTION_OUTPUT
		);
	}

	/**
	 * @param array<int,mixed> $tools
	 */
	private function validate(
		AiToolCall $call,
		mixed $value,
		array $tools,
		string $direction
	): AgentToolContractValidation {
		$resolved = $this->resolveDefinition($call->getName(), $tools);
		$prefix = $direction === AgentToolContractValidation::DIRECTION_INPUT ? 'tool_input' : 'tool_output';

		if (!$resolved['tool'] instanceof IAgentTool) {
			return new AgentToolContractValidation(
				callId: $call->getId(),
				toolName: $call->getName(),
				direction: $direction,
				status: AgentToolContractValidation::STATUS_NOT_DECLARED,
				reasonCode: $prefix . '_contract_not_declared',
				summary: 'No executable tool definition was found for contract validation.',
				metadata: [
					'tool_found' => false,
					'definition_error' => $resolved['error']
				]
			);
		}

		$contract = $this->resolveSchema(
			$resolved['tool'],
			$resolved['definition'],
			$call->getName(),
			$direction
		);

		if (!$contract['declared']) {
			return new AgentToolContractValidation(
				callId: $call->getId(),
				toolName: $call->getName(),
				direction: $direction,
				status: AgentToolContractValidation::STATUS_NOT_DECLARED,
				reasonCode: $prefix . '_contract_not_declared',
				summary: ucfirst($direction) . ' schema is not declared for this tool.',
				metadata: [
					'tool_found' => true,
					'tool_implementation' => $resolved['tool']::class
				]
			);
		}

		$validation = $this->validator->validate($value, $contract['schema']);
		if (!$validation['valid']) {
			$reasonCode = $validation['schema_valid']
				? $prefix . '_contract_violation'
				: $prefix . '_schema_invalid';

			return new AgentToolContractValidation(
				callId: $call->getId(),
				toolName: $call->getName(),
				direction: $direction,
				status: AgentToolContractValidation::STATUS_INVALID,
				reasonCode: $reasonCode,
				summary: $validation['schema_valid']
					? ucfirst($direction) . ' does not satisfy the declared tool contract.'
					: ucfirst($direction) . ' could not be validated because the declared tool schema is invalid.',
				schemaSource: $contract['source'],
				issues: $validation['issues'],
				metadata: [
					'tool_found' => true,
					'tool_implementation' => $resolved['tool']::class,
					'schema_valid' => $validation['schema_valid'],
					'issue_count' => count($validation['issues'])
				]
			);
		}

		return new AgentToolContractValidation(
			callId: $call->getId(),
			toolName: $call->getName(),
			direction: $direction,
			status: AgentToolContractValidation::STATUS_VALID,
			reasonCode: '',
			summary: ucfirst($direction) . ' satisfies the declared tool contract.',
			schemaSource: $contract['source'],
			metadata: [
				'tool_found' => true,
				'tool_implementation' => $resolved['tool']::class,
				'schema_valid' => true,
				'issue_count' => 0
			]
		);
	}

	/**
	 * @param array<int,mixed> $tools
	 * @return array{tool:?IAgentTool,definition:array<string,mixed>,error:string}
	 */
	private function resolveDefinition(string $name, array $tools): array {
		$lastError = '';

		foreach ($tools as $tool) {
			if (!$tool instanceof IAgentTool) {
				continue;
			}

			try {
				$definitions = $tool->getToolDefinitions();
			} catch (\Throwable $e) {
				$lastError = 'Tool definitions could not be read: ' . $e->getMessage();
				continue;
			}

			foreach ($definitions as $definition) {
				if (!is_array($definition)) {
					continue;
				}

				if ($this->definitionName($definition) === $name) {
					return [
						'tool' => $tool,
						'definition' => $definition,
						'error' => ''
					];
				}
			}
		}

		return [
			'tool' => null,
			'definition' => [],
			'error' => $lastError
		];
	}

	/**
	 * @param array<string,mixed> $definition
	 * @return array{declared:bool,schema:mixed,source:string}
	 */
	private function resolveSchema(
		IAgentTool $tool,
		array $definition,
		string $toolName,
		string $direction
	): array {
		$function = is_array($definition['function'] ?? null)
			? $definition['function']
			: $definition;

		if ($direction === AgentToolContractValidation::DIRECTION_INPUT) {
			foreach ([
				['container' => $function, 'key' => 'parameters', 'source' => 'function.parameters'],
				['container' => $function, 'key' => 'inputSchema', 'source' => 'function.inputSchema'],
				['container' => $definition, 'key' => 'inputSchema', 'source' => 'inputSchema'],
				['container' => $definition, 'key' => 'parameters', 'source' => 'parameters']
			] as $candidate) {
				if (array_key_exists($candidate['key'], $candidate['container'])) {
					return [
						'declared' => true,
						'schema' => $candidate['container'][$candidate['key']],
						'source' => $candidate['source']
					];
				}
			}

			return ['declared' => false, 'schema' => null, 'source' => ''];
		}

		foreach ([
			['container' => $definition, 'key' => 'outputSchema', 'source' => 'outputSchema'],
			['container' => $function, 'key' => 'outputSchema', 'source' => 'function.outputSchema']
		] as $candidate) {
			if (array_key_exists($candidate['key'], $candidate['container'])) {
				return [
					'declared' => true,
					'schema' => $candidate['container'][$candidate['key']],
					'source' => $candidate['source']
				];
			}
		}

		if ($tool instanceof IOutputSchemaProvider) {
			try {
				$schemas = $tool->getOutputSchemas();
			} catch (\Throwable $e) {
				return [
					'declared' => true,
					'schema' => 'Invalid output schema provider: ' . $e->getMessage(),
					'source' => IOutputSchemaProvider::class
				];
			}

			if (is_array($schemas) && array_key_exists($toolName, $schemas)) {
				return [
					'declared' => true,
					'schema' => $schemas[$toolName],
					'source' => IOutputSchemaProvider::class . '::getOutputSchemas'
				];
			}
		}

		return ['declared' => false, 'schema' => null, 'source' => ''];
	}

	/**
	 * @param array<string,mixed> $definition
	 */
	private function definitionName(array $definition): string {
		if (is_array($definition['function'] ?? null)) {
			return trim((string)($definition['function']['name'] ?? ''));
		}

		return trim((string)($definition['name'] ?? ''));
	}
}

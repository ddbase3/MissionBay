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

namespace MissionBay\Service;

use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Api\IAgentRuntimeService;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentExecutionResult;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentStateContext;

/**
 * MissionBay implementation of the transport-neutral agent execution service.
 */
final class AgentExecutionService implements IAgentRuntimeService {

	private const DEFAULT_ASSISTANT_NODE_ID = 'assistant';
	private const ASSISTANT_NODE_TYPE = 'aiassistantnode';
	private const CANONICAL_USER_INPUT = 'prompt';
	private const LEGACY_USER_INPUT = 'user';
	private const CANONICAL_RESUME_INPUT = 'resume';
	private const CANONICAL_MODE_INPUT = 'mode';

	public function __construct(
		private readonly IAgentContextFactory $contextFactory,
		private readonly IAgentFlowFactory $flowFactory,
		private readonly IAgentFlowCompiler $flowCompiler
	) {}

	public static function getName(): string {
		return 'agentexecutionservice';
	}

	public static function getRuntimeId(): string {
		return 'missionbay';
	}

	public static function getRuntimeLabel(): string {
		return 'MissionBay';
	}

	public static function getRuntimeDescription(): string {
		return 'Executes declarative MissionBay agent flows, profiles, memory and tools.';
	}

	public static function getDefaultPriority(): int {
		return 100;
	}

	public function execute(
		AgentExecutionRequest $request,
		?IAgentEventSink $eventSink = null
	): AgentExecutionResult {
		$agentConfiguration = $request->getAgentConfiguration();
		$inputs = $this->normalizeInputs($request->getInputs());
		$compilation = $this->flowCompiler->compile($agentConfiguration);
		[$effectiveFlow, $executionWarnings] = $this->prepareFlowForInputs(
			$compilation->getFlow(),
			$agentConfiguration,
			$inputs
		);
		[$flow, $context] = $this->createFlow($effectiveFlow, $request->getContext(), $eventSink);
		$output = $flow->run($inputs);
		$agentResult = $context instanceof IAgentStateContext ? $context->getResult() : null;

		return new AgentExecutionResult(
			$output,
			array_values(array_unique(array_merge($compilation->getWarnings(), $executionWarnings))),
			$agentResult
		);
	}

	/**
	 * @param array<string,mixed> $effectiveFlow
	 * @param array<string,mixed> $contextVars
	 * @return array{0:mixed,1:\AssistantFoundation\Api\IAgentContext}
	 */
	private function createFlow(
		array $effectiveFlow,
		array $contextVars,
		?IAgentEventSink $eventSink
	): array {
		$context = $this->contextFactory->createContext();

		foreach ($contextVars as $key => $value) {
			if (!is_string($key) && !is_int($key)) {
				continue;
			}
			$key = trim((string)$key);
			if ($key === '') {
				continue;
			}
			$context->setVar($key, $value);
		}

		if ($eventSink !== null) {
			$context->setVar(IAgentEventSink::CONTEXT_KEY, $eventSink);
		}

		return [
			$this->flowFactory->createFromArray('strictflow', $effectiveFlow, $context),
			$context
		];
	}

	/** @return array<string,mixed> */
	private function normalizeInputs(array $inputs): array {
		if (!array_key_exists(self::CANONICAL_USER_INPUT, $inputs) && array_key_exists(self::LEGACY_USER_INPUT, $inputs)) {
			$inputs[self::CANONICAL_USER_INPUT] = $inputs[self::LEGACY_USER_INPUT];
		}
		unset($inputs[self::LEGACY_USER_INPUT]);
		return $inputs;
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $agentConfiguration
	 * @param array<string,mixed> $inputs
	 * @return array{0:array<string,mixed>,1:array<int,string>}
	 */
	private function prepareFlowForInputs(array $flow, array $agentConfiguration, array $inputs): array {
		$assistantNodeId = $this->normalizeAssistantNodeId(
			$agentConfiguration['agent_components_assistant_node'] ?? self::DEFAULT_ASSISTANT_NODE_ID
		);
		$warnings = [];

		if (array_key_exists(self::CANONICAL_MODE_INPUT, $inputs)) {
			[$flow, $modeWarnings] = $this->ensureAssistantInputConnection(
				$flow,
				$assistantNodeId,
				self::CANONICAL_MODE_INPUT
			);
			$warnings = array_merge($warnings, $modeWarnings);
		}

		if (array_key_exists(self::CANONICAL_RESUME_INPUT, $inputs)) {
			[$flow, $resumeWarnings] = $this->ensureAssistantInputConnection(
				$flow,
				$assistantNodeId,
				self::CANONICAL_RESUME_INPUT
			);
			$warnings = array_merge($warnings, $resumeWarnings);
		}

		return [$flow, array_values(array_unique($warnings))];
	}

	/**
	 * @param array<string,mixed> $flow
	 * @return array{0:array<string,mixed>,1:array<int,string>}
	 */
	private function ensureAssistantInputConnection(
		array $flow,
		string $assistantNodeId,
		string $inputName
	): array {
		$nodeIndex = $this->findAssistantNodeIndex($flow, $assistantNodeId);
		if ($nodeIndex === null || !isset($flow['nodes'][$nodeIndex]) || !is_array($flow['nodes'][$nodeIndex])) {
			return [$flow, ['Assistant node not found for input connection: ' . $inputName]];
		}

		$targetNodeId = trim((string)($flow['nodes'][$nodeIndex]['id'] ?? ''));
		if ($targetNodeId === '') {
			return [$flow, ['Assistant node has no id for input connection: ' . $inputName]];
		}

		if (!isset($flow['connections']) || !is_array($flow['connections'])) {
			$flow['connections'] = [];
		}

		foreach ($flow['connections'] as $connection) {
			if (!is_array($connection)) {
				continue;
			}
			if (
				(string)($connection['from'] ?? '') === '__input__'
				&& (string)($connection['output'] ?? '') === $inputName
				&& (string)($connection['to'] ?? '') === $targetNodeId
				&& (string)($connection['input'] ?? '') === $inputName
			) {
				return [$flow, []];
			}
		}

		$flow['connections'][] = [
			'from' => '__input__',
			'output' => $inputName,
			'to' => $targetNodeId,
			'input' => $inputName
		];

		return [$flow, []];
	}

	/** @param array<string,mixed> $flow */
	private function findAssistantNodeIndex(array $flow, string $assistantNodeId): ?int {
		$fallback = null;
		foreach ($flow['nodes'] ?? [] as $index => $node) {
			if (!is_array($node)) {
				continue;
			}
			if ((string)($node['id'] ?? '') === $assistantNodeId) {
				return (int)$index;
			}
			if ($fallback === null && (string)($node['type'] ?? '') === self::ASSISTANT_NODE_TYPE) {
				$fallback = (int)$index;
			}
		}
		return $fallback;
	}

	private function normalizeAssistantNodeId(mixed $value): string {
		$nodeId = trim((string)$value);
		return $nodeId !== '' ? $nodeId : self::DEFAULT_ASSISTANT_NODE_ID;
	}
}

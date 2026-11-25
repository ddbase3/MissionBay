<?php declare(strict_types=1);

namespace Base3\MissionBay\Orchestrator\Program;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySourceCatalogService;
use Base3\AssistantFoundation\Dto\AgentStageResult;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Event\MissionBayToolFailedEvent;
use Base3\MissionBay\Event\MissionBayToolFinishedEvent;
use Base3\MissionBay\Event\MissionBayToolStartedEvent;
use Base3\MissionBay\Orchestrator\AgentStageResultAccumulator;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;

/** Drives the VM through the existing policy and execution stages. It never invokes a domain tool. */
final class ToolProgramExecutionService {

	public function __construct(
		private readonly IEventManager $eventManager,
		private readonly ToolProgramDefinitionService $definitions,
		private readonly ToolProgramInterpreter $interpreter = new ToolProgramInterpreter()
	) {}

	public static function enabled(IAgentContext $context): bool {
		$config = $context->getVar(Keys::MODEL_DECISION_CONFIG);
		return $config instanceof AgentModelDecisionConfig && $config->isProgramEnabled();
	}

	public static function hasWork(IAgentContext $context): bool {
		if ((array)$context->getVar(Keys::PROGRAM_STATE) !== []) { return true; }
		if (!self::enabled($context) || $context->getVar(Keys::PHASE) !== Keys::PHASE_TOOLS) { return false; }
		foreach ((array)$context->getVar(Keys::PENDING_TOOL_CALLS) as $call) {
			if ($call instanceof AiToolCall && $call->getName() === ToolProgramDefinitionService::TOOL_NAME) { return true; }
		}
		return false;
	}

	/** Adds correlation only for the active, server-generated concrete call. */
	public static function trace(IAgentContext $context, array $trace, string $callId, array $metadata): array {
		$program = (array)$context->getVar(Keys::PROGRAM_STATE);
		$parentId = $program['call']['id'] ?? null;
		$step = $metadata['program_step'] ?? null;
		if (is_string($parentId) && $parentId !== '' && ($metadata['program_call_id'] ?? null) === $parentId
			&& is_int($step) && $step > 0 && $callId === 'program-' . hash('sha256', $parentId . ':' . $step)) {
			$trace['program_call_id'] = $parentId;
			$trace['program_step'] = $step;
		}
		return $trace;
	}

	/** @param callable():AgentStageResult $execute The same configured tool-execution stage's ordinary process. */
	public function process(IAgentContext $context, IAgentStage $policy, callable $execute, callable $synchronize): AgentStageResult {
		$patch = new AgentStageResultAccumulator($context);
		$snapshot = (array)$context->getVar(Keys::PROGRAM_STATE);
		$state = new ToolProgramState();
		$call = null;
		$inStage = false;
		try {
			if ($snapshot === []) {
				$calls = (array)$context->getVar(Keys::PENDING_TOOL_CALLS);
				if (count($calls) !== 1) { return $this->rejectMixed($context, $patch, $calls); }
				$call = reset($calls);
				if (!$call instanceof AiToolCall || $call->getName() !== ToolProgramDefinitionService::TOOL_NAME || trim($call->getId()) === '') {
					throw new \LogicException('Invalid program call identity.');
				}
				$index = (int)$context->getVar(Keys::CALL_INDEX) + 1;
				$snapshot = ['version' => ToolProgram::VERSION, 'call' => $call->toArray(), 'call_index' => $index];
				$patch->apply(AgentStageResult::patch([Keys::CALL_INDEX => $index]));
				$this->emit($context, $call, $index);
			} else {
				$call = AiToolCall::fromArray($snapshot['call']);
				$state = ToolProgramState::fromJson($snapshot['vm']);
				if (($snapshot['version'] ?? null) !== ToolProgram::VERSION) { throw new \RuntimeException('Program language version changed before resume.'); }
			}
			if (!self::enabled($context) || $context->getVar(Keys::CAPABILITY_SELECTION_APPLIED) !== true) {
				throw new \RuntimeException('Program execution requires the enabled profile and an active capability selection.');
			}
			$args = $call->getArguments();
			if (array_keys($args) !== ['program'] || !is_string($args['program'])) { throw new \RuntimeException('Expected exactly one program string.'); }
			$program = (new ToolProgramParser())->parse($args['program']);
			$contracts = $this->definitions->getContracts((array)$context->getVar(Keys::TOOL_DEFINITIONS), (array)$context->getVar(Keys::TOOLS));
			$fingerprints = [];
			$selected = (array)$context->getVar(Keys::SELECTED_TOOL_NAMES);
			// Recheck the original selection against the current catalog and hard filters on resume.
			if (isset($snapshot['fingerprints'])) {
				$config = $context->getVar(Keys::CAPABILITY_SELECTION_CONFIG);
				$catalog = $context->getVar(Keys::CAPABILITY_CATALOG);
				if (!$config instanceof AgentCapabilitySelectionConfig) { $config = new AgentCapabilitySelectionConfig(); }
				$required = (array)$context->getVar(Keys::REQUIRED_TOOL_NAMES);
				$sourceService = new AgentCapabilitySourceCatalogService();
				$sources = $sourceService->buildSources($catalog, (array)$context->getVar(Keys::TOOLS), $config, $required);
				$eligible = [];
				foreach ($sources as $source) {
					foreach ($source['capabilities'] as $capability) { $eligible[$capability->getName()] = true; }
				}
				if (count($selected) > $config->getMaxTools() || array_diff($selected, array_keys($eligible)) !== []) {
					throw new \RuntimeException('Program capability selection is no longer eligible.');
				}
			}
			foreach ($program->toolNames as $name) {
				if (!in_array($name, $selected, true) || !isset($contracts[$name])) {
					throw new \RuntimeException('Program function is not selected or lacks a supported input/output contract: ' . $name);
				}
				$fingerprints[$name] = $contracts[$name]['fingerprint'];
			}
			if (isset($snapshot['fingerprints']) && $snapshot['fingerprints'] !== $fingerprints) {
				throw new \RuntimeException('Program tool contracts changed before resume.');
			}
			$snapshot['fingerprints'] = $fingerprints;
			while (true) {
				if ($this->cancelled($context)) { throw new \RuntimeException('Program execution was cancelled.'); }
				if ($state->waitingCallId === '') {
					$next = $this->interpreter->advance($program, $state, $call->getId(), fn(): bool => $this->cancelled($context));
					if (array_key_exists('result', $next)) {
						return $this->finish($context, $patch, $call, $snapshot, $state, $next['result']);
					}
					$patch->apply(AgentStageResult::patch([
						Keys::PENDING_TOOL_CALLS => [$next['call']], Keys::PHASE => Keys::PHASE_TOOLS,
						Keys::TOOL_CACHE_PLANS => []
					]));
				}
				$snapshot['vm'] = $state->toJson();
				$patch->apply(AgentStageResult::patch([Keys::PROGRAM_STATE => $snapshot]));
				$inStage = true;
				$synchronize();
				if ($context->getVar(Keys::PHASE) === Keys::PHASE_TOOLS) {
					$pending = (array)$context->getVar(Keys::PENDING_TOOL_CALLS);
					$child = count($pending) === 1 ? reset($pending) : null;
					if (!$child instanceof AiToolCall || $child->getId() !== $state->waitingCallId || !isset($fingerprints[$child->getName()]) || !$policy->supports($context)) {
						throw new \RuntimeException('Program continuation has no matching concrete policy request.');
					}
					$patch->apply($policy->process($context));
					$synchronize();
				}
				if ($context->getVar(Keys::SUSPENDED) === true) { return $patch->result(); }
				if ($this->stopped($context)) { $inStage = false; throw new \RuntimeException('Program stopped by the existing execution policy.'); }
				$patch->apply($execute());
				$synchronize();
				$inStage = false;
				if ($context->getVar(Keys::SUSPENDED) === true) { return $patch->result(); }
				if ($this->stopped($context)) { throw new \RuntimeException('Program stopped by the existing execution stage.'); }
				$matching = [];
				foreach ((array)$context->getVar(Keys::TOOL_RESULTS) as $result) {
					if ($result instanceof AgentToolResult && $result->getCallId() === $state->waitingCallId) { $matching[] = $result; }
				}
				if (count($matching) !== 1) { throw new \RuntimeException('Program requires exactly one result for the pending call.'); }
				$this->interpreter->acceptResult($state, $matching[0]);
			}
		} catch (\Throwable $exception) {
			if (!$call instanceof AiToolCall) { throw $exception; }
			// Unexpected infrastructure exceptions must not be presented as a safe retry.
			if ($inStage || (!$exception instanceof \RuntimeException && !$exception instanceof \InvalidArgumentException && !$exception instanceof \JsonException && !$exception instanceof \ArithmeticError)) {
				$patch->apply(AgentStageResult::patch([
					Keys::FAILURE_CODE => 'program_execution_failed', Keys::FAILURE_MESSAGE => 'Program execution failed.',
					Keys::FAILURE_DETAIL => ['partial_response_allowed' => true], Keys::PHASE => Keys::PHASE_FAILED
				]));
			}
			return $this->finish($context, $patch, $call, $snapshot, $state, null, $inStage ? 'Program execution failed in the existing tool pipeline.' : $exception->getMessage());
		}
	}

	private function rejectMixed(IAgentContext $context, AgentStageResultAccumulator $patch, array $calls): AgentStageResult {
		$results = [];
		$index = (int)$context->getVar(Keys::CALL_INDEX);
		foreach ($calls as $call) {
			if (!$call instanceof AiToolCall) { throw new \LogicException('Non-normalized mixed program call.'); }
			$index++;
			$error = 'Submit the program alone; no calls in this mixed batch were executed.';
			$output = ['status' => 'failed', 'error_code' => 'program_mixed_calls', 'error' => $error];
			$result = AgentToolResult::failure($call->getId(), $call->getName(), $call->getArguments(), 'program_mixed_calls', $error, ['iteration' => (int)$context->getVar(Keys::ITERATION), 'call_index' => $index], $output);
			$results[] = $result;
			if ($call->getName() === ToolProgramDefinitionService::TOOL_NAME) {
				$this->emit($context, $call, $index);
				$this->emit($context, $call, $index, $result);
			}
		}
		$patch->apply(AgentStageResult::patch([Keys::CALL_INDEX => $index, Keys::PENDING_TOOL_CALLS => [], Keys::TOOL_RESULTS => $results, Keys::PHASE => Keys::PHASE_AFTER_TOOLS]));
		return $patch->result();
	}

	private function finish(IAgentContext $context, AgentStageResultAccumulator $patch, AiToolCall $call, array $snapshot, ToolProgramState $state, mixed $value, string $error = ''): AgentStageResult {
		$children = (array)$context->getVar(Keys::TOOL_RESULTS);
		$successful = 0;
		$failed = 0;
		$failures = [];
		foreach ($children as $child) {
			if (!$child instanceof AgentToolResult) { continue; }
			if ($child->isSuccess()) { $successful++; } else {
				$failed++;
				$failures[] = ['call_id' => $child->getCallId(), 'error_code' => $child->getErrorCode()];
			}
		}
		$cancelled = $this->cancelled($context);
		$output = [
			'status' => $cancelled ? 'cancelled' : ($error === '' ? 'completed' : 'failed'),
			'result' => $value,
			'failed_calls' => $failures,
			'execution' => ['tool_calls' => $state->calls, 'successful' => $successful, 'failed' => $failed, 'unresolved' => max(0, $state->calls - $successful - $failed), 'instructions' => $state->steps]
		];
		$index = (int)$snapshot['call_index'];
		$metadata = ['iteration' => (int)$context->getVar(Keys::ITERATION), 'call_index' => $index, 'program' => true];
		if ($error === '' && !$cancelled) {
			$result = AgentToolResult::success($call->getId(), $call->getName(), $call->getArguments(), $output, $metadata);
		} else {
			$error = $error !== '' ? $error : 'Program execution was cancelled.';
			$output['error_code'] = $cancelled ? 'program_cancelled' : 'program_failed';
			$output['error'] = $error;
			$result = AgentToolResult::failure($call->getId(), $call->getName(), $call->getArguments(), $output['error_code'], $error, $metadata, $output);
		}
		$this->emit($context, $call, $index, $result);
		$values = [
			Keys::OBSERVATIONS => array_merge((array)$context->getVar(Keys::OBSERVATIONS), $children),
			Keys::TOOL_RESULTS => [$result], Keys::PENDING_TOOL_CALLS => [], Keys::PROGRAM_STATE => [], Keys::TOOL_CACHE_PLANS => []
		];
		if (!$this->stopped($context)) { $values[Keys::PHASE] = Keys::PHASE_AFTER_TOOLS; }
		if ($cancelled) { $values[Keys::EXECUTION_STATUS] = AgentExecutionStatus::CANCELLED; }
		$patch->apply(AgentStageResult::patch($values));
		return $patch->result();
	}

	private function cancelled(IAgentContext $context): bool {
		$sink = $context->getVar(IAgentEventSink::CONTEXT_KEY);
		return $context->getVar(Keys::EXECUTION_STATUS) === AgentExecutionStatus::CANCELLED || ($sink instanceof IAgentEventSink && $sink->isCancelled());
	}

	private function stopped(IAgentContext $context): bool {
		return $this->cancelled($context) || trim((string)$context->getVar(Keys::FAILURE_CODE)) !== '';
	}

	private function emit(IAgentContext $context, AiToolCall $call, int $index, ?AgentToolResult $result = null): void {
		$trace = (array)$context->getVar(Keys::TRACE);
		$trace['source'] = 'agent';
		$trace['program_call_id'] = $call->getId();
		$node = (string)$context->getVar(Keys::NODE_ID);
		$iteration = (int)$context->getVar(Keys::ITERATION);
		$name = $call->getName();
		$args = $call->getArguments();
		try {
			$event = $result === null
				? new MissionBayToolStartedEvent($node, $call->getId(), $name, $name, $args, $iteration, '', $index, $trace)
				: ($result->isSuccess()
					? new MissionBayToolFinishedEvent($node, $call->getId(), $name, $name, $args, $result->getOutput(), $iteration, '', $index, $trace)
					: new MissionBayToolFailedEvent($node, $call->getId(), $name, $name, $args, $result->getErrorMessage(), 'program', $result->getErrorCode(), $iteration, '', $index, $trace, $result->getOutput()));
			$this->eventManager->fire($event);
		} catch (\Throwable) {
			// Same non-fatal audit delivery contract as ordinary tools.
		}
	}
}

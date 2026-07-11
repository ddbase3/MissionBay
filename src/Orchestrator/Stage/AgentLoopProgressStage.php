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

namespace MissionBay\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentProgressAssessment;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;

/**
 * AgentLoopProgressStage
 *
 * Detects a stalled tool loop after observations have been committed. The
 * stage never removes or suppresses tool calls. It only terminates a loop when
 * the latest iteration consists exclusively of successful repeat-safe calls
 * whose normalized arguments and outputs exactly match earlier observations.
 */
final class AgentLoopProgressStage implements IAgentStage {

	public function __construct(
		private readonly string $id = 'loop-progress',
		private readonly string $stageName = 'loop-progress',
		private readonly int $maxConsecutiveStalledIterations = 1
	) {
		if ($this->maxConsecutiveStalledIterations < 1) {
			throw new \InvalidArgumentException('maxConsecutiveStalledIterations must be at least 1.');
		}
	}

	public static function getName(): string {
		return 'agentloopprogressstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		return 'Detects exact repeated read-only observations and ends a stalled loop without suppressing the executed tool calls.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_OBSERVED
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);
		$observations = $context->getVar(AgentToolLoopContextKeys::OBSERVATIONS);
		$toolDefinitions = $context->getVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS);
		$assessments = $context->getVar(AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS);
		$consecutiveStalled = (int)($context->getVar(AgentToolLoopContextKeys::CONSECUTIVE_STALLED_ITERATIONS) ?? 0);

		$observations = is_array($observations) ? $observations : [];
		$toolDefinitions = is_array($toolDefinitions) ? $toolDefinitions : [];
		$assessments = is_array($assessments) ? $assessments : [];

		$current = [];
		$previous = [];

		foreach ($observations as $observation) {
			if (!$observation instanceof AgentToolResult) {
				continue;
			}

			$observationIteration = (int)($observation->getMetadata()['iteration'] ?? 0);
			if ($observationIteration === $iteration) {
				$current[] = $observation;
				continue;
			}

			if ($observationIteration <= 0 || $observationIteration >= $iteration || !$observation->isSuccess()) {
				continue;
			}

			$signature = $this->buildCallSignature($observation);
			$outputHash = $this->buildValueHash($observation->getOutput());
			if ($signature === null || $outputHash === null) {
				continue;
			}

			$previous[$signature][$outputHash] = true;
		}

		$verdict = AgentProgressAssessment::VERDICT_UNKNOWN;
		$reason = 'The latest iteration could not be classified as progress or a safe repeat.';
		$currentSignatures = [];
		$repeatedSignatures = [];
		$allRepeated = $current !== [];
		$allRepeatSafe = $current !== [];

		foreach ($current as $result) {
			if (!$result->isSuccess() || !$this->isRepeatSafe($toolDefinitions, $result->getToolName())) {
				$allRepeatSafe = false;
				$allRepeated = false;
				continue;
			}

			$signature = $this->buildCallSignature($result);
			$outputHash = $this->buildValueHash($result->getOutput());
			if ($signature === null || $outputHash === null) {
				$allRepeated = false;
				continue;
			}

			$currentSignatures[] = $signature;
			if (isset($previous[$signature][$outputHash])) {
				$repeatedSignatures[] = $signature;
				continue;
			}

			$allRepeated = false;
		}

		if ($current !== [] && $allRepeatSafe && $allRepeated) {
			$verdict = AgentProgressAssessment::VERDICT_STALLED;
			$consecutiveStalled++;
			$reason = 'All successful repeat-safe tool calls matched earlier calls with equivalent arguments and unchanged outputs.';
		} elseif ($current !== [] && $allRepeatSafe) {
			$verdict = AgentProgressAssessment::VERDICT_PROGRESS;
			$consecutiveStalled = 0;
			$reason = 'The latest repeat-safe tool observations added a new call signature or changed output.';
		} else {
			$consecutiveStalled = 0;
		}

		$currentSignatures = array_values(array_unique($currentSignatures));
		$repeatedSignatures = array_values(array_unique($repeatedSignatures));
		$terminated = $verdict === AgentProgressAssessment::VERDICT_STALLED
			&& $consecutiveStalled >= $this->maxConsecutiveStalledIterations;

		$assessment = new AgentProgressAssessment(
			iteration: $iteration,
			verdict: $verdict,
			consecutiveStalledIterations: $consecutiveStalled,
			reason: $reason,
			currentSignatures: $currentSignatures,
			repeatedSignatures: $repeatedSignatures,
			metadata: [
				'max_consecutive_stalled_iterations' => $this->maxConsecutiveStalledIterations,
				'terminated' => $terminated,
				'current_result_count' => count($current),
				'repeat_safe_result_count' => count($currentSignatures)
			]
		);
		$assessments[] = $assessment;

		$patch = [
			AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS => $assessments,
			AgentToolLoopContextKeys::CONSECUTIVE_STALLED_ITERATIONS => $consecutiveStalled,
			AgentToolLoopContextKeys::LOOP_PROGRESS_TERMINATED => $terminated
		];

		if ($terminated) {
			$patch += [
				AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE => null,
				AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_COMPLETE,
				AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION => $this->buildFinalInstruction($assessment),
				AgentToolLoopContextKeys::CONTINUATION_HINT => '',
				AgentToolLoopContextKeys::COMPLETED => true,
				AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL
			];
		} elseif ($verdict === AgentProgressAssessment::VERDICT_STALLED) {
			$patch[AgentToolLoopContextKeys::CONTINUATION_HINT] = $this->buildContinuationHint($assessment);
		}

		return AgentStageResult::patch($patch, [
			'progress' => $assessment->toArray()
		]);
	}

	/**
	 * @param array<int,array<string,mixed>> $definitions
	 */
	private function isRepeatSafe(array $definitions, string $toolName): bool {
		foreach ($definitions as $definition) {
			if (!is_array($definition) || (string)($definition['function']['name'] ?? '') !== $toolName) {
				continue;
			}

			$function = is_array($definition['function'] ?? null) ? $definition['function'] : [];
			$annotations = is_array($definition['annotations'] ?? null)
				? $definition['annotations']
				: (is_array($function['annotations'] ?? null) ? $function['annotations'] : []);

			foreach (['readOnlyHint', 'read_only', 'readonly'] as $key) {
				if (($definition[$key] ?? false) === true || ($function[$key] ?? false) === true || ($annotations[$key] ?? false) === true) {
					return true;
				}
			}

			$tags = $definition['tags'] ?? [];
			if (!is_array($tags)) {
				$tags = [$tags];
			}

			foreach ($tags as $tag) {
				$normalized = strtolower(trim((string)$tag));
				if (in_array($normalized, ['readonly', 'read-only', 'read_only'], true)) {
					return true;
				}
			}

			return false;
		}

		return false;
	}

	private function buildCallSignature(AgentToolResult $result): ?string {
		$arguments = $this->normalizeValue($result->getArguments());
		if ($arguments === null) {
			return null;
		}

		$json = $this->encode($arguments);
		if ($json === null) {
			return null;
		}

		return $result->getToolName() . ':' . substr(hash('sha256', $json), 0, 16);
	}

	private function buildValueHash(mixed $value): ?string {
		$normalized = $this->normalizeValue($value);
		if ($normalized === null && $value !== null) {
			return null;
		}

		$json = $this->encode($normalized);
		return $json === null ? null : hash('sha256', $json);
	}

	private function normalizeValue(mixed $value): mixed {
		if (is_array($value)) {
			if (array_is_list($value)) {
				return array_map(fn(mixed $item): mixed => $this->normalizeValue($item), $value);
			}

			ksort($value, SORT_STRING);
			$result = [];
			foreach ($value as $key => $item) {
				$result[(string)$key] = $this->normalizeValue($item);
			}
			return $result;
		}

		if (is_object($value)) {
			if ($value instanceof \JsonSerializable) {
				return $this->normalizeValue($value->jsonSerialize());
			}

			if (method_exists($value, 'toArray')) {
				return $this->normalizeValue($value->toArray());
			}

			return null;
		}

		if (is_resource($value)) {
			return null;
		}

		return $value;
	}

	private function encode(mixed $value): ?string {
		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
		return $json === false ? null : $json;
	}

	private function buildContinuationHint(AgentProgressAssessment $assessment): string {
		return implode("\n", [
			'The latest iteration repeated successful read-only tool calls with equivalent arguments and unchanged outputs.',
			'Do not request those calls again. Choose a materially different query only when it is expected to add evidence; otherwise end the tool phase.',
			'Progress assessment: ' . $assessment->getReason()
		]);
	}

	private function buildFinalInstruction(AgentProgressAssessment $assessment): string {
		return implode("\n", [
			'The tool loop was ended because an iteration repeated successful read-only calls with equivalent arguments and unchanged outputs.',
			'Answer from the evidence already available. If the available observations do not identify or prove the requested fact, state that limitation clearly instead of inventing it.',
			'Progress assessment: ' . $assessment->getReason()
		]);
	}
}

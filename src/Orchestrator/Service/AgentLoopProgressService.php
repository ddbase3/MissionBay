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

use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentProgressAssessment;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;

/**
 * AgentLoopProgressService
 *
 * Detects a stalled tool loop after observations have been committed. The
 * service never removes or suppresses tool calls. It only terminates a loop when
 * the latest iteration consists exclusively of successful repeat-safe calls
 * whose normalized outputs fail to add new evidence. Exact repeated calls stop
 * immediately. Changed text-query arguments that still produce unchanged outputs receive
 * one warning iteration before termination.
 */
final class AgentLoopProgressService {

	public function __construct(
		private readonly int $maxConsecutiveStalledIterations = 2
	) {
		if ($this->maxConsecutiveStalledIterations < 1) {
			throw new \InvalidArgumentException('maxConsecutiveStalledIterations must be at least 1.');
		}
	}

	public function assess(IAgentContext $context): AgentStageResult {
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);
		$observations = $context->getVar(AgentToolLoopContextKeys::OBSERVATIONS);
		$toolDefinitions = $context->getVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS);
		$assessments = $context->getVar(AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS);
		$consecutiveStalled = (int)($context->getVar(AgentToolLoopContextKeys::CONSECUTIVE_STALLED_ITERATIONS) ?? 0);

		$observations = is_array($observations) ? $observations : [];
		$toolDefinitions = is_array($toolDefinitions) ? $toolDefinitions : [];
		$assessments = is_array($assessments) ? $assessments : [];

		$current = [];
		$previousBySignature = [];
		$previousByOutput = [];

		foreach ($observations as $observation) {
			if (!$observation instanceof AgentToolResult) {
				continue;
			}

			$observationIteration = (int)($observation->getMetadata()['iteration'] ?? 0);
			if ($observationIteration === $iteration) {
				$current[] = $observation;
				continue;
			}

			if (
				$observationIteration <= 0
				|| $observationIteration >= $iteration
				|| !$observation->isSuccess()
				|| !$this->isRepeatSafe($toolDefinitions, $observation->getToolName())
			) {
				continue;
			}

			$signature = $this->buildCallSignature($observation);
			$outputHash = $this->buildValueHash($observation->getOutput());
			if ($signature === null || $outputHash === null) {
				continue;
			}

			$previousBySignature[$signature][$outputHash] = true;
			$previousByOutput[$observation->getToolName()][$outputHash][] = $observation->getArguments();
		}

		$verdict = AgentProgressAssessment::VERDICT_UNKNOWN;
		$reason = 'The latest iteration could not be classified as progress or a safe repeat.';
		$currentSignatures = [];
		$repeatedSignatures = [];
		$outputRepeatedSignatures = [];
		$allExactRepeated = $current !== [];
		$allOutputRepeated = $current !== [];
		$allRepeatSafe = $current !== [];

		foreach ($current as $result) {
			if (!$result->isSuccess() || !$this->isRepeatSafe($toolDefinitions, $result->getToolName())) {
				$allRepeatSafe = false;
				$allExactRepeated = false;
				$allOutputRepeated = false;
				continue;
			}

			$signature = $this->buildCallSignature($result);
			$outputHash = $this->buildValueHash($result->getOutput());
			if ($signature === null || $outputHash === null) {
				$allExactRepeated = false;
				$allOutputRepeated = false;
				continue;
			}

			$currentSignatures[] = $signature;
			if (isset($previousBySignature[$signature][$outputHash])) {
				$repeatedSignatures[] = $signature;
			} else {
				$allExactRepeated = false;
			}

			$rephrasedWithoutNewEvidence = false;
			foreach ($previousByOutput[$result->getToolName()][$outputHash] ?? [] as $previousArguments) {
				if ($this->isLikelyEquivalentReadQuery($previousArguments, $result->getArguments())) {
					$rephrasedWithoutNewEvidence = true;
					break;
				}
			}

			if ($rephrasedWithoutNewEvidence) {
				$outputRepeatedSignatures[] = $signature;
			} else {
				$allOutputRepeated = false;
			}
		}

		$repeatMode = 'none';
		if ($current !== [] && $allRepeatSafe && $allExactRepeated) {
			$verdict = AgentProgressAssessment::VERDICT_STALLED;
			$consecutiveStalled++;
			$repeatMode = 'exact-call';
			$reason = 'All successful read-only calls repeated earlier calls with equivalent arguments and unchanged outputs.';
		} elseif ($current !== [] && $allRepeatSafe && $allOutputRepeated) {
			$verdict = AgentProgressAssessment::VERDICT_STALLED;
			$consecutiveStalled++;
			$repeatMode = 'unchanged-output';
			$reason = 'The read-only calls only rephrased equivalent text queries and reproduced outputs already observed from the same tools.';
		} elseif ($current !== [] && $allRepeatSafe) {
			$verdict = AgentProgressAssessment::VERDICT_PROGRESS;
			$consecutiveStalled = 0;
			$reason = 'The latest read-only tool observations added new output evidence.';
		} else {
			$consecutiveStalled = 0;
		}

		$currentSignatures = array_values(array_unique($currentSignatures));
		$repeatedSignatures = array_values(array_unique($repeatedSignatures));
		$outputRepeatedSignatures = array_values(array_unique($outputRepeatedSignatures));
		$terminated = $verdict === AgentProgressAssessment::VERDICT_STALLED
			&& ($allExactRepeated || $consecutiveStalled >= $this->maxConsecutiveStalledIterations);

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
				'repeat_mode' => $repeatMode,
				'current_result_count' => count($current),
				'repeat_safe_result_count' => count($currentSignatures),
				'unchanged_output_result_count' => count($outputRepeatedSignatures)
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

	private function isLikelyEquivalentReadQuery(array $previous, array $current): bool {
		$previous = $this->normalizeValue($previous);
		$current = $this->normalizeValue($current);
		if (!is_array($previous) || !is_array($current) || array_keys($previous) !== array_keys($current)) {
			return false;
		}

		$changed = 0;
		foreach ($previous as $key => $previousValue) {
			$currentValue = $current[$key];
			if ($previousValue === $currentValue) {
				continue;
			}

			$changed++;
			if (!preg_match('/^(?:q|query|search|search_query|term|text|question|prompt)$/i', (string)$key)) {
				return false;
			}
			if (!is_string($previousValue) || !is_string($currentValue)) {
				return false;
			}
			if (!$this->hasSubstantialTextOverlap($previousValue, $currentValue)) {
				return false;
			}
		}

		return $changed > 0;
	}

	private function hasSubstantialTextOverlap(string $left, string $right): bool {
		$left = $this->normalizeQueryText($left);
		$right = $this->normalizeQueryText($right);
		if ($left === '' || $right === '') {
			return false;
		}
		if (str_contains($left, $right) || str_contains($right, $left)) {
			return true;
		}

		$leftTokens = array_values(array_unique(array_filter(explode(' ', $left), static fn(string $token): bool => strlen($token) >= 2)));
		$rightTokens = array_values(array_unique(array_filter(explode(' ', $right), static fn(string $token): bool => strlen($token) >= 2)));
		if ($leftTokens === [] || $rightTokens === []) {
			return false;
		}

		$shared = count(array_intersect($leftTokens, $rightTokens));
		return ($shared / min(count($leftTokens), count($rightTokens))) >= 0.6;
	}

	private function normalizeQueryText(string $text): string {
		$text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
		$text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;
		return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
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
			'The latest read-only tool iteration added no new output evidence.',
			'Do not repeat the same calls and do not merely rephrase their arguments. Continue only with a concretely different tool step that is reasonably expected to resolve a material gap; otherwise end the tool phase and report the limitation.',
			'Progress assessment: ' . $assessment->getReason()
		]);
	}

	private function buildFinalInstruction(AgentProgressAssessment $assessment): string {
		return implode("\n", [
			'The tool loop was ended because repeated read-only work stopped adding new output evidence.',
			'Answer from the evidence already available. If the observations do not identify, support, or verify the requested fact or action, state that limitation clearly instead of inventing it.',
			'Progress assessment: ' . $assessment->getReason()
		]);
	}
}

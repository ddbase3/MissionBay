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
use AssistantFoundation\Dto\AgentBudget;
use AssistantFoundation\Dto\AgentBudgetAssessment;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AiUsage;

/**
 * AgentBudgetGuardService
 *
 * Performs deterministic budget preflight checks at configured execution
 * boundaries. The same service is called before model decisions, tool execution,
 * and final response generation. It evaluates only exact runtime counters and
 * provider-normalized usage metadata. Missing usage is recorded as unknown and
 * can optionally block the run through AgentBudget::requiresUsageReporting().
 */
final class AgentBudgetGuardService {

	public const CHECKPOINT_MODEL = 'model';
	public const CHECKPOINT_TOOLS = 'tools';
	public const CHECKPOINT_FINAL = 'final';

	public function check(IAgentContext $context, string $checkpoint): AgentStageResult {
		if (!in_array($checkpoint, [self::CHECKPOINT_MODEL, self::CHECKPOINT_TOOLS, self::CHECKPOINT_FINAL], true)) {
			throw new \InvalidArgumentException('Unsupported agent budget checkpoint: ' . $checkpoint);
		}
		$budget = $context->getVar(AgentToolLoopContextKeys::BUDGET);
		$modelResults = $context->getVar(AgentToolLoopContextKeys::MODEL_RESULTS);
		$executedToolCalls = $context->getVar(AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS);
		$pendingToolCalls = $context->getVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS);
		$assessments = $context->getVar(AgentToolLoopContextKeys::BUDGET_ASSESSMENTS);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);
		$startedAt = $context->getVar(AgentToolLoopContextKeys::RUN_STARTED_AT);

		if (!$budget instanceof AgentBudget) {
			$budget = AgentBudget::unlimited();
		}

		if (!is_array($modelResults)) {
			$modelResults = [];
		}

		if (!is_array($executedToolCalls)) {
			$executedToolCalls = [];
		}

		if (!is_array($pendingToolCalls)) {
			$pendingToolCalls = [];
		}

		if (!is_array($assessments)) {
			$assessments = [];
		}

		$usageState = $this->aggregateUsage($modelResults);
		$elapsedMs = is_int($startedAt)
			? max(0.0, round((hrtime(true) - $startedAt) / 1000000, 3))
			: 0.0;
		$currentToolCallCount = $this->countExecutedToolCalls($executedToolCalls);
		$pendingToolCallCount = $checkpoint === self::CHECKPOINT_TOOLS
			? count($pendingToolCalls)
			: 0;
		$projectedToolCallCount = $currentToolCallCount + $pendingToolCallCount;
		$exceededLimits = $this->findExceededLimits(
			$budget,
			$usageState['usage'],
			$usageState['metric_values'],
			$usageState['operation_count'],
			$checkpoint === self::CHECKPOINT_TOOLS ? $projectedToolCallCount : null,
			$elapsedMs
		);
		$unknownLimits = $this->findUnknownLimits(
			$budget,
			$usageState['operation_count'],
			$usageState['token_report_counts'],
			$usageState['metric_report_counts']
		);
		$assessment = new AgentBudgetAssessment(
			iteration: $iteration,
			budget: $budget,
			usage: $usageState['usage'],
			aiOperationCount: $usageState['operation_count'],
			toolCallCount: $currentToolCallCount,
			elapsedMs: $elapsedMs,
			exceededLimits: $exceededLimits,
			unknownLimits: $unknownLimits,
			metadata: [
				'phase' => $this->getExpectedPhase($checkpoint),
				'checkpoint' => $checkpoint,
				'pending_tool_call_count' => $pendingToolCallCount,
				'projected_tool_call_count' => $projectedToolCallCount
			]
		);
		$assessments[] = $assessment;

		if ($assessment->hasExceededLimits()) {
			$message = match($checkpoint) {
				self::CHECKPOINT_TOOLS => 'Agent budget was exceeded before tool execution could start.',
				self::CHECKPOINT_FINAL => 'Agent budget was exceeded before final response generation could start.',
				default => 'Agent budget was exceeded before another model decision could start.'
			};

			return $this->failure(
				'agent_budget_exceeded',
				$message,
				$assessment,
				$assessments
			);
		}

		if ($budget->requiresUsageReporting() && $assessment->hasUnknownLimits()) {
			return $this->failure(
				'agent_budget_usage_unknown',
				'Agent budget requires provider usage reporting, but one or more configured usage dimensions are unknown.',
				$assessment,
				$assessments
			);
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::BUDGET_ASSESSMENTS => $assessments
		], [
			'budget' => $assessment->toArray()
		]);
	}


	/**
	 * Cached results are logical tool observations but do not consume the
	 * execution-count budget because no tool implementation was invoked.
	 *
	 * @param array<int,mixed> $executedToolCalls
	 */
	private function countExecutedToolCalls(array $executedToolCalls): int {
		$count = 0;

		foreach ($executedToolCalls as $call) {
			if (is_array($call) && ($call['cached'] ?? false) === true) {
				continue;
			}

			$count++;
		}

		return $count;
	}

	private function getExpectedPhase(string $checkpoint): string {
		return match($checkpoint) {
			self::CHECKPOINT_TOOLS => AgentToolLoopContextKeys::PHASE_TOOLS,
			self::CHECKPOINT_FINAL => AgentToolLoopContextKeys::PHASE_FINAL,
			default => AgentToolLoopContextKeys::PHASE_MODEL
		};
	}

	/**
	 * @param array<int,mixed> $modelResults
	 * @return array{
	 *   usage:AiUsage,
	 *   operation_count:int,
	 *   token_report_counts:array<string,int>,
	 *   metric_values:array<string,int|float>,
	 *   metric_report_counts:array<string,int>
	 * }
	 */
	private function aggregateUsage(array $modelResults): array {
		$usage = AiUsage::none();
		$operationCount = 0;
		$tokenReportCounts = [
			'input_tokens' => 0,
			'output_tokens' => 0,
			'total_tokens' => 0
		];
		$metricValues = [];
		$metricReportCounts = [];

		foreach ($modelResults as $modelResult) {
			if (!is_array($modelResult)) {
				continue;
			}

			$operationCount++;
			$usageData = $modelResult['usage'] ?? null;
			if (!is_array($usageData)) {
				continue;
			}

			$usage = $usage->merge(AiUsage::fromArray($usageData));

			foreach (array_keys($tokenReportCounts) as $tokenName) {
				if ($this->isIntegerLike($usageData[$tokenName] ?? null)) {
					$tokenReportCounts[$tokenName]++;
				}
			}

			$metrics = $usageData['metrics'] ?? null;
			if (!is_array($metrics)) {
				continue;
			}

			foreach ($metrics as $name => $value) {
				if (!is_string($name) || (!is_int($value) && !is_float($value))) {
					continue;
				}

				$metricValues[$name] = ($metricValues[$name] ?? 0) + $value;
				$metricReportCounts[$name] = ($metricReportCounts[$name] ?? 0) + 1;
			}
		}

		return [
			'usage' => $usage,
			'operation_count' => $operationCount,
			'token_report_counts' => $tokenReportCounts,
			'metric_values' => $metricValues,
			'metric_report_counts' => $metricReportCounts
		];
	}

	/**
	 * @param array<string,int|float> $metricValues
	 * @return array<string,array<string,mixed>>
	 */
	private function findExceededLimits(
		AgentBudget $budget,
		AiUsage $usage,
		array $metricValues,
		int $aiOperationCount,
		?int $projectedToolCallCount,
		float $elapsedMs
	): array {
		$exceeded = [];

		$this->addExhausted($exceeded, 'input_tokens', $usage->getInputTokens(), $budget->getMaxInputTokens());
		$this->addExhausted($exceeded, 'output_tokens', $usage->getOutputTokens(), $budget->getMaxOutputTokens());
		$this->addExhausted($exceeded, 'total_tokens', $usage->getTotalTokens(), $budget->getMaxTotalTokens());
		$this->addExhausted($exceeded, 'ai_operations', $aiOperationCount, $budget->getMaxAiOperations());
		$this->addExceeded($exceeded, 'tool_calls', $projectedToolCallCount, $budget->getMaxToolCalls());
		$this->addExhausted($exceeded, 'elapsed_ms', $elapsedMs, $budget->getMaxElapsedMs());

		foreach ($budget->getMetricLimits() as $name => $limit) {
			$this->addExhausted($exceeded, 'metric:' . $name, $metricValues[$name] ?? null, $limit);
		}

		return $exceeded;
	}

	/**
	 * @param array<string,int> $tokenReportCounts
	 * @param array<string,int> $metricReportCounts
	 * @return array<string,array<string,mixed>>
	 */
	private function findUnknownLimits(
		AgentBudget $budget,
		int $aiOperationCount,
		array $tokenReportCounts,
		array $metricReportCounts
	): array {
		if ($aiOperationCount === 0) {
			return [];
		}

		$unknown = [];
		$this->addUnknownTokenLimit(
			$unknown,
			'input_tokens',
			$budget->getMaxInputTokens(),
			$tokenReportCounts['input_tokens'] ?? 0,
			$aiOperationCount
		);
		$this->addUnknownTokenLimit(
			$unknown,
			'output_tokens',
			$budget->getMaxOutputTokens(),
			$tokenReportCounts['output_tokens'] ?? 0,
			$aiOperationCount
		);
		$this->addUnknownTokenLimit(
			$unknown,
			'total_tokens',
			$budget->getMaxTotalTokens(),
			$tokenReportCounts['total_tokens'] ?? 0,
			$aiOperationCount
		);

		foreach ($budget->getMetricLimits() as $name => $limit) {
			if (($metricReportCounts[$name] ?? 0) > 0) {
				continue;
			}

			$unknown['metric:' . $name] = [
				'limit' => $limit,
				'reason' => 'metric_not_reported',
				'ai_operation_count' => $aiOperationCount
			];
		}

		return $unknown;
	}

	/**
	 * @param array<string,array<string,mixed>> $exceeded
	 */
	private function addExhausted(array &$exceeded, string $name, int|float|null $current, int|float|null $limit): void {
		if ($limit === null || $current === null || $current < $limit) {
			return;
		}

		$exceeded[$name] = [
			'current' => $current,
			'limit' => $limit,
			'excess' => max(0, $current - $limit),
			'exhausted' => true
		];
	}

	/**
	 * @param array<string,array<string,mixed>> $exceeded
	 */
	private function addExceeded(array &$exceeded, string $name, int|float|null $current, int|float|null $limit): void {
		if ($limit === null || $current === null || $current <= $limit) {
			return;
		}

		$exceeded[$name] = [
			'current' => $current,
			'limit' => $limit,
			'excess' => $current - $limit
		];
	}

	/**
	 * @param array<string,array<string,mixed>> $unknown
	 */
	private function addUnknownTokenLimit(
		array &$unknown,
		string $name,
		?int $limit,
		int $reportedOperations,
		int $aiOperationCount
	): void {
		if ($limit === null || $reportedOperations >= $aiOperationCount) {
			return;
		}

		$unknown[$name] = [
			'limit' => $limit,
			'reason' => 'not_reported_for_every_ai_operation',
			'reported_operation_count' => $reportedOperations,
			'ai_operation_count' => $aiOperationCount
		];
	}

	private function isIntegerLike(mixed $value): bool {
		return is_int($value)
			|| (is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1);
	}

	/**
	 * @param array<int,AgentBudgetAssessment> $assessments
	 */
	private function failure(
		string $code,
		string $message,
		AgentBudgetAssessment $assessment,
		array $assessments
	): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::BUDGET_ASSESSMENTS => $assessments,
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => $assessment->toArray(),
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
		], [
			'budget' => $assessment->toArray()
		]);
	}
}

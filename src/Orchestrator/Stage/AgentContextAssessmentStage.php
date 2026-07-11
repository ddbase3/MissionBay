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
use AssistantFoundation\Dto\AgentContextAssessment;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiUsage;

/**
 * AgentContextAssessmentStage
 *
 * Records provider-neutral structural context metrics after tool execution and
 * before tool results are materialized into model messages.
 *
 * This stage does not estimate tokens, alter tool results, or change the loop
 * phase. It records exact serialized byte counts and only aggregates token
 * usage that AI providers reported through normalized result metadata.
 */
final class AgentContextAssessmentStage implements IAgentStage {

	public function __construct(
		private readonly string $id = 'context-assessment',
		private readonly string $stageName = 'context-assessment'
	) {}

	public static function getName(): string {
		return 'agentcontextassessmentstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		return 'Measures the current message and tool-result footprint and aggregates provider-reported AI usage without changing content.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);

		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_AFTER_TOOLS
			&& is_array($toolResults)
			&& $toolResults !== []
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$modelResults = $context->getVar(AgentToolLoopContextKeys::MODEL_RESULTS);
		$assessments = $context->getVar(AgentToolLoopContextKeys::CONTEXT_ASSESSMENTS);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);

		if(!is_array($messages)) {
			$messages = [];
		}

		if(!is_array($toolResults)) {
			$toolResults = [];
		}

		if(!is_array($modelResults)) {
			$modelResults = [];
		}

		if(!is_array($assessments)) {
			$assessments = [];
		}

		$successfulToolResults = 0;
		$failedToolResults = 0;
		$toolResultBytes = 0;

		foreach($toolResults as $toolResult) {
			if(!$toolResult instanceof AgentToolResult) {
				return $this->failure(
					'invalid_tool_result',
					'Context assessment stage received a non-normalized tool result.',
					['type' => get_debug_type($toolResult)]
				);
			}

			if($toolResult->isSuccess()) {
				$successfulToolResults++;
			} else {
				$failedToolResults++;
			}

			$toolResultBytes += $this->measureValue($toolResult->toArray());
		}

		$messageBytes = 0;
		foreach($messages as $message) {
			$messageBytes += $this->measureValue($message);
		}

		$assessment = new AgentContextAssessment(
			iteration: $iteration,
			messageCount: count($messages),
			messageBytes: $messageBytes,
			toolResultCount: count($toolResults),
			successfulToolResultCount: $successfulToolResults,
			failedToolResultCount: $failedToolResults,
			toolResultBytes: $toolResultBytes,
			usage: $this->aggregateUsage($modelResults),
			metadata: [
				'phase' => AgentToolLoopContextKeys::PHASE_AFTER_TOOLS
			]
		);
		$assessments[] = $assessment;

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::CONTEXT_ASSESSMENTS => $assessments
		]);
	}

	/**
	 * @param array<int,mixed> $modelResults
	 */
	private function aggregateUsage(array $modelResults): AiUsage {
		$usage = AiUsage::none();

		foreach($modelResults as $modelResult) {
			if(!is_array($modelResult)) {
				continue;
			}

			$resultUsage = $modelResult['usage'] ?? null;
			if(!is_array($resultUsage)) {
				continue;
			}

			$usage = $usage->merge(AiUsage::fromArray($resultUsage));
		}

		return $usage;
	}

	private function measureValue(mixed $value): int {
		if($value === null) {
			return 0;
		}

		if(is_string($value)) {
			return strlen($value);
		}

		if(is_int($value) || is_float($value) || is_bool($value)) {
			return strlen((string)$value);
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if(!is_string($json)) {
			return 0;
		}

		return strlen($json);
	}

	/**
	 * @param array<string,mixed> $detail
	 */
	private function failure(string $code, string $message, array $detail): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => $detail,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
		]);
	}
}

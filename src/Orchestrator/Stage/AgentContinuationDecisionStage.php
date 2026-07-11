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
use AssistantFoundation\Dto\AgentContinuationDecision;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentStageResult;

/**
 * AgentContinuationDecisionStage
 *
 * Evaluates one semantic verification after the primary model has already
 * produced a terminal tool-phase decision. Only an explicit, high-confidence
 * continue recommendation may reopen the loop. Missing, malformed, or low-
 * confidence verifier output keeps the primary terminal decision intact.
 */
final class AgentContinuationDecisionStage implements IAgentStage {

	public function __construct(
		private readonly string $id = 'continuation-decision',
		private readonly string $stageName = 'continuation-decision',
		private readonly float $minAnswerConfidence = 0.75,
		private readonly float $minClarifyConfidence = 0.75,
		private readonly float $minContinueConfidence = 0.70
	) {
		$this->assertConfidence($this->minAnswerConfidence, 'minAnswerConfidence');
		$this->assertConfidence($this->minClarifyConfidence, 'minClarifyConfidence');
		$this->assertConfidence($this->minContinueConfidence, 'minContinueConfidence');
	}

	public static function getName(): string {
		return 'agentcontinuationdecisionstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		return 'Keeps a terminal model decision unless semantic verification gives a high-confidence reason to continue or clarify.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);

		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_FINAL
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) === true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === ''
			&& $this->findSemanticVerification($context, $iteration) instanceof AgentResultVerification;
	}

	public function process(IAgentContext $context): AgentStageResult {
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);
		$verification = $this->findSemanticVerification($context, $iteration);
		$decisions = $context->getVar(AgentToolLoopContextKeys::CONTINUATION_DECISIONS);
		$progressTerminated = $context->getVar(AgentToolLoopContextKeys::LOOP_PROGRESS_TERMINATED) === true;
		$existingFinalInstruction = trim((string)($context->getVar(AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION) ?? ''));

		if (!is_array($decisions)) {
			$decisions = [];
		}

		if (!$verification instanceof AgentResultVerification) {
			return AgentStageResult::none();
		}

		$metadata = $verification->getMetadata();
		$recommendation = strtolower(trim((string)($metadata['recommendation'] ?? 'unknown')));
		$confidence = isset($metadata['confidence']) && is_numeric($metadata['confidence'])
			? max(0.0, min(1.0, (float)$metadata['confidence']))
			: null;
		$decisionName = AgentContinuationDecision::DECISION_ANSWER;
		$decisionReason = $this->buildDefaultAnswerReason($verification, $recommendation, $confidence, $progressTerminated);
		$patch = [
			AgentToolLoopContextKeys::CONTINUATION_HINT => '',
			AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION => $this->mergeInstructions(
				$existingFinalInstruction,
				$this->buildAnswerInstruction($verification)
			),
			AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_COMPLETE,
			AgentToolLoopContextKeys::COMPLETED => true,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL
		];

		if (
			!$progressTerminated
			&& $recommendation === AgentContinuationDecision::DECISION_CONTINUE
			&& $this->meetsThreshold($confidence, $this->minContinueConfidence)
		) {
			$decisionName = AgentContinuationDecision::DECISION_CONTINUE;
			$decisionReason = $verification->getSummary();
			$patch = [
				AgentToolLoopContextKeys::CONTINUATION_HINT => $this->buildContinuationHint($verification, $recommendation, $confidence),
				AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE => null,
				AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION => '',
				AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_NONE,
				AgentToolLoopContextKeys::COMPLETED => false,
				AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL
			];
		} elseif (
			$recommendation === AgentContinuationDecision::DECISION_CLARIFY
			&& $this->meetsThreshold($confidence, $this->minClarifyConfidence)
		) {
			$decisionName = AgentContinuationDecision::DECISION_CLARIFY;
			$decisionReason = $verification->getSummary();
			$patch[AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION] = $this->mergeInstructions(
				$existingFinalInstruction,
				$this->buildClarificationInstruction($verification)
			);
		} elseif (
			$recommendation === AgentContinuationDecision::DECISION_ANSWER
			&& $verification->isVerified()
			&& $this->meetsThreshold($confidence, $this->minAnswerConfidence)
		) {
			$decisionReason = $verification->getSummary();
		}

		$decision = new AgentContinuationDecision(
			iteration: $iteration,
			decision: $decisionName,
			reason: $decisionReason,
			source: $verification->getVerifier(),
			confidence: $confidence,
			metadata: [
				'verdict' => $verification->getVerdict(),
				'recommendation' => $recommendation,
				'min_answer_confidence' => $this->minAnswerConfidence,
				'min_clarify_confidence' => $this->minClarifyConfidence,
				'min_continue_confidence' => $this->minContinueConfidence,
				'primary_terminal_decision_preserved' => $decisionName !== AgentContinuationDecision::DECISION_CONTINUE,
				'loop_progress_terminated' => $progressTerminated,
				'issues' => $verification->getIssues()
			]
		);
		$decisions[] = $decision;
		$patch[AgentToolLoopContextKeys::CONTINUATION_DECISIONS] = $decisions;

		return AgentStageResult::patch($patch, [
			'continuation' => $decision->toArray()
		]);
	}

	private function findSemanticVerification(IAgentContext $context, int $iteration): ?AgentResultVerification {
		$verifications = $context->getVar(AgentToolLoopContextKeys::RESULT_VERIFICATIONS);

		if (!is_array($verifications)) {
			return null;
		}

		foreach (array_reverse($verifications) as $verification) {
			if (!$verification instanceof AgentResultVerification) {
				continue;
			}

			if ($verification->getIteration() !== $iteration) {
				continue;
			}

			if ($verification->getVerifier() !== AgentSemanticVerificationStage::VERIFIER_NAME) {
				continue;
			}

			return $verification;
		}

		return null;
	}

	private function meetsThreshold(?float $confidence, float $threshold): bool {
		return $confidence !== null && $confidence >= $threshold;
	}

	private function buildAnswerInstruction(AgentResultVerification $verification): string {
		return implode("\n", [
			'The tool-decision model has ended the tool phase.',
			'Produce the user-facing answer from the available conversation and tool observations.',
			'Keep verified facts separate from uncertainty and do not claim checks that are not present in the evidence.',
			'Semantic assessment: ' . $verification->getSummary()
		]);
	}

	private function buildClarificationInstruction(AgentResultVerification $verification): string {
		return implode("\n", [
			'The available evidence indicates that a user clarification is required before further reliable tool work.',
			'Ask one concise, specific clarification question. Do not pretend that the missing information was verified.',
			'Assessment summary: ' . $verification->getSummary(),
			'Open issues: ' . $this->formatIssues($verification->getIssues())
		]);
	}

	private function mergeInstructions(string ...$instructions): string {
		$parts = [];

		foreach ($instructions as $instruction) {
			$instruction = trim($instruction);
			if ($instruction !== '' && !in_array($instruction, $parts, true)) {
				$parts[] = $instruction;
			}
		}

		return implode("\n\n", $parts);
	}

	private function buildDefaultAnswerReason(
		AgentResultVerification $verification,
		string $recommendation,
		?float $confidence,
		bool $progressTerminated
	): string {
		if ($progressTerminated) {
			return 'The terminal decision is kept because the deterministic loop-progress guard detected repeated read-only observations without new evidence.';
		}

		if ($recommendation === AgentContinuationDecision::DECISION_CONTINUE) {
			return $confidence === null
				? 'The terminal model decision is kept because the verifier did not report a usable continue confidence.'
				: 'The terminal model decision is kept because continue confidence was below the configured threshold.';
		}

		if ($recommendation === AgentContinuationDecision::DECISION_ANSWER && !$verification->isVerified()) {
			return 'The terminal model decision is kept; semantic verification did not provide a verified answer assessment.';
		}

		if ($recommendation === AgentContinuationDecision::DECISION_CLARIFY) {
			return 'The terminal model decision is kept because clarification confidence was below the configured threshold.';
		}

		return 'The terminal model decision is kept because semantic verification was inconclusive or unavailable.';
	}

	private function buildContinuationHint(
		AgentResultVerification $verification,
		string $recommendation,
		?float $confidence
	): string {
		$confidenceText = $confidence === null
			? 'unknown'
			: rtrim(rtrim(number_format($confidence, 3, '.', ''), '0'), '.');

		return implode("\n", [
			'Guidance from the previous semantic verification:',
			'- recommendation: ' . ($recommendation !== '' ? $recommendation : 'unknown'),
			'- confidence: ' . $confidenceText,
			'- summary: ' . $verification->getSummary(),
			'- open issues: ' . $this->formatIssues($verification->getIssues()),
			'Select only tool calls that are expected to add materially new evidence. Do not repeat a successful call with equivalent arguments unless a different result is reasonably expected. If the accumulated evidence is already sufficient, return the tool-phase completion signal.'
		]);
	}

	/**
	 * @param array<int,array<string,mixed>> $issues
	 */
	private function formatIssues(array $issues): string {
		$messages = [];

		foreach ($issues as $issue) {
			if (!is_array($issue)) {
				continue;
			}

			$message = trim((string)($issue['message'] ?? ''));
			if ($message !== '') {
				$messages[] = $message;
			}
		}

		return $messages === [] ? 'none reported' : implode('; ', $messages);
	}

	private function assertConfidence(float $confidence, string $name): void {
		if ($confidence < 0.0 || $confidence > 1.0) {
			throw new \InvalidArgumentException($name . ' must be between 0.0 and 1.0.');
		}
	}
}

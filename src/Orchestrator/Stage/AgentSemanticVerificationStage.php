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
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;
use MissionBay\Orchestrator\AgentStageResultAccumulator;
use MissionBay\Orchestrator\Service\AgentContinuationDecisionService;
use MissionBay\Orchestrator\Service\AgentSemanticVerificationService;

/**
 * Performs one terminal semantic verification and applies the resulting
 * continue, answer, or clarification decision as one meaningful stage.
 */
final class AgentSemanticVerificationStage implements IAgentStage {

	private AgentSemanticVerificationService $verificationService;
	private AgentContinuationDecisionService $continuationDecisionService;

	public function __construct(
		private readonly string $id = 'semantic-verification',
		private readonly string $stageName = 'semantic-verification',
		private readonly int $maxInputBytes = 60000,
		private readonly int $maxTaskBytes = 12000,
		?AgentSemanticVerificationService $verificationService = null,
		?AgentContinuationDecisionService $continuationDecisionService = null
	) {
		$this->verificationService = $verificationService
			?? new AgentSemanticVerificationService($this->maxInputBytes, $this->maxTaskBytes);
		$this->continuationDecisionService = $continuationDecisionService
			?? new AgentContinuationDecisionService();
	}

	public static function getName(): string {
		return 'agentsemanticverificationstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		return 'Verifies terminal evidence once and resolves continue, answer, or clarification without a separate continuation stage.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_REQUIRED;
	}

	public function supports(IAgentContext $context): bool {
		$observations = $context->getVar(AgentToolLoopContextKeys::OBSERVATIONS);

		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_FINAL
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) === true
			&& is_array($observations)
			&& $observations !== []
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$results = new AgentStageResultAccumulator($context);
		$results->apply($this->verificationService->verify($context), 'verification');

		if ((string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '') {
			$results->apply($this->continuationDecisionService->decide($context), 'decision');
		}

		return $results->result();
	}
}

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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentBudgetAssessment;
use Base3\AssistantFoundation\Dto\AgentContextAssessment;
use Base3\AssistantFoundation\Dto\AgentContextCompaction;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentResultVerification;
use Base3\AssistantFoundation\Dto\AgentStageTraceEntry;
use Base3\AssistantFoundation\Dto\AgentStageResult;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\AgentToolOrchestratorResult;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentBudgetGuardStage;
use Base3\MissionBay\Orchestrator\Stage\AgentContextAssessmentStage;
use Base3\MissionBay\Orchestrator\Stage\AgentContextCompactionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentFinalAnswerStage;
use Base3\MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentResultVerificationStage;
use Base3\MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use Base3\MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\Test\State\StateStoreStub;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use PHPUnit\Framework\TestCase;

final class AgentToolOrchestratorStageTest extends TestCase {

	private function createActionPolicyStage(): AgentActionPolicyStage {
		$fingerprint = new AgentActionFingerprint();
		return new AgentActionPolicyStage(
			new StaticAgentActionPolicyResolver([new AllowAllAgentActionPolicy()]),
			$fingerprint,
			new AgentActionReviewService($fingerprint, new StateStoreAgentSuspensionRepository(new StateStoreStub())),
			new AgentToolContractValidationService(),
			new AgentCapabilitySelectionGuardService(),
			'action-policy',
			'action-policy',
			['allow-all-actions']
		);
	}

	/**
	 * @return array<int,IAgentStage>
	 */
	private function createDefaultTestStages(): array {
		return [
			new AgentModelDecisionStage(),
			$this->createActionPolicyStage(),
			new AgentToolExecutionStage(new RecordingEventManager()),
			new AgentToolObservationStage()
		];
	}

	public function testDefaultStagesImplementFoundationContract(): void {
		$budgetStage = new AgentBudgetGuardStage();
		$toolBudgetStage = new AgentBudgetGuardStage(
			'tool-budget-guard',
			'tool-budget-guard',
			AgentBudgetGuardStage::CHECKPOINT_TOOLS
		);
		$modelStage = new AgentModelDecisionStage();
		$policyStage = $this->createActionPolicyStage();
		$toolStage = new AgentToolExecutionStage(new RecordingEventManager());
		$assessmentStage = new AgentContextAssessmentStage();
		$compactionStage = new AgentContextCompactionStage();
		$verificationStage = new AgentResultVerificationStage();
		$semanticVerificationStage = new AgentSemanticVerificationStage();
		$observationStage = new AgentToolObservationStage();
		$finalAnswerStage = new AgentFinalAnswerStage();

		$this->assertInstanceOf(IAgentStage::class, $budgetStage);
		$this->assertInstanceOf(IAgentStage::class, $toolBudgetStage);
		$this->assertInstanceOf(IAgentStage::class, $modelStage);
		$this->assertInstanceOf(IAgentStage::class, $policyStage);
		$this->assertInstanceOf(IAgentStage::class, $toolStage);
		$this->assertInstanceOf(IAgentStage::class, $assessmentStage);
		$this->assertInstanceOf(IAgentStage::class, $compactionStage);
		$this->assertInstanceOf(IAgentStage::class, $verificationStage);
		$this->assertInstanceOf(IAgentStage::class, $semanticVerificationStage);
		$this->assertInstanceOf(IAgentStage::class, $observationStage);
		$this->assertInstanceOf(IAgentStage::class, $finalAnswerStage);
		$this->assertSame('agentbudgetguardstage', AgentBudgetGuardStage::getName());
		$this->assertSame('budget-guard', $budgetStage->id());
		$this->assertSame('budget-guard', $budgetStage->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $budgetStage->getAiUsage());
		$this->assertSame('tool-budget-guard', $toolBudgetStage->id());
		$this->assertSame('tool-budget-guard', $toolBudgetStage->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $toolBudgetStage->getAiUsage());
		$this->assertSame('agentmodeldecisionstage', AgentModelDecisionStage::getName());
		$this->assertSame('model-decision', $modelStage->id());
		$this->assertSame('model-decision', $modelStage->name());
		$this->assertSame('agentactionpolicystage', AgentActionPolicyStage::getName());
		$this->assertSame('action-policy', $policyStage->id());
		$this->assertSame('action-policy', $policyStage->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $policyStage->getAiUsage());
		$this->assertSame('agenttoolexecutionstage', AgentToolExecutionStage::getName());
		$this->assertSame('tool-execution', $toolStage->id());
		$this->assertSame('tool-execution', $toolStage->name());
		$this->assertSame('agentcontextassessmentstage', AgentContextAssessmentStage::getName());
		$this->assertSame('context-assessment', $assessmentStage->id());
		$this->assertSame('context-assessment', $assessmentStage->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $assessmentStage->getAiUsage());
		$this->assertSame('agentcontextcompactionstage', AgentContextCompactionStage::getName());
		$this->assertSame('context-compaction', $compactionStage->id());
		$this->assertSame('context-compaction', $compactionStage->name());
		$this->assertSame(IAgentStage::AI_USAGE_CONDITIONAL, $compactionStage->getAiUsage());
		$this->assertSame('agentresultverificationstage', AgentResultVerificationStage::getName());
		$this->assertSame('result-verification', $verificationStage->id());
		$this->assertSame('result-verification', $verificationStage->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $verificationStage->getAiUsage());
		$this->assertSame('agentsemanticverificationstage', AgentSemanticVerificationStage::getName());
		$this->assertSame('semantic-verification', $semanticVerificationStage->id());
		$this->assertSame('semantic-verification', $semanticVerificationStage->name());
		$this->assertSame(IAgentStage::AI_USAGE_REQUIRED, $semanticVerificationStage->getAiUsage());
		$this->assertSame('agenttoolobservationstage', AgentToolObservationStage::getName());
		$this->assertSame('tool-observation', $observationStage->id());
		$this->assertSame('tool-observation', $observationStage->name());
		$this->assertSame('agentfinalanswerstage', AgentFinalAnswerStage::getName());
		$this->assertSame('final-answer', $finalAnswerStage->id());
		$this->assertSame('final-answer', $finalAnswerStage->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $finalAnswerStage->getAiUsage());
	}

	public function testTerminalModelResponseKeepsFinalAssistantSeparate(): void {
		$model = new QueueChatModel([
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => 'done'
					]
				]]
			]
		]);
		$context = new AgentContext();
		$messages = [['role' => 'user', 'content' => 'hello']];

		$result = (new AgentToolOrchestrator(null, null, $this->createDefaultTestStages()))->run(
			$model,
			$messages,
			[],
			[],
			$context
		);

		$this->assertTrue($result->isCompleted());
		$this->assertSame(1, $result->getIterations());
		$this->assertCount(1, $result->getModelResults());
		$this->assertSame('chat', $result->getModelResults()[0]['operation']);
		$this->assertSame($messages, $result->getMessages());
		$this->assertSame('done', $result->getFinalAssistantMessage()['content']);
		$this->assertSame('', $result->getFinalOutputContent());
		$this->assertSame(AgentToolOrchestratorResult::FINAL_RESPONSE_COMPLETE, $result->getFinalResponseMode());
		$this->assertSame(AgentToolOrchestratorResult::FINAL_OUTPUT_DELIVERY_NONE, $result->getFinalOutputDelivery());
		$this->assertSame([], $result->getToolCalls());
		$this->assertSame('system', $model->getCalls()[0][0][0]['role']);
		$this->assertStringContainsString('TOOL_PHASE_COMPLETE', $model->getCalls()[0][0][0]['content']);
	}

	public function testToolExecutionPreservesExistingMessageAndResultSemantics(): void {
		$model = new QueueChatModel([
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => null,
						'tool_calls' => [[
							'id' => 'call-1',
							'function' => [
								'name' => 'lookup',
								'arguments' => '{"query":"BASE3"}'
							]
						]]
					]
				]]
			],
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => 'final answer'
					]
				]]
			]
		]);
		$tool = new LookupTool();
		$context = new AgentContext();

		$result = (new AgentToolOrchestrator(null, null, $this->createDefaultTestStages()))->run(
			$model,
			[['role' => 'user', 'content' => 'find it']],
			$tool->getToolDefinitions(),
			[$tool],
			$context
		);

		$this->assertTrue($result->isCompleted());
		$this->assertSame(2, $result->getIterations());
		$this->assertCount(3, $result->getMessages());
		$this->assertSame('assistant', $result->getMessages()[1]['role']);
		$this->assertSame('tool', $result->getMessages()[2]['role']);
		$this->assertSame('call-1', $result->getMessages()[2]['tool_call_id']);
		$this->assertSame('{"found":"BASE3"}', $result->getMessages()[2]['content']);
		$this->assertSame('final answer', $result->getFinalAssistantMessage()['content']);
		$this->assertSame('', $result->getFinalOutputContent());
		$this->assertSame(AgentToolOrchestratorResult::FINAL_RESPONSE_COMPLETE, $result->getFinalResponseMode());
		$this->assertSame(AgentToolOrchestratorResult::FINAL_OUTPUT_DELIVERY_NONE, $result->getFinalOutputDelivery());
		$this->assertCount(1, $result->getResultVerifications());
		$this->assertInstanceOf(AgentResultVerification::class, $result->getResultVerifications()[0]);
		$this->assertTrue($result->getResultVerifications()[0]->isVerified());
		$this->assertSame([
			[
				'tool' => 'lookup',
				'arguments' => ['query' => 'BASE3'],
				'result' => ['found' => 'BASE3']
			]
		], $result->getToolCalls());
		$this->assertCount(1, $result->getToolResults());
		$this->assertTrue($result->getToolResults()[0]->isSuccess());
		$this->assertSame('call-1', $result->getToolResults()[0]->getCallId());
		$this->assertSame(['found' => 'BASE3'], $result->getToolResults()[0]->getOutput());
	}

	public function testAdditionalStageCanWorkBetweenToolExecutionAndNextModelDecision(): void {
		$model = new QueueChatModel([
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => null,
						'tool_calls' => [[
							'id' => 'call-1',
							'function' => [
								'name' => 'lookup',
								'arguments' => '{}'
							]
						]]
					]
				]]
			],
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => 'done'
					]
				]]
			]
		]);
		$tool = new LookupTool();
		$enrichmentStage = new ToolResultEnrichmentStage();
		$context = new AgentContext();
		$orchestrator = new AgentToolOrchestrator(null, null, [
			new AgentModelDecisionStage(),
			$this->createActionPolicyStage(),
			new AgentToolExecutionStage(new RecordingEventManager()),
			$enrichmentStage,
			new AgentToolObservationStage()
		]);

		$result = $orchestrator->run(
			$model,
			[['role' => 'user', 'content' => 'find it']],
			$tool->getToolDefinitions(),
			[$tool],
			$context
		);

		$this->assertTrue($result->isCompleted());
		$this->assertSame(1, $enrichmentStage->getExecutions());
		$secondCallToolMessages = array_values(array_filter(
			$model->getCalls()[1][0],
			static fn(array $message): bool => ($message['role'] ?? null) === 'tool'
		));
		$this->assertSame('stage enrichment', $secondCallToolMessages[0]['content']);
	}

	public function testContextAssessmentRecordsMetricsWithoutChangingToolResultsOrPhase(): void {
		$success = AgentToolResult::success(
			'call-1',
			'lookup',
			['query' => 'BASE3'],
			['found' => true]
		);
		$failure = AgentToolResult::failure(
			'call-2',
			'missing',
			[],
			'tool_not_found',
			'Tool not found: missing'
		);
		$context = new AgentContext(vars: [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_AFTER_TOOLS,
			AgentToolLoopContextKeys::MESSAGES => [
				['role' => 'user', 'content' => 'find it']
			],
			AgentToolLoopContextKeys::TOOL_RESULTS => [$success, $failure],
			AgentToolLoopContextKeys::MODEL_RESULTS => [[
				'usage' => [
					'input_tokens' => 10,
					'output_tokens' => 2,
					'total_tokens' => 12,
					'metrics' => [],
					'details' => []
				]
			]],
			AgentToolLoopContextKeys::CONTEXT_ASSESSMENTS => [],
			AgentToolLoopContextKeys::ITERATION => 1,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => ''
		]);
		$stage = new AgentContextAssessmentStage();

		$this->assertTrue($stage->supports($context));

		$patch = $stage->process($context)->getPatch();
		$assessments = $patch[AgentToolLoopContextKeys::CONTEXT_ASSESSMENTS];

		$this->assertCount(1, $assessments);
		$this->assertInstanceOf(AgentContextAssessment::class, $assessments[0]);
		$this->assertSame(1, $assessments[0]->getMessageCount());
		$this->assertSame(2, $assessments[0]->getToolResultCount());
		$this->assertSame(1, $assessments[0]->getSuccessfulToolResultCount());
		$this->assertSame(1, $assessments[0]->getFailedToolResultCount());
		$this->assertSame(12, $assessments[0]->getUsage()->getTotalTokens());
		$this->assertGreaterThan(0, $assessments[0]->getMessageBytes());
		$this->assertGreaterThan(0, $assessments[0]->getToolResultBytes());
		$this->assertArrayNotHasKey(AgentToolLoopContextKeys::PHASE, $patch);
		$this->assertArrayNotHasKey(AgentToolLoopContextKeys::TOOL_RESULTS, $patch);
	}

	public function testContextCompactionUsesNormalizedModelAndPreservesToolIdentity(): void {
		$model = new QueueChatModel([
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => 'Compact factual result.'
					],
					'finish_reason' => 'stop'
				]],
				'usage' => [
					'prompt_tokens' => 20,
					'completion_tokens' => 5,
					'total_tokens' => 25
				]
			]
		]);
		$toolResult = AgentToolResult::success(
			'call-1',
			'lookup',
			['query' => 'BASE3'],
			str_repeat('large result ', 20)
		);
		$context = new AgentContext(vars: [
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_AFTER_TOOLS,
			AgentToolLoopContextKeys::TOOL_RESULTS => [$toolResult],
			AgentToolLoopContextKeys::MODEL_RESULTS => [],
			AgentToolLoopContextKeys::CONTEXT_COMPACTIONS => [],
			AgentToolLoopContextKeys::ITERATION => 1,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => ''
		]);
		$stage = new AgentContextCompactionStage(
			minToolResultBytes: 1,
			maxInputBytes: 10000,
			targetSummaryCharacters: 1000
		);

		$this->assertTrue($stage->supports($context));
		$patch = $stage->process($context)->getPatch();

		$this->assertSame('Compact factual result.', $patch[AgentToolLoopContextKeys::TOOL_RESULTS][0]->getOutput());
		$this->assertSame('call-1', $patch[AgentToolLoopContextKeys::TOOL_RESULTS][0]->getCallId());
		$this->assertSame('lookup', $patch[AgentToolLoopContextKeys::TOOL_RESULTS][0]->getToolName());
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::MODEL_RESULTS]);
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::CONTEXT_COMPACTIONS]);
		$this->assertInstanceOf(AgentContextCompaction::class, $patch[AgentToolLoopContextKeys::CONTEXT_COMPACTIONS][0]);
		$this->assertTrue($patch[AgentToolLoopContextKeys::CONTEXT_COMPACTIONS][0]->wasApplied());
	}

	public function testTerminalProviderAnswerSkipsCompactionSecondDecisionAndSemanticVerification(): void {
		$model = new QueueChatModel([[
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => null,
					'tool_calls' => [[
						'id' => 'call-search',
						'function' => [
							'name' => 'terminal_search',
							'arguments' => '{"query":"current result"}'
						]
					]]
				]
			]]
		]]);
		$tool = new TerminalAnswerTool();
		$stages = [
			new AgentModelDecisionStage(),
			$this->createActionPolicyStage(),
			new AgentToolExecutionStage(new RecordingEventManager()),
			new AgentContextCompactionStage(minToolResultBytes: 1),
			new AgentToolObservationStage(),
			new AgentSemanticVerificationStage()
		];

		$result = (new AgentToolOrchestrator(null, null, $stages))->run(
			$model,
			[['role' => 'user', 'content' => 'What happened?']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$this->assertTrue($result->isCompleted());
		$this->assertSame(1, $result->getIterations());
		$this->assertCount(1, $model->getCalls());
		$this->assertCount(0, $result->getContextCompactions());
		$this->assertStringContainsString('provider-produced answer', $result->getFinalResponseInstruction());
		$this->assertSame(AgentToolLoopContextKeys::FINAL_RESPONSE_COMPLETE, $result->getFinalResponseMode());
	}

	public function testSecondModelFailureAfterObservationRecoversToPartialFinalResponse(): void {
		$model = new QueueChatModel([
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => null,
						'tool_calls' => [[
							'id' => 'call-1',
							'function' => ['name' => 'lookup', 'arguments' => '{}']
						]]
					]
				]]
			],
			new \RuntimeException('provider timeout')
		]);
		$tool = new LookupTool();
		$stages = [
			new AgentModelDecisionStage(),
			$this->createActionPolicyStage(),
			new AgentToolExecutionStage(new RecordingEventManager()),
			new AgentToolObservationStage(),
			new AgentSemanticVerificationStage()
		];

		$result = (new AgentToolOrchestrator(null, null, $stages))->run(
			$model,
			[['role' => 'user', 'content' => 'find it']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$this->assertTrue($result->isCompleted());
		$this->assertFalse($result->hasFailure());
		$this->assertSame(AgentToolLoopContextKeys::FINAL_RESPONSE_PARTIAL, $result->getFinalResponseMode());
		$this->assertCount(2, $model->getCalls());
		$this->assertStringContainsString('available conversation and tool observations', $result->getFinalResponseInstruction());
	}

	public function testProviderTerminalAnswerBypassesFinalModelCall(): void {
		$model = new QueueChatModel([]);
		$answer = 'The provider already supplied the answer.';
		$messages = [[
			'role' => 'tool',
			'tool_call_id' => 'call-terminal',
			'content' => (string)json_encode([
				'ok' => true,
				'answer' => $answer,
				'final_answer_ready' => true
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		]];
		$orchestrationResult = new AgentToolOrchestratorResult(
			messages: $messages,
			finalAssistantMessage: null,
			completed: true,
			iterations: 1,
			finalResponseMode: AgentToolOrchestratorResult::FINAL_RESPONSE_COMPLETE,
			executionStatus: AgentExecutionStatus::COMPLETED
		);
		$turnResult = new AgentAssistantTurnResult(
			messages: $messages,
			userMessage: ['role' => 'user', 'content' => 'question'],
			memories: [],
			nodeId: 'assistant',
			assistantMessageId: 'msg-terminal',
			memoryWriteEnabled: false,
			orchestrationResult: $orchestrationResult,
			completed: true
		);
		$service = new AgentAssistantFinalResponseService(new AgentAssistantMessageFactory());

		$this->assertSame($answer, $service->createDirectResponse($model, $turnResult));
		$this->assertCount(0, $model->getCalls());

		$chunks = [];
		$this->assertSame($answer, $service->createStreamingResponse(
			$model,
			$turnResult,
			static function(string $chunk) use (&$chunks): void {
				$chunks[] = $chunk;
			}
		));
		$this->assertSame([$answer], $chunks);
		$this->assertCount(0, $model->getCalls());
	}

	public function testOrchestratorRecordsExecutedAndSkippedStagesAndEmitsLiveEvents(): void {
		$model = new QueueChatModel([
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => 'done'
					]
				]]
			]
		]);
		$events = [];

		$result = (new AgentToolOrchestrator(null, null, $this->createDefaultTestStages()))->run(
			$model,
			[['role' => 'user', 'content' => 'hello']],
			[],
			[],
			new AgentContext(),
			function(string $event, array $payload) use (&$events): void {
				$events[] = ['event' => $event, 'payload' => $payload];
			}
		);

		$this->assertCount(4, $result->getStageTrace());
		$this->assertInstanceOf(AgentStageTraceEntry::class, $result->getStageTrace()[0]);
		$this->assertSame(AgentStageTraceEntry::STATUS_COMPLETED, $result->getStageTrace()[0]->getStatus());
		$this->assertSame(AgentStageTraceEntry::STATUS_SKIPPED, $result->getStageTrace()[1]->getStatus());
		$this->assertSame(AgentStageTraceEntry::STATUS_SKIPPED, $result->getStageTrace()[2]->getStatus());
		$this->assertSame(AgentStageTraceEntry::STATUS_SKIPPED, $result->getStageTrace()[3]->getStatus());
		$this->assertSame(['stage.started', 'stage.finished'], array_column($events, 'event'));
		$this->assertSame(IAgentStage::AI_USAGE_REQUIRED, $events[0]['payload']['ai_usage']);
		$this->assertSame('done', $result->getFinalAssistantMessage()['content']);
		$this->assertSame('', $result->getFinalOutputContent());
		$this->assertSame(AgentToolOrchestratorResult::FINAL_RESPONSE_COMPLETE, $result->getFinalResponseMode());
		$this->assertSame(AgentToolOrchestratorResult::FINAL_OUTPUT_DELIVERY_NONE, $result->getFinalOutputDelivery());
		$this->assertCount(2, $result->getBudgetAssessments());
		$this->assertInstanceOf(AgentBudgetAssessment::class, $result->getBudgetAssessments()[0]);
		$this->assertSame('model', $result->getBudgetAssessments()[0]->getMetadata()['checkpoint']);
		$this->assertSame('final', $result->getBudgetAssessments()[1]->getMetadata()['checkpoint']);
	}


	public function testLegacyToolExecutionDoesNotDispatchPersistentToolEvents(): void {
		$model = new QueueChatModel([
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => null,
						'tool_calls' => [[
							'id' => 'call-event-1',
							'function' => [
								'name' => 'lookup',
								'arguments' => '{"query":"event"}'
							]
						]]
					]
				]]
			],
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => 'done'
					]
				]]
			]
		]);
		$eventManager = new RecordingEventManager();
		$tool = new LookupTool();
		$stages = $this->createDefaultTestStages();
		$stages[2] = new AgentToolExecutionStage($eventManager);

		(new AgentToolOrchestrator(null, null, $stages))->run(
			$model,
			[['role' => 'user', 'content' => 'find event']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$this->assertSame([], $eventManager->getFiredEvents());
	}

	public function testLoopLimitAllowsPartialFinalResponse(): void {
		$model = new QueueChatModel([
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => null,
						'tool_calls' => [[
							'id' => 'call-limit-1',
							'function' => [
								'name' => 'lookup',
								'arguments' => '{"query":"partial"}'
							]
						]]
					]
				]]
			]
		]);
		$tool = new LookupTool();

		$result = (new AgentToolOrchestrator(null, null, $this->createDefaultTestStages()))->run(
			$model,
			[['role' => 'user', 'content' => 'find partial data']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext(),
			null,
			1
		);

		$this->assertFalse($result->isCompleted());
		$this->assertSame('max_tool_loops', $result->getFailureCode());
		$this->assertTrue($result->canGenerateFinalResponse());
		$this->assertTrue($result->isPartialFinalResponse());
		$this->assertSame(AgentToolOrchestratorResult::FINAL_RESPONSE_PARTIAL, $result->getFinalResponseMode());
		$this->assertCount(1, $result->getToolCalls());
		$this->assertSame(1, $result->getFailureDetail()['executed_tool_calls']);
	}

	public function testModelFailureIsReturnedAsIncompleteResult(): void {
		$model = new QueueChatModel([
			new \RuntimeException('model unavailable')
		]);

		$result = (new AgentToolOrchestrator(null, null, $this->createDefaultTestStages()))->run(
			$model,
			[['role' => 'user', 'content' => 'hello']],
			[],
			[],
			new AgentContext()
		);

		$this->assertFalse($result->isCompleted());
		$this->assertSame('model_raw_error', $result->getFailureCode());
		$this->assertSame(1, $result->getIterations());
		$this->assertSame('model unavailable', $result->getFailureDetail()['message']);
		$this->assertStringContainsString('Model call failed: model unavailable', $result->getFailureMessage());
	}
}

require_once __DIR__ . '/RecordingEventManager.php';
require_once __DIR__ . '/QueueChatModel.php';
require_once __DIR__ . '/LookupTool.php';
require_once __DIR__ . '/TerminalAnswerTool.php';
require_once __DIR__ . '/ToolResultEnrichmentStage.php';

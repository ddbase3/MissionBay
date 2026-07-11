<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentBudgetAssessment;
use AssistantFoundation\Dto\AgentContextAssessment;
use AssistantFoundation\Dto\AgentContextCompaction;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentStageTraceEntry;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;
use Base3\Event\Api\IEventManager;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentBudgetGuardStage;
use MissionBay\Orchestrator\Stage\AgentContextAssessmentStage;
use MissionBay\Orchestrator\Stage\AgentContextCompactionStage;
use MissionBay\Orchestrator\Stage\AgentFinalAnswerStage;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentResultVerificationStage;
use MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use PHPUnit\Framework\TestCase;

final class AgentToolOrchestratorStageTest extends TestCase {

	/**
	 * @return array<int,IAgentStage>
	 */
	private function createDefaultTestStages(): array {
		return [
			new AgentBudgetGuardStage(),
			new AgentModelDecisionStage(),
			new AgentActionPolicyStage(
				new StaticAgentActionPolicyResolver([new AllowAllAgentActionPolicy()]),
				'action-policy',
				'action-policy',
				['allow-all-actions']
			),
			new AgentBudgetGuardStage(
				'tool-budget-guard',
				'tool-budget-guard',
				AgentBudgetGuardStage::CHECKPOINT_TOOLS
			),
			new AgentToolExecutionStage(new RecordingEventManager()),
			new AgentContextAssessmentStage(),
			new AgentResultVerificationStage(),
			new AgentToolObservationStage(),
			new AgentFinalAnswerStage()
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
		$policyStage = new AgentActionPolicyStage(
			new StaticAgentActionPolicyResolver([new AllowAllAgentActionPolicy()]),
			'action-policy',
			'action-policy',
			['allow-all-actions']
		);
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
		$this->assertSame('done', $result->getFinalOutputContent());
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
		$this->assertSame('final answer', $result->getFinalOutputContent());
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

		$this->assertCount(9, $result->getStageTrace());
		$this->assertInstanceOf(AgentStageTraceEntry::class, $result->getStageTrace()[0]);
		$this->assertSame(AgentStageTraceEntry::STATUS_COMPLETED, $result->getStageTrace()[0]->getStatus());
		$this->assertSame(AgentStageTraceEntry::STATUS_COMPLETED, $result->getStageTrace()[1]->getStatus());
		$this->assertSame(AgentStageTraceEntry::STATUS_SKIPPED, $result->getStageTrace()[2]->getStatus());
		$this->assertSame([
			'stage.started',
			'stage.finished',
			'stage.started',
			'stage.finished',
			'stage.started',
			'token',
			'stage.finished'
		], array_column($events, 'event'));
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $events[0]['payload']['ai_usage']);
		$this->assertSame(IAgentStage::AI_USAGE_REQUIRED, $events[2]['payload']['ai_usage']);
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $events[4]['payload']['ai_usage']);
		$this->assertSame('done', $events[5]['payload']['text']);
		$this->assertCount(1, $result->getBudgetAssessments());
		$this->assertInstanceOf(AgentBudgetAssessment::class, $result->getBudgetAssessments()[0]);
	}


	public function testToolExecutionDispatchesPersistentToolEvents(): void {
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
		$stages[4] = new AgentToolExecutionStage($eventManager);

		(new AgentToolOrchestrator(null, null, $stages))->run(
			$model,
			[['role' => 'user', 'content' => 'find event']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$this->assertCount(2, $eventManager->getFiredEvents());
		$this->assertInstanceOf(MissionBayToolStartedEvent::class, $eventManager->getFiredEvents()[0]);
		$this->assertInstanceOf(MissionBayToolFinishedEvent::class, $eventManager->getFiredEvents()[1]);
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
	}
}


final class RecordingEventManager implements IEventManager {

	/** @var array<int,object|string> */
	private array $firedEvents = [];

	public function on(string $event, callable $listener, int $priority = 0): void {
	}

	public function once(string $event, callable $listener, int $priority = 0): void {
	}

	public function off(string $event, callable $listener): void {
	}

	public function fire(object|string $event, ...$args): array {
		$this->firedEvents[] = $event;

		return [];
	}

	/** @return array<int,object|string> */
	public function getFiredEvents(): array {
		return $this->firedEvents;
	}
}

final class QueueChatModel implements IAiChatModel {

	use NormalizedChatModelTrait;

	/**
	 * @var array<int,mixed>
	 */
	private array $responses;

	/**
	 * @var array<int,array{0:array,1:array}>
	 */
	private array $calls = [];

	/**
	 * @param array<int,mixed> $responses
	 */
	public function __construct(array $responses) {
		$this->responses = $responses;
	}

	public function chat(array $messages): string {
		return '';
	}

	public function raw(array $messages, array $tools = []): mixed {
		$this->calls[] = [$messages, $tools];
		$response = array_shift($this->responses);

		if ($response instanceof \Throwable) {
			throw $response;
		}

		return $response;
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
	}

	public function setOptions(array $options): void {
	}

	public function getOptions(): array {
		return [];
	}

	/**
	 * @return array<int,array{0:array,1:array}>
	 */
	public function getCalls(): array {
		return $this->calls;
	}
}

final class LookupTool implements IAgentTool {

	public static function getName(): string {
		return 'lookuptool';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Lookup',
			'function' => [
				'name' => 'lookup',
				'description' => 'Looks up one value.',
				'parameters' => [
					'type' => 'object',
					'properties' => []
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return [
			'found' => $arguments['query'] ?? 'default'
		];
	}
}

final class ToolResultEnrichmentStage implements IAgentStage {

	private int $executions = 0;

	public static function getName(): string {
		return 'toolresultenrichmentstage';
	}

	public function id(): string {
		return 'tool-result-enrichment';
	}

	public function name(): string {
		return 'tool-result-enrichment';
	}

	public function getDescription(): string {
		return 'Enriches test tool results.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_AFTER_TOOLS;
	}

	public function process(IAgentContext $context): AgentStageResult {
		$this->executions++;
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$enriched = [];

		foreach ($toolResults as $toolResult) {
			if (!$toolResult instanceof AgentToolResult) {
				continue;
			}

			$enriched[] = AgentToolResult::success(
				$toolResult->getCallId(),
				$toolResult->getToolName(),
				$toolResult->getArguments(),
				'stage enrichment',
				$toolResult->getMetadata()
			);
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::TOOL_RESULTS => $enriched
		]);
	}

	public function getExecutions(): int {
		return $this->executions;
	}
}

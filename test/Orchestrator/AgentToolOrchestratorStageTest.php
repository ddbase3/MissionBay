<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentStageResult;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentToolOrchestratorStageTest extends TestCase {

	public function testDefaultStagesImplementFoundationContract(): void {
		$modelStage = new AgentModelDecisionStage();
		$toolStage = new AgentToolExecutionStage();

		$this->assertInstanceOf(IAgentStage::class, $modelStage);
		$this->assertInstanceOf(IAgentStage::class, $toolStage);
		$this->assertSame('agentmodeldecisionstage', AgentModelDecisionStage::getName());
		$this->assertSame('model-decision', $modelStage->id());
		$this->assertSame('model-decision', $modelStage->name());
		$this->assertSame('agenttoolexecutionstage', AgentToolExecutionStage::getName());
		$this->assertSame('tool-execution', $toolStage->id());
		$this->assertSame('tool-execution', $toolStage->name());
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

		$result = (new AgentToolOrchestrator())->run(
			$model,
			$messages,
			[],
			[],
			$context
		);

		$this->assertTrue($result->isCompleted());
		$this->assertSame(1, $result->getIterations());
		$this->assertSame($messages, $result->getMessages());
		$this->assertSame('done', $result->getFinalAssistantMessage()['content']);
		$this->assertSame([], $result->getToolCalls());
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

		$result = (new AgentToolOrchestrator())->run(
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
		$enrichmentStage = new ContextEnrichmentStage();
		$context = new AgentContext();
		$orchestrator = new AgentToolOrchestrator(null, null, [
			new AgentModelDecisionStage(),
			new AgentToolExecutionStage(),
			$enrichmentStage
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
		$this->assertSame('stage enrichment', $model->getCalls()[1][0][3]['content']);
	}

	public function testModelFailureIsReturnedAsIncompleteResult(): void {
		$model = new QueueChatModel([
			new \RuntimeException('model unavailable')
		]);

		$result = (new AgentToolOrchestrator())->run(
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

final class QueueChatModel implements IAiChatModel {

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

final class ContextEnrichmentStage implements IAgentStage {

	private int $executions = 0;

	public static function getName(): string {
		return 'contextenrichmentstage';
	}

	public function id(): string {
		return 'context-enrichment';
	}

	public function name(): string {
		return 'context-enrichment';
	}

	public function supports(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_AFTER_TOOLS;
	}

	public function process(IAgentContext $context): AgentStageResult {
		$this->executions++;
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$messages[] = [
			'role' => 'system',
			'content' => 'stage enrichment'
		];

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::MESSAGES => $messages
		]);
	}

	public function getExecutions(): int {
		return $this->executions;
	}
}

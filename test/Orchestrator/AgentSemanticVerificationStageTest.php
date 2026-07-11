<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentToolResult;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentSemanticVerificationStageTest extends TestCase {

	public function testStageSkipsDuringNormalToolIteration(): void {
		$context = $this->createContext(
			new SemanticVerificationQueueModel([]),
			[AgentToolResult::success('call-1', 'lookup', [], ['found' => true])]
		);
		$context->setVar(AgentToolLoopContextKeys::PHASE, AgentToolLoopContextKeys::PHASE_OBSERVED);
		$context->setVar(AgentToolLoopContextKeys::COMPLETED, false);

		$this->assertFalse((new AgentSemanticVerificationStage())->supports($context));
	}

	public function testStageRunsOnceForTerminalCandidateWithObservations(): void {
		$model = new SemanticVerificationQueueModel([[
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => json_encode([
						'verdict' => 'verified',
						'summary' => 'The plugin detail result is sufficient.',
						'issues' => [],
						'recommendation' => 'answer',
						'confidence' => 0.91
					])
			],
				'finish_reason' => 'stop'
			]],
			'usage' => [
				'prompt_tokens' => 20,
				'completion_tokens' => 10,
				'total_tokens' => 30
			]
		]]);
		$observation = AgentToolResult::success(
			'call-1',
			'plugin-detail',
			['query' => 'Igor2Mail'],
			['active' => 1],
			['iteration' => 2]
		);
		$context = $this->createContext($model, [$observation], 3);
		$stage = new AgentSemanticVerificationStage();

		$this->assertTrue($stage->supports($context));
		$result = $stage->process($context);
		$patch = $result->getPatch();
		$verification = $patch[AgentToolLoopContextKeys::RESULT_VERIFICATIONS][0];

		$this->assertInstanceOf(AgentResultVerification::class, $verification);
		$this->assertTrue($verification->isVerified());
		$this->assertSame('answer', $verification->getMetadata()['recommendation']);
		$this->assertSame(0.91, $verification->getMetadata()['confidence']);
		$this->assertSame(30, $patch[AgentToolLoopContextKeys::MODEL_RESULTS][0]['usage']['total_tokens']);
		$this->assertSame('valid', $result->getMetadata()['parse_status']);
		$this->assertArrayNotHasKey(AgentToolLoopContextKeys::PHASE, $patch);
		$this->assertArrayNotHasKey(AgentToolLoopContextKeys::COMPLETED, $patch);
	}

	public function testVerifierReceivesAllCommittedObservationsAndNoCurrentResultSet(): void {
		$model = new SemanticVerificationQueueModel([[
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => json_encode([
						'verdict' => 'verified',
						'summary' => 'Accumulated evidence is sufficient.',
						'issues' => [],
						'recommendation' => 'answer',
						'confidence' => 0.9
					])
				]
			]]
		]]);
		$first = AgentToolResult::success(
			'call-summary',
			'plugin-summary',
			['query' => 'Igor2Mail'],
			['installed' => true],
			['iteration' => 1]
		);
		$second = AgentToolResult::success(
			'call-detail',
			'plugin-detail',
			['query' => 'Igor2Mail'],
			['active' => 1],
			['iteration' => 2]
		);
		$context = $this->createContext($model, [$first, $second], 3);

		(new AgentSemanticVerificationStage())->process($context);
		$calls = $model->getCalls();
		$payload = json_decode($calls[0][0][1]['content'], true);

		$this->assertCount(2, $payload['previous_observations']);
		$this->assertSame([], $payload['current_tool_results']);
		$this->assertSame(2, $payload['evidence_count']);
	}

	public function testParserAcceptsNestedAliasesAndPercentageConfidence(): void {
		$model = new SemanticVerificationQueueModel([[
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => json_encode([
						'assessment' => [
							'status' => 'sufficient',
							'reason' => 'The evidence supports an answer.',
							'decision' => 'finish',
							'score' => '88%',
							'gaps' => []
						]
					])
				]
			]]
		]]);
		$context = $this->createContext($model, [
			AgentToolResult::success('call-1', 'lookup', [], ['found' => true])
		]);
		$verification = (new AgentSemanticVerificationStage())
			->process($context)
			->getPatch()[AgentToolLoopContextKeys::RESULT_VERIFICATIONS][0];

		$this->assertTrue($verification->isVerified());
		$this->assertSame('answer', $verification->getMetadata()['recommendation']);
		$this->assertSame(0.88, $verification->getMetadata()['confidence']);
	}

	public function testMalformedVerifierResponseIsInconclusiveWithoutReopeningByItself(): void {
		$model = new SemanticVerificationQueueModel([[
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => 'not valid verification json'
				]
			]]
		]]);
		$context = $this->createContext($model, [
			AgentToolResult::success('call-1', 'lookup', [], ['found' => true])
		]);
		$result = (new AgentSemanticVerificationStage())->process($context);
		$verification = $result->getPatch()[AgentToolLoopContextKeys::RESULT_VERIFICATIONS][0];

		$this->assertSame(AgentResultVerification::VERDICT_INCONCLUSIVE, $verification->getVerdict());
		$this->assertSame('invalid_semantic_verifier_response', $verification->getIssues()[0]['code']);
		$this->assertSame('invalid', $result->getMetadata()['parse_status']);
		$this->assertArrayNotHasKey(AgentToolLoopContextKeys::FAILURE_CODE, $result->getPatch());
	}

	/**
	 * @param array<int,AgentToolResult> $observations
	 */
	private function createContext(IAiChatModel $model, array $observations, int $iteration = 1): AgentContext {
		return new AgentContext(vars: [
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::MESSAGES => [
				['role' => 'system', 'content' => 'You are a test assistant.'],
				['role' => 'user', 'content' => 'Check the plugin status.']
			],
			AgentToolLoopContextKeys::TOOL_RESULTS => [],
			AgentToolLoopContextKeys::OBSERVATIONS => $observations,
			AgentToolLoopContextKeys::MODEL_RESULTS => [],
			AgentToolLoopContextKeys::RESULT_VERIFICATIONS => [],
			AgentToolLoopContextKeys::LOGGER => null,
			AgentToolLoopContextKeys::ITERATION => $iteration,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL,
			AgentToolLoopContextKeys::COMPLETED => true,
			AgentToolLoopContextKeys::FAILURE_CODE => ''
		]);
	}
}

final class SemanticVerificationQueueModel implements IAiChatModel {

	use NormalizedChatModelTrait;

	/** @var array<int,mixed> */
	private array $responses;

	/** @var array<int,array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}> */
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

	/**
	 * @return array<int,array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}>
	 */
	public function getCalls(): array {
		return $this->calls;
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
	}

	public function setOptions(array $options): void {
	}

	public function getOptions(): array {
		return [];
	}
}

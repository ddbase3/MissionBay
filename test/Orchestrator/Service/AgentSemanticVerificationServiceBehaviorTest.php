<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Service;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use Base3\Logger\Api\ILogger;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\Service\AgentSemanticVerificationService;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentSemanticVerificationServiceBehaviorTest extends TestCase {

	public function testMissingModelProducesInconclusiveResultWithoutModelCalls(): void {
		$result = (new AgentSemanticVerificationService())->verify(new AgentContext());
		$this->assertSame('inconclusive', $result->getMetadata()['parse_status']);
		$this->assertSame('semantic_verifier_model_missing', $this->verification($result)->getIssues()[0]['code']);
	}

	public function testNonNormalizedObservationIsRejectedBeforeModelCall(): void {
		$model = $this->createMock(IAiChatModel::class);
		$model->expects($this->never())->method('complete');
		$result = (new AgentSemanticVerificationService())->verify($this->context($model, [new \stdClass()]));
		$this->assertSame('invalid_tool_result_type', $this->verification($result)->getIssues()[0]['code']);
	}

	public function testEmptyVerifierOutputIsInconclusive(): void {
		$result = $this->runReply(' ');
		$this->assertSame('invalid', $result->getMetadata()['parse_status']);
		$this->assertSame('invalid_semantic_verifier_response', $this->verification($result)->getIssues()[0]['code']);
	}

	public function testMalformedJsonDoesNotProduceVerifiedVerdict(): void {
		$result = $this->runReply('{bad json}');
		$this->assertSame(AgentResultVerification::VERDICT_INCONCLUSIVE, $this->verification($result)->getVerdict());
	}

	public function testMissingVerdictIsInconclusive(): void {
		$result = $this->runReply('{"summary":"looks fine"}');
		$this->assertSame('invalid', $result->getMetadata()['parse_status']);
	}

	public function testMissingSummaryIsInconclusive(): void {
		$result = $this->runReply('{"verdict":"verified","summary":"  "}');
		$this->assertSame('invalid', $result->getMetadata()['parse_status']);
	}

	public function testVerifiedAliasAndValidSummaryAreNormalized(): void {
		$result = $this->runReply('{"verdict":" PASS ","summary":" Evidence complete ","recommendation":"respond"}');
		$this->assertTrue($this->verification($result)->isVerified());
		$this->assertSame('Evidence complete', $this->verification($result)->getSummary());
		$this->assertSame('answer', $this->verification($result)->getMetadata()['recommendation']);
	}

	public function testFailedAliasRecommendsMoreTools(): void {
		$result = $this->runReply('{"status":"incomplete","reason":"Missing evidence","next_action":"more_tools"}');
		$this->assertSame(AgentResultVerification::VERDICT_FAILED, $this->verification($result)->getVerdict());
		$this->assertSame('continue', $this->verification($result)->getMetadata()['recommendation']);
	}

	public function testUncertainAliasNormalizesToInconclusive(): void {
		$result = $this->runReply('{"verdict":"uncertain","summary":"Cannot verify","recommendation":"ask_user"}');
		$this->assertSame(AgentResultVerification::VERDICT_INCONCLUSIVE, $this->verification($result)->getVerdict());
		$this->assertSame('clarify', $this->verification($result)->getMetadata()['recommendation']);
	}

	public function testUnknownRecommendationIsNotInvented(): void {
		$result = $this->runReply('{"verdict":"verified","summary":"Done","recommendation":"run_secret_tool"}');
		$this->assertSame('unknown', $this->verification($result)->getMetadata()['recommendation']);
	}

	public function testFencedJsonAndNestedVerificationAreAccepted(): void {
		$result = $this->runReply("```json\n{\"verification\":{\"verdict\":\"verified\",\"summary\":\"Valid\"}}\n```");
		$this->assertSame('valid', $result->getMetadata()['parse_status']);
		$this->assertTrue($this->verification($result)->isVerified());
	}

	public function testPercentageConfidenceIsConvertedToFraction(): void {
		$result = $this->runReply('{"verdict":"verified","summary":"Done","confidence":"75%"}');
		$this->assertSame(0.75, $this->verification($result)->getMetadata()['confidence']);
	}

	public function testNumericConfidenceAboveOneIsConvertedFromPercentage(): void {
		$result = $this->runReply('{"verdict":"verified","summary":"Done","confidence":88}');
		$this->assertSame(0.88, $this->verification($result)->getMetadata()['confidence']);
	}

	public function testConfidenceClampsNegativeValues(): void {
		$result = $this->runReply('{"verdict":"verified","summary":"Done","confidence":-10}');
		$this->assertSame(0.0, $this->verification($result)->getMetadata()['confidence']);
	}

	public function testConfidenceClampsValuesAboveOneHundred(): void {
		$result = $this->runReply('{"verdict":"verified","summary":"Done","confidence":140}');
		$this->assertSame(1.0, $this->verification($result)->getMetadata()['confidence']);
	}

	public function testInvalidConfidenceBecomesNull(): void {
		$result = $this->runReply('{"verdict":"verified","summary":"Done","confidence":"unknown"}');
		$this->assertNull($this->verification($result)->getMetadata()['confidence']);
	}

	public function testIssueNormalizationIgnoresMalformedEntriesAndPreservesDetail(): void {
		$result = $this->runReply(json_encode(['verdict' => 'failed', 'summary' => 'Gaps', 'issues' => [false, ['code' => '', 'message' => '  Missing fact  ', 'detail' => 'field A'], ['code' => 'empty', 'message' => ' ']]]));
		$issues = $this->verification($result)->getIssues();
		$this->assertCount(1, $issues);
		$this->assertSame('semantic_issue_1', $issues[0]['code']);
		$this->assertSame('Missing fact', $issues[0]['message']);
		$this->assertSame(['value' => 'field A'], $issues[0]['detail']);
	}

	public function testModelExceptionIsInconclusiveAndLogged(): void {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willThrowException(new \RuntimeException('provider offline'));
		$logs = [];
		$logger = $this->createStub(ILogger::class);
		$logger->method('log')->willReturnCallback(static function(string $scope, string $message, ?int $timestamp = null) use (&$logs): bool {
			$logs[] = [$scope, $message];
			return true;
		});
		$context = $this->context($model);
		$context->setVar(Keys::LOGGER, $logger);
		$result = (new AgentSemanticVerificationService())->verify($context);
		$this->assertSame('semantic_verifier_error', $this->verification($result)->getIssues()[0]['code']);
		$this->assertSame('agentsemanticverificationstage', $logs[0][0]);
		$this->assertStringContainsString('provider offline', $logs[0][1]);
	}

	public function testModelMetadataIsAppendedWithoutDroppingExistingData(): void {
		$model = $this->model('{"verdict":"verified","summary":"Done"}');
		$context = $this->context($model);
		$context->setVar(Keys::MODEL_RESULTS, [['provider' => 'earlier']]);
		$result = (new AgentSemanticVerificationService())->verify($context);
		$this->assertCount(2, $result->getPatch()[Keys::MODEL_RESULTS]);
		$this->assertSame('earlier', $result->getPatch()[Keys::MODEL_RESULTS][0]['provider']);
		$this->assertSame('verification-provider', $result->getPatch()[Keys::MODEL_RESULTS][1]['provider']);
	}

	public function testEarlierVerificationIsPreserved(): void {
		$earlier = new AgentResultVerification(1, 'test', AgentResultVerification::VERDICT_INCONCLUSIVE, 'Earlier');
		$context = $this->context($this->model('{"verdict":"verified","summary":"Done"}'));
		$context->setVar(Keys::RESULT_VERIFICATIONS, [$earlier]);
		$context->setVar(Keys::ITERATION, 3);
		$result = (new AgentSemanticVerificationService())->verify($context);
		$this->assertCount(2, $result->getPatch()[Keys::RESULT_VERIFICATIONS]);
		$this->assertSame($earlier, $result->getPatch()[Keys::RESULT_VERIFICATIONS][0]);
		$this->assertSame(3, $this->verification($result)->getIteration());
	}

	public function testVerifierReceivesLastUserTaskAndNormalizedObservation(): void {
		$captured = [];
		$model = $this->captureModel($captured);
		$context = $this->context($model, [AgentToolResult::success('call1', 'lookup', ['id' => '8'], ['status' => 'ok'], ['iteration' => 2])]);
		$context->setVar(Keys::MESSAGES, [['role' => 'user', 'content' => 'Old question'], ['role' => 'assistant', 'content' => 'Previous claim'], ['role' => 'user', 'content' => 'Newest question']]);
		(new AgentSemanticVerificationService())->verify($context);
		$payload = json_decode($captured[1]['content'], true);
		$this->assertSame('Newest question', $payload['task']);
		$this->assertSame(1, $payload['evidence_count']);
		$this->assertSame('lookup', $payload['previous_observations'][0]['tool']);
		$this->assertFalse($payload['previous_observations'][0]['current_iteration']);
	}

	public function testEmptyInputArraysAreSafelyNormalized(): void {
		$captured = [];
		$model = $this->captureModel($captured);
		$context = $this->context($model);
		$context->setVar(Keys::MESSAGES, 'not array');
		$context->setVar(Keys::OBSERVATIONS, null);
		$context->setVar(Keys::TOOL_DEFINITIONS, false);
		(new AgentSemanticVerificationService())->verify($context);
		$payload = json_decode($captured[1]['content'], true);
		$this->assertSame('', $payload['task']);
		$this->assertSame([], $payload['previous_observations']);
		$this->assertSame([], $payload['available_tools']);
	}

	public function testOversizedTaskIsTruncatedAndMarked(): void {
		$captured = [];
		$model = $this->captureModel($captured);
		$context = $this->context($model);
		$context->setVar(Keys::MESSAGES, [['role' => 'user', 'content' => str_repeat('A', 15000)]]);
		(new AgentSemanticVerificationService(12000, 1200))->verify($context);
		$payload = json_decode($captured[1]['content'], true);
		$this->assertTrue($payload['input_truncated']);
		$this->assertSame(1200, strlen($payload['task']));
	}

	private function runReply(string $reply): \AssistantFoundation\Dto\AgentStageResult {
		return (new AgentSemanticVerificationService())->verify($this->context($this->model($reply)));
	}

	private function model(string $reply): IAiChatModel {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willReturn(new AiChatResult($reply, [], new AiResultMetadata('chat', 'verification-provider')));
		return $model;
	}

	private function captureModel(array &$captured): IAiChatModel {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willReturnCallback(static function(array $messages, array $tools = []) use (&$captured): AiChatResult {
			$captured = $messages;
			return new AiChatResult('{"verdict":"verified","summary":"Done"}', [], new AiResultMetadata('chat', 'verification-provider'));
		});
		return $model;
	}

	private function context(?IAiChatModel $model, array $observations = []): AgentContext {
		return new AgentContext(vars: [
			Keys::MODEL => $model,
			Keys::MESSAGES => [['role' => 'user', 'content' => 'What happened?']],
			Keys::OBSERVATIONS => $observations,
			Keys::TOOL_DEFINITIONS => [['type' => 'function', 'function' => ['name' => 'lookup', 'description' => 'Read runtime status']]],
			Keys::ITERATION => 2
		]);
	}

	private function verification(\AssistantFoundation\Dto\AgentStageResult $result): AgentResultVerification {
		$items = $result->getPatch()[Keys::RESULT_VERIFICATIONS];
		return $items[count($items) - 1];
	}
}

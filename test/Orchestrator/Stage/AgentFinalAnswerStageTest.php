<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use MissionBay\Orchestrator\Stage\AgentFinalAnswerStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentFinalAnswerStageTest extends TestCase {

	public function testStageMetadataAndUsageReflectSelectedMode(): void {
		$reuse = new AgentFinalAnswerStage('id', 'human label');
		$this->assertSame('agentfinalanswerstage', AgentFinalAnswerStage::getName());
		$this->assertSame('id', $reuse->id());
		$this->assertSame('human label', $reuse->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $reuse->getAiUsage());
		$this->assertSame(IAgentStage::AI_USAGE_REQUIRED, (new AgentFinalAnswerStage(mode: AgentFinalAnswerStage::MODE_REGENERATE))->getAiUsage());
	}

	public function testUnsupportedModeIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		new AgentFinalAnswerStage(mode: 'silently-fallback');
	}

	public function testSupportsOnlyFinalPhaseWithoutRecordedFailure(): void {
		$stage = new AgentFinalAnswerStage();
		$this->assertTrue($stage->supports($this->context([Keys::PHASE => Keys::PHASE_FINAL])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_TOOLS])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_FINAL, Keys::FAILURE_CODE => 'blocked'])));
	}

	public function testReuseCompletesWithoutReinvokingModelAndEmitsOneToken(): void {
		$events = [];
		$stage = new AgentFinalAnswerStage();
		$context = $this->context([
			Keys::PHASE => Keys::PHASE_FINAL,
			Keys::FINAL_ASSISTANT_MESSAGE => ['content' => '  Answer from model  '],
			Keys::EVENT_CALLBACK => static function(string $event, array $payload) use (&$events): void { $events[] = [$event, $payload]; }
		]);
		$result = $stage->process($context);
		$this->assertSame('Answer from model', $result->getPatch()[Keys::FINAL_OUTPUT_CONTENT]);
		$this->assertTrue($result->getPatch()[Keys::COMPLETED]);
		$this->assertSame(Keys::PHASE_COMPLETE, $result->getPatch()[Keys::PHASE]);
		$this->assertSame('model-decision', $result->getMetadata()['output']['source']);
		$this->assertSame([['token', ['text' => 'Answer from model']]], $events);
	}

	public function testReuseFailsForMissingOrNonScalarTerminalContent(): void {
		$stage = new AgentFinalAnswerStage();
		foreach ([null, ['content' => '  '], ['content' => ['structured' => true]]] as $message) {
			$result = $stage->process($this->context([Keys::FINAL_ASSISTANT_MESSAGE => $message]));
			$this->assertSame('final_answer_missing', $result->getPatch()[Keys::FAILURE_CODE]);
			$this->assertFalse($result->getPatch()[Keys::COMPLETED]);
			$this->assertSame(Keys::PHASE_FAILED, $result->getPatch()[Keys::PHASE]);
		}
	}

	public function testEventTransportFailureDoesNotAlterCompletedAnswer(): void {
		$event = static function(): void { throw new \RuntimeException('transport unavailable'); };
		$result = (new AgentFinalAnswerStage())->process($this->context([
			Keys::FINAL_ASSISTANT_MESSAGE => ['content' => 'Persist the answer'],
			Keys::EVENT_CALLBACK => $event
		]));
		$this->assertSame('Persist the answer', $result->getPatch()[Keys::FINAL_OUTPUT_CONTENT]);
	}

	public function testRegenerateWithMissingModelFallsBackOnlyWhenTerminalContentExists(): void {
		$stage = new AgentFinalAnswerStage(mode: AgentFinalAnswerStage::MODE_REGENERATE);
		$fallback = $stage->process($this->context([Keys::FINAL_ASSISTANT_MESSAGE => ['content' => '  Original  ']]));
		$this->assertSame('Original', $fallback->getPatch()[Keys::FINAL_OUTPUT_CONTENT]);
		$this->assertSame('final_answer_model_missing', $fallback->getMetadata()['output']['warning']);
		$failure = $stage->process($this->context([]));
		$this->assertSame('final_answer_model_missing', $failure->getPatch()[Keys::FAILURE_CODE]);
	}

	public function testRegenerateUsesModelResultAndRecordsMetadata(): void {
		$model = $this->createStub(IAiChatModel::class);
		$messages = [['role' => 'user', 'content' => 'question']];
		$model->method('complete')->willReturnCallback(static function(array $received, array $tools) use ($messages): AiChatResult {
			if ($received !== $messages || $tools !== []) {
				throw new \RuntimeException('Regeneration changed the model input.');
			}
			return new AiChatResult('Fresh answer', [], new AiResultMetadata('chat', 'unit-test'));
		});
		$result = (new AgentFinalAnswerStage(mode: AgentFinalAnswerStage::MODE_REGENERATE))->process($this->context([
			Keys::MODEL => $model, Keys::MESSAGES => $messages, Keys::MODEL_RESULTS => [['old' => true]]
		]));
		$this->assertSame('Fresh answer', $result->getPatch()[Keys::FINAL_OUTPUT_CONTENT]);
		$this->assertSame('final-model-call', $result->getMetadata()['output']['source']);
		$this->assertCount(2, $result->getPatch()[Keys::MODEL_RESULTS]);
		$this->assertSame('unit-test', $result->getPatch()[Keys::MODEL_RESULTS][1]['provider']);
	}

	public function testRegenerateFailureFallsBackToTerminalAnswer(): void {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willThrowException(new \RuntimeException('provider down'));
		$result = (new AgentFinalAnswerStage(mode: AgentFinalAnswerStage::MODE_REGENERATE))->process($this->context([
			Keys::MODEL => $model, Keys::FINAL_ASSISTANT_MESSAGE => ['content' => 'Known answer']
		]));
		$this->assertSame('Known answer', $result->getPatch()[Keys::FINAL_OUTPUT_CONTENT]);
		$this->assertSame('final_answer_generation_failed', $result->getMetadata()['output']['warning']);
		$this->assertSame('provider down', $result->getMetadata()['output']['detail']['message']);
	}

	public function testEmptyRegeneratedResultFallsBackAndRecordsUsage(): void {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willReturn(new AiChatResult('', [], new AiResultMetadata('chat')));
		$result = (new AgentFinalAnswerStage(mode: AgentFinalAnswerStage::MODE_REGENERATE))->process($this->context([
			Keys::MODEL => $model, Keys::FINAL_ASSISTANT_MESSAGE => ['content' => 'Old answer']
		]));
		$this->assertSame('Old answer', $result->getPatch()[Keys::FINAL_OUTPUT_CONTENT]);
		$this->assertSame('final_answer_empty', $result->getMetadata()['output']['warning']);
		$this->assertCount(1, $result->getPatch()[Keys::MODEL_RESULTS]);
	}

	public function testStreamingRegenerationEmitsDeltasAndProviderMetadata(): void {
		$events = [];
		$model = $this->createStub(IAiChatModel::class);
		$model->method('streamResult')->willReturnCallback(static function(array $messages, array $tools, callable $onData, ?callable $onMeta): AiChatResult {
			$onData('First ');
			$onData('second');
			$onMeta(['finish_reason' => 'stop']);
			return new AiChatResult('First second', [], new AiResultMetadata('chat'));
		});
		$result = (new AgentFinalAnswerStage(mode: AgentFinalAnswerStage::MODE_REGENERATE))->process($this->context([
			Keys::MODEL => $model,
			Keys::EVENT_CALLBACK => static function(string $event, array $payload) use (&$events): void { $events[] = [$event, $payload]; }
		]));
		$this->assertSame('First second', $result->getPatch()[Keys::FINAL_OUTPUT_CONTENT]);
		$this->assertSame([['token', ['text' => 'First ']], ['token', ['text' => 'second']], ['meta', ['finish_reason' => 'stop']]], $events);
	}

	public function testInterruptedStreamRetainsEmittedPartialContent(): void {
		$events = [];
		$model = $this->createStub(IAiChatModel::class);
		$model->method('streamResult')->willReturnCallback(static function(array $messages, array $tools, callable $onData, ?callable $onMeta): AiChatResult {
			$onData('Partial');
			throw new \RuntimeException('network lost');
		});
		$result = (new AgentFinalAnswerStage(mode: AgentFinalAnswerStage::MODE_REGENERATE))->process($this->context([
			Keys::MODEL => $model,
			Keys::EVENT_CALLBACK => static function(string $event, array $payload) use (&$events): void { $events[] = [$event, $payload]; }
		]));
		$this->assertSame('partial-stream', $result->getMetadata()['output']['source']);
		$this->assertSame('Partial', $result->getPatch()[Keys::FINAL_OUTPUT_CONTENT]);
		$this->assertSame('stream_interrupted', $result->getMetadata()['output']['warning']);
		$this->assertSame([['token', ['text' => 'Partial']]], $events);
	}

	/** @param array<string,mixed> $variables */
	private function context(array $variables): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $variables[$key] ?? null);
		return $context;
	}
}

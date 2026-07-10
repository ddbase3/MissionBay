<?php declare(strict_types=1);

namespace MissionBay\Test\Ai;

use MissionBay\Ai\AiResultNormalizer;
use PHPUnit\Framework\TestCase;

final class AiResultNormalizerTest extends TestCase {

	public function testNormalizesOpenAiChatWithToolCallAndUsage(): void {
		$result = AiResultNormalizer::chat([
			'id' => 'req-openai',
			'model' => 'gpt-test',
			'choices' => [[
				'finish_reason' => 'tool_calls',
				'message' => [
					'role' => 'assistant',
					'content' => '',
					'tool_calls' => [[
						'id' => 'call-1',
						'type' => 'function',
						'function' => [
							'name' => 'lookup',
							'arguments' => '{"query":"BASE3"}'
						]
					]]
				]
			]],
			'usage' => [
				'prompt_tokens' => 12,
				'completion_tokens' => 5,
				'total_tokens' => 17,
				'prompt_tokens_details' => ['cached_tokens' => 3]
			]
		], ['provider' => 'openai']);

		$this->assertTrue($result->hasToolCalls());
		$this->assertSame('lookup', $result->getToolCalls()[0]->getName());
		$this->assertSame(['query' => 'BASE3'], $result->getToolCalls()[0]->getArguments());
		$this->assertSame(12, $result->getMetadata()->getUsage()->getInputTokens());
		$this->assertSame(5, $result->getMetadata()->getUsage()->getOutputTokens());
		$this->assertSame(3, $result->getMetadata()->getUsage()->getCachedInputTokens());
	}

	public function testNormalizesAnthropicChat(): void {
		$result = AiResultNormalizer::chat([
			'id' => 'msg-1',
			'model' => 'claude-test',
			'stop_reason' => 'tool_use',
			'content' => [
				['type' => 'text', 'text' => 'I will check.'],
				[
					'type' => 'tool_use',
					'id' => 'tool-1',
					'name' => 'lookup',
					'input' => ['query' => 'BASE3']
				]
			],
			'usage' => [
				'input_tokens' => 9,
				'output_tokens' => 4
			]
		], ['provider' => 'anthropic']);

		$this->assertSame('I will check.', $result->getContent());
		$this->assertSame('lookup', $result->getToolCalls()[0]->getName());
		$this->assertSame(13, $result->getMetadata()->getUsage()->getTotalTokens());
		$this->assertSame('tool_use', $result->getMetadata()->getFinishReason());
	}

	public function testNormalizesGeminiNativeChat(): void {
		$result = AiResultNormalizer::chat([
			'modelVersion' => 'gemini-test',
			'candidates' => [[
				'finishReason' => 'STOP',
				'content' => [
					'parts' => [
						['text' => 'Done'],
						['functionCall' => ['name' => 'lookup', 'args' => ['query' => 'BASE3']]]
					]
				]
			]],
			'usageMetadata' => [
				'promptTokenCount' => 10,
				'candidatesTokenCount' => 2,
				'totalTokenCount' => 12,
				'thoughtsTokenCount' => 1
			]
		], ['provider' => 'gemini', 'model' => 'gemini-test']);

		$this->assertSame('Done', $result->getContent());
		$this->assertSame('lookup', $result->getToolCalls()[0]->getName());
		$this->assertSame(1, $result->getMetadata()->getUsage()->getReasoningTokens());
		$this->assertSame('STOP', $result->getMetadata()->getFinishReason());
	}
	public function testNormalizesOpenAiResponsesApiAndUsage(): void {
		$result = AiResultNormalizer::chat([
			'id' => 'resp-1',
			'model' => 'gpt-responses',
			'status' => 'completed',
			'output' => [
				[
					'type' => 'message',
					'content' => [
						['type' => 'output_text', 'text' => 'Done']
					]
				],
				[
					'type' => 'function_call',
					'call_id' => 'call-2',
					'name' => 'lookup',
					'arguments' => '{"query":"BASE3"}'
				]
			],
			'usage' => [
				'input_tokens' => 20,
				'output_tokens' => 4,
				'total_tokens' => 24,
				'output_tokens_details' => ['reasoning_tokens' => 2]
			]
		], ['provider' => 'openai']);

		$this->assertSame('Done', $result->getContent());
		$this->assertSame('lookup', $result->getToolCalls()[0]->getName());
		$this->assertSame(24, $result->getMetadata()->getUsage()->getTotalTokens());
		$this->assertSame(2, $result->getMetadata()->getUsage()->getReasoningTokens());
	}

	public function testNormalizesStreamingMetadataWithoutSummingCumulativeUsage(): void {
		$metadata = AiResultNormalizer::streamMetadata([
			[
				'event' => 'meta',
				'full' => [
					'id' => 'stream-1',
					'model' => 'stream-model',
					'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 1, 'total_tokens' => 9]
				]
			],
			[
				'event' => 'meta',
				'finish_reason' => 'stop',
				'full' => [
					'id' => 'stream-1',
					'model' => 'stream-model',
					'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 3, 'total_tokens' => 11]
				]
			]
		], ['provider' => 'test']);

		$this->assertSame(11, $metadata->getUsage()->getTotalTokens());
		$this->assertSame('stream-1', $metadata->getRequestId());
		$this->assertSame('stop', $metadata->getFinishReason());
		$this->assertTrue($metadata->getExtra()['stream']);
	}

}

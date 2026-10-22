<?php declare(strict_types=1);

namespace MissionBay\Test\ChatModel;

use AssistantFoundation\Api\IAiProvider;
use AssistantFoundation\Dto\AiProviderFileReference;
use Base3\Api\IClassMap;
use Base3\Event\Api\IEventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Ai\AgentChatMessageAdapter;
use MissionBay\ChatModel\OpenAiResponsesChatModel;
use PHPUnit\Framework\TestCase;

final class OpenAiResponsesChatModelTest extends TestCase {

	private function makeModel(IAiProvider $provider): OpenAiResponsesChatModel {
		$map = $this->createMock(IClassMap::class);
		$events = new AiProviderRequestEventDispatcher($this->createMock(IEventManager::class));

		return new class($map, $events, $provider) extends OpenAiResponsesChatModel {

			public function __construct(
				IClassMap $classMap,
				AiProviderRequestEventDispatcher $events,
				private readonly IAiProvider $testProvider
			) {
				parent::__construct($classMap, $events);
			}

			protected function getProvider(): IAiProvider {
				return $this->testProvider;
			}
		};
	}

	public function testCreatesResponsesPayloadWithoutUnsupportedSamplingOptions(): void {
		$provider = $this->createMock(IAiProvider::class);
		$provider->expects($this->once())->method('request')
			->with('/v1/responses', $this->callback(function(array $payload): bool {
				$this->assertSame('gpt-6-luna', $payload['model']);
				$this->assertFalse($payload['store']);
				$this->assertSame([['role' => 'user', 'content' => 'Hello']], $payload['input']);
				$this->assertSame(['effort' => 'medium'], $payload['reasoning']);
				$this->assertSame(8192, $payload['max_output_tokens']);
				$this->assertArrayNotHasKey('temperature', $payload);
				$this->assertArrayNotHasKey('top_p', $payload);
				$this->assertArrayNotHasKey('messages', $payload);
				$this->assertArrayNotHasKey('reasoning_effort', $payload);
				return true;
			}))
			->willReturn([
				'id' => 'resp-1', 'status' => 'completed', 'model' => 'gpt-6-luna',
				'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hi']]]]
			]);

		$model = $this->makeModel($provider);
		$model->setOptions([
			'model' => 'gpt-6-luna', 'apikey' => 'test',
			'temperature' => 0.3, 'top_p' => 0.8,
			'max_tokens' => 8192, 'reasoning_effort' => 'medium'
		]);

		$this->assertSame('Hi', $model->complete([['role' => 'user', 'content' => 'Hello']])->getContent());
	}

	public function testConvertsFunctionCallsAndToolResultsForContinuation(): void {
		$provider = $this->createMock(IAiProvider::class);
		$provider->expects($this->once())->method('request')
			->with('/v1/responses', $this->callback(function(array $payload): bool {
				$this->assertSame('call-123', $payload['input'][1]['call_id']);
				$this->assertSame('function_call', $payload['input'][1]['type']);
				$this->assertSame('lookup', $payload['input'][1]['name']);
				$this->assertSame('{"id":7}', $payload['input'][1]['arguments']);
				$this->assertSame('function_call_output', $payload['input'][2]['type']);
				$this->assertSame('call-123', $payload['input'][2]['call_id']);
				$this->assertSame('{"name":"Test"}', $payload['input'][2]['output']);
				$this->assertSame('lookup', $payload['tools'][0]['name']);
				$this->assertSame('function', $payload['tools'][0]['type']);
				$this->assertFalse($payload['tools'][0]['strict']);
				$this->assertSame('auto', $payload['tool_choice']);
				return true;
			}))
			->willReturn(['id' => 'resp-2', 'output' => [], 'status' => 'completed']);

		$model = $this->makeModel($provider);
		$model->setOptions(['model' => 'gpt-6.1-sol', 'apikey' => 'test']);
		$model->raw([
			['role' => 'user', 'content' => 'Check it'],
			['role' => 'assistant', 'content' => '', 'tool_calls' => [[
				'id' => 'call-123', 'type' => 'function',
				'function' => ['name' => 'lookup', 'arguments' => '{"id":7}']
			]]],
			['role' => 'tool', 'tool_call_id' => 'call-123', 'content' => '{"name":"Test"}']
		], [[
			'type' => 'function',
			'function' => ['name' => 'lookup', 'description' => 'Look up an entry',
				'parameters' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]]]
		]]);
	}

	public function testStreamsTextFunctionCallsAndUsage(): void {
		$provider = $this->createMock(IAiProvider::class);
		$provider->expects($this->once())->method('stream')
			->willReturnCallback(function(string $path, array $payload, callable $onChunk): void {
				$this->assertSame('/v1/responses', $path);
				$this->assertTrue($payload['stream']);

				$frames = [
					['type' => 'response.output_text.delta', 'delta' => 'Hello '],
					['type' => 'response.output_text.delta', 'delta' => 'world'],
					['type' => 'response.output_item.done', 'output_index' => 1, 'item' => [
						'type' => 'function_call', 'call_id' => 'call-9', 'name' => 'lookup',
						'arguments' => '{"query":"BASE3"}'
					]],
					['type' => 'response.completed', 'response' => [
						'id' => 'resp-stream', 'model' => 'gpt-6-luna', 'status' => 'completed',
						'output' => [['type' => 'function_call', 'call_id' => 'call-9', 'name' => 'lookup',
							'arguments' => '{"query":"BASE3"}']],
						'usage' => ['input_tokens' => 15, 'output_tokens' => 11, 'total_tokens' => 26]
					]]
				];

				$stream = '';
				foreach($frames as $frame) {
					$stream .= 'data: ' . json_encode($frame) . "\n\n";
				}

				$onChunk(substr($stream, 0, 19));
				$onChunk(substr($stream, 19, 57));
				$onChunk(substr($stream, 76));
			});

		$model = $this->makeModel($provider);
		$model->setOptions(['model' => 'gpt-6-luna', 'apikey' => 'test']);
		$text = '';
		$result = $model->streamResult([['role' => 'user', 'content' => 'Search']], [],
			function(string $delta) use (&$text): void { $text .= $delta; });

		$this->assertSame('Hello world', $text);
		$this->assertSame('Hello world', $result->getContent());
		$this->assertCount(1, $result->getToolCalls());
		$this->assertSame('call-9', $result->getToolCalls()[0]->getId());
		$this->assertSame('lookup', $result->getToolCalls()[0]->getName());
		$this->assertSame(['query' => 'BASE3'], $result->getToolCalls()[0]->getArguments());
		$this->assertSame(26, $result->getMetadata()->getUsage()->getTotalTokens());
		$this->assertSame('tool_calls', $result->getMetadata()->getFinishReason());
	}

	public function testAddsExistingOpenAiPdfFileReferencesAsResponsesInputs(): void {
		$provider = $this->createMock(IAiProvider::class);
		$provider->expects($this->once())->method('request')
			->with('/v1/responses', $this->callback(function(array $payload): bool {
				$this->assertSame([
					['type' => 'input_file', 'file_id' => 'file-123'],
					['type' => 'input_text', 'text' => 'Summarize']
				], $payload['input'][0]['content']);
				return true;
			}))
			->willReturn(['id' => 'resp-file', 'output' => [], 'status' => 'completed']);

		$model = $this->makeModel($provider);
		$model->setOptions(['model' => 'gpt-6-luna', 'apikey' => 'test']);
		$model->setFileReferences([new AiProviderFileReference(
			'openai', 'file-123', 'sha256', 'example.pdf', 'application/pdf', 100
		)]);
		$model->raw([['role' => 'user', 'content' => 'Summarize']]);
	}

	public function testRejectsInvalidReasoningEffortBeforeSendingRequest(): void {
		$provider = $this->createMock(IAiProvider::class);
		$provider->expects($this->never())->method('request');
		$model = $this->makeModel($provider);
		$model->setOptions(['model' => 'gpt-6-luna', 'reasoningEffort' => 'wrong']);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid OpenAI Responses reasoning effort');
		$model->raw([['role' => 'user', 'content' => 'Hello']]);
	}

	public function testStatelessFunctionContinuationRetainsEncryptedReasoning(): void {
		$reasoning = [
			'id' => 'rs-123',
			'type' => 'reasoning',
			'summary' => [],
			'encrypted_content' => 'opaque-reasoning'
		];
		$functionCall = [
			'id' => 'fc-123',
			'type' => 'function_call',
			'status' => 'completed',
			'call_id' => 'call-123',
			'name' => 'lookup',
			'arguments' => '{"id":7}'
		];
		$iteration = 0;
		$provider = $this->createMock(IAiProvider::class);
		$provider->expects($this->exactly(2))->method('request')
			->willReturnCallback(function(string $path, array $payload) use (&$iteration, $reasoning, $functionCall): array {
				$this->assertSame('/v1/responses', $path);
				$this->assertFalse($payload['store']);
				$iteration++;
				if($iteration === 1) {
					return [
						'id' => 'resp-tool',
						'status' => 'completed',
						'output' => [$reasoning, $functionCall]
					];
				}

				$this->assertSame($reasoning, $payload['input'][1]);
				$this->assertSame($functionCall, $payload['input'][2]);
				$this->assertSame([
					'type' => 'function_call_output',
					'call_id' => 'call-123',
					'output' => '{"name":"Test"}'
				], $payload['input'][3]);
				return [
					'id' => 'resp-finish',
					'status' => 'completed',
					'output' => [[
						'type' => 'message',
						'content' => [['type' => 'output_text', 'text' => 'Done']]
					]]
				];
			});

		$model = $this->makeModel($provider);
		$model->setOptions(['model' => 'gpt-6-luna', 'apikey' => 'test']);
		$tool = [[
			'type' => 'function',
			'function' => ['name' => 'lookup', 'parameters' => ['type' => 'object', 'properties' => []]]
		]];
		$messages = [['role' => 'user', 'content' => 'Check item 7']];
		$first = $model->complete($messages, $tool);
		$this->assertCount(1, $first->getToolCalls());
		$assistant = AgentChatMessageAdapter::assistantMessage($first);
		$this->assertSame([$reasoning, $functionCall], $assistant['response_items']);
		$assistant['content'] = '';
		$messages[] = $assistant;
		$messages[] = [
			'role' => 'tool',
			'tool_call_id' => 'call-123',
			'content' => '{"name":"Test"}'
		];
		$this->assertSame('Done', $model->complete($messages, $tool)->getContent());
	}

	public function testStreamingReasoningItemsAreAvailableForToolContinuation(): void {
		$reasoning = ['type' => 'reasoning', 'id' => 'rs-stream', 'encrypted_content' => 'encrypted'];
		$functionCall = [
			'type' => 'function_call', 'id' => 'fc-stream', 'call_id' => 'call-stream',
			'name' => 'lookup', 'arguments' => '{"id":2}'
		];
		$provider = $this->createMock(IAiProvider::class);
		$provider->expects($this->once())->method('stream')
			->willReturnCallback(function(string $path, array $payload, callable $onChunk) use ($reasoning, $functionCall): void {
				$this->assertSame('/v1/responses', $path);
				$this->assertSame(true, $payload['stream']);
				$onChunk('data: ' . json_encode([
					'type' => 'response.completed',
					'response' => [
						'id' => 'resp-stream',
						'status' => 'completed',
						'output' => [$reasoning, $functionCall]
					]
				]) . "\n\n");
			});

		$model = $this->makeModel($provider);
		$model->setOptions(['model' => 'gpt-6-luna', 'apikey' => 'test']);
		$result = $model->streamResult([['role' => 'user', 'content' => 'Check item 2']], [], static function(string $chunk): void {});
		$this->assertSame([$reasoning, $functionCall], AgentChatMessageAdapter::assistantMessage($result)['response_items']);
	}

	public function testRejectsIncompleteResponsesRatherThanTreatingThemAsSuccess(): void {
		$provider = $this->createMock(IAiProvider::class);
		$provider->expects($this->once())->method('request')->willReturn([
			'status' => 'incomplete',
			'incomplete_details' => ['reason' => 'max_output_tokens'],
			'output' => []
		]);
		$model = $this->makeModel($provider);
		$model->setOptions(['model' => 'gpt-6-luna', 'apikey' => 'test']);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('max_output_tokens');
		$model->complete([['role' => 'user', 'content' => 'Hello']]);
	}

	public function testFailsOnMismatchedPreservedFunctionCalls(): void {
		$provider = $this->createMock(IAiProvider::class);
		$provider->expects($this->never())->method('request');
		$model = $this->makeModel($provider);
		$model->setOptions(['model' => 'gpt-6-luna', 'apikey' => 'test']);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('does not match');
		$model->raw([
			['role' => 'user', 'content' => 'Check it'],
			['role' => 'assistant', 'content' => '', 'tool_calls' => [[
				'id' => 'call-1', 'function' => ['name' => 'lookup', 'arguments' => '{}']
			]], 'response_items' => [[
				'type' => 'function_call', 'call_id' => 'different',
				'name' => 'lookup', 'arguments' => '{}'
			]]]
		]);
	}

}

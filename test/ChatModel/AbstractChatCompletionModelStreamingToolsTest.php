<?php declare(strict_types=1);

namespace MissionBay\Test\ChatModel;

use AssistantFoundation\Api\IAiProvider;
use Base3\Api\IClassMap;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\ChatModel\MistralChatModel;
use MissionBay\ChatModel\OpenAiChatModel;
use MissionBay\ChatModel\OpenAiCompatibleChatModel;
use PHPUnit\Framework\TestCase;

final class AbstractChatCompletionModelStreamingToolsTest extends TestCase {

	public function testMistralPayloadMergesSystemMessagesInOrder(): void {
		$provider = new CapturingStreamingProvider(false);
		$model = new MistralChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'mistral-small-2603',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key'
		]);

		$model->raw([
			['role' => 'system', 'content' => 'Base system'],
			['role' => 'system', 'content' => 'Priority 20 context'],
			['role' => 'system', 'content' => 'Priority 30 context'],
			['role' => 'user', 'content' => 'Hello']
		]);

		$this->assertSame([
			['role' => 'system', 'content' => "Base system\n\nPriority 20 context\n\nPriority 30 context"],
			['role' => 'user', 'content' => 'Hello']
		], $provider->payload['messages'] ?? null);
	}

	public function testOpenAiCompatiblePayloadMergesSystemMessagesInOrder(): void {
		$provider = new CapturingStreamingProvider(false);
		$model = new OpenAiCompatibleChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'compatible-model',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key'
		]);

		$model->raw([
			['role' => 'system', 'content' => 'Base system'],
			['role' => 'system', 'content' => 'Context A'],
			['role' => 'system', 'content' => 'Context B'],
			['role' => 'user', 'content' => 'Hello']
		]);

		$this->assertSame([
			['role' => 'system', 'content' => "Base system\n\nContext A\n\nContext B"],
			['role' => 'user', 'content' => 'Hello']
		], $provider->payload['messages'] ?? null);
	}

	public function testOpenAiPayloadKeepsSeparateSystemMessages(): void {
		$provider = new CapturingStreamingProvider(false);
		$model = new OpenAiChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'gpt-test',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key'
		]);

		$messages = [
			['role' => 'system', 'content' => 'Base system'],
			['role' => 'system', 'content' => 'Context A'],
			['role' => 'system', 'content' => 'Context B'],
			['role' => 'user', 'content' => 'Hello']
		];

		$model->raw($messages);

		$this->assertSame($messages, $provider->payload['messages'] ?? null);
	}

	public function testMistralPayloadUsesMaxTokensParameter(): void {
		$provider = new CapturingStreamingProvider(false);
		$model = new MistralChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'mistral-medium-2508',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key',
			'max_tokens' => 4000
		]);

		$model->raw([
			['role' => 'user', 'content' => 'Hello']
		]);

		$this->assertSame(4000, $provider->payload['max_tokens'] ?? null);
		$this->assertArrayNotHasKey('max_completion_tokens', $provider->payload);
	}

	public function testOpenAiCompatiblePayloadUsesMaxTokensParameter(): void {
		$provider = new CapturingStreamingProvider(false);
		$model = new OpenAiCompatibleChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'compatible-model',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key',
			'max_tokens' => 2048
		]);

		$model->raw([
			['role' => 'user', 'content' => 'Hello']
		]);

		$this->assertSame(2048, $provider->payload['max_tokens'] ?? null);
		$this->assertArrayNotHasKey('max_completion_tokens', $provider->payload);
	}

	public function testOpenAiPayloadUsesMaxCompletionTokensParameter(): void {
		$provider = new CapturingStreamingProvider(false);
		$model = new OpenAiChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'gpt-5.4-mini',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key',
			'max_tokens' => 5000
		]);

		$model->raw([
			['role' => 'user', 'content' => 'Hello']
		]);

		$this->assertSame(5000, $provider->payload['max_completion_tokens'] ?? null);
		$this->assertArrayNotHasKey('max_tokens', $provider->payload);
	}

	public function testStreamingPayloadKeepsToolDefinitionsAndSerializesEmptyPropertiesAsObject(): void {
		$provider = new CapturingStreamingProvider();
		$model = new MistralChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'mistral-small-2603',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key',
			'temperature' => 0.3,
			'max_tokens' => 20000
		]);

		$tools = [[
			'type' => 'function',
			'function' => [
				'name' => 'get_global_webdav_status',
				'description' => 'Returns the current global WebDAV state.',
				'parameters' => [
					'type' => 'object',
					'properties' => []
				]
			]
		]];

		$result = $model->streamResult(
			[['role' => 'user', 'content' => 'What is the WebDAV status?']],
			$tools,
			static function(string $delta): void {},
			static function(array $metadata): void {}
		);

		$payloadTools = $provider->payload['tools'] ?? null;
		$this->assertIsArray($payloadTools);
		$this->assertSame('get_global_webdav_status', $payloadTools[0]['function']['name'] ?? null);
		$this->assertInstanceOf(
			\stdClass::class,
			$payloadTools[0]['function']['parameters']['properties'] ?? null
		);
		$this->assertStringContainsString(
			'"properties":{}',
			json_encode($payloadTools[0]['function']['parameters'], JSON_THROW_ON_ERROR)
		);
		$this->assertSame('auto', $provider->payload['tool_choice'] ?? null);
		$this->assertTrue($provider->payload['stream'] ?? false);
		$this->assertCount(1, $result->getToolCalls());
		$this->assertSame('get_global_webdav_status', $result->getToolCalls()[0]->getName());
		$this->assertSame([], $result->getToolCalls()[0]->getArguments());
	}

	public function testToolCallMetadataPrecedesContentFromSameConfiguredProviderEvent(): void {
		$provider = new MixedContentToolStreamingProvider();
		$model = new MistralChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'mistral-small-2603',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key'
		]);
		$events = [];

		$result = $model->streamResult(
			[['role' => 'user', 'content' => 'Look it up']],
			[[
				'type' => 'function',
				'function' => [
					'name' => 'lookup',
					'parameters' => ['type' => 'object', 'properties' => []]
				]
			]],
			function(string $delta) use (&$events): void {
				$events[] = ['data', $delta];
			},
			function(array $metadata) use (&$events): void {
				$events[] = ['meta', $metadata['event'] ?? ''];
			}
		);

		$this->assertSame(['meta', 'toolcall'], $events[0]);
		$this->assertSame(['data', 'This must not be published before tool metadata.'], $events[1]);
		$this->assertCount(1, $result->getToolCalls());
		$this->assertSame('lookup', $result->getToolCalls()[0]->getName());
	}

	public function testStreamingPayloadIncludesConfiguredChatTemplateKwargs(): void {
		$provider = new CapturingStreamingProvider(false);
		$model = new MistralChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'qwen35-9b',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key',
			'chat_template_kwargs' => [
				'enable_thinking' => true
			]
		]);

		$model->streamResult(
			[['role' => 'user', 'content' => 'Think before answering.']],
			[],
			static function(string $delta): void {},
			static function(array $metadata): void {}
		);

		$this->assertSame(
			['enable_thinking' => true],
			$provider->payload['chat_template_kwargs'] ?? null
		);
	}

	public function testStreamingReasoningDeltasRemainHiddenAndEmitLifecycleMetadata(): void {
		$provider = new ReasoningStreamingProvider();
		$model = new MistralChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'qwen35-9b',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key'
		]);
		$deltas = [];
		$metadataEvents = [];

		$result = $model->streamResult(
			[['role' => 'user', 'content' => 'Answer after reasoning.']],
			[],
			function(string $delta) use (&$deltas): void {
				$deltas[] = $delta;
			},
			function(array $metadata) use (&$metadataEvents): void {
				$metadataEvents[] = $metadata;
			}
		);

		$this->assertSame(['Final answer.'], $deltas);
		$this->assertSame('Final answer.', $result->getContent());
		$this->assertSame('stop', $result->getMetadata()->getFinishReason());
		$this->assertSame('reasoning_start', $metadataEvents[0]['event'] ?? null);
		$this->assertFalse($metadataEvents[0]['visible'] ?? true);
		$this->assertSame('reasoning_end', $metadataEvents[1]['event'] ?? null);
		$this->assertFalse($metadataEvents[1]['visible'] ?? true);
		$this->assertSame(16, $metadataEvents[1]['bytes'] ?? null);
		$this->assertStringNotContainsString('Thinking quietly', json_encode($result->getRaw()) ?: '');
	}

	public function testStreamingPayloadWithoutToolsRemainsToolFree(): void {
		$provider = new CapturingStreamingProvider(false);
		$model = new MistralChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'mistral-small-2603',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key'
		]);

		$result = $model->streamResult(
			[['role' => 'user', 'content' => 'Hi']],
			[],
			static function(string $delta): void {},
			static function(array $metadata): void {}
		);

		$this->assertArrayNotHasKey('tools', $provider->payload);
		$this->assertArrayNotHasKey('tool_choice', $provider->payload);
		$this->assertSame('Hello!', $result->getContent());
		$this->assertFalse($result->hasToolCalls());
	}
}

require_once __DIR__ . '/ReasoningStreamingProvider.php';
require_once __DIR__ . '/MixedContentToolStreamingProvider.php';
require_once __DIR__ . '/CapturingStreamingProvider.php';
require_once __DIR__ . '/SingleProviderClassMap.php';

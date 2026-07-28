<?php declare(strict_types=1);

namespace MissionBay\Test\ChatModel;

use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use PHPUnit\Framework\TestCase;

final class NormalizedChatModelTraitTest extends TestCase {

	public function testStreamResultReturnsContentMetadataAndReconstructedToolCalls(): void {
		$events = [];
		$model = new class($this->createDispatcher($events)) {
			use NormalizedChatModelTrait;

			public function __construct(AiProviderRequestEventDispatcher $providerRequestEvents) {
				$this->initializeProviderRequestEvents($providerRequestEvents);
			}

			public static function getName(): string {
				return 'normalizedchatmodeltraittest';
			}

			public function raw(array $messages, array $tools = []): mixed {
				return [];
			}

			public function stream(
				array $messages,
				array $tools,
				callable $onData,
				callable $onMeta = null
			): void {
				$onData('I will check.');
				$onMeta([
					'event' => 'toolcall',
					'tool_calls' => [[
						'index' => 0,
						'id' => 'call-1',
						'type' => 'function',
						'function' => [
							'name' => 'lookup',
							'arguments' => '{"query":"'
						]
					]]
				]);
				$onMeta([
					'event' => 'toolcall',
					'tool_calls' => [[
						'index' => 0,
						'function' => ['arguments' => 'BASE3"}']
					]]
				]);
				$onMeta([
					'event' => 'meta',
					'finish_reason' => 'tool_calls',
					'full' => [
						'id' => 'stream-request',
						'model' => 'stream-test',
						'usage' => [
							'prompt_tokens' => 8,
							'completion_tokens' => 3,
							'total_tokens' => 11
						]
					]
				]);
			}

			public function getOptions(): array {
				return [
					'provider' => 'test',
					'model' => 'stream-test'
				];
			}
		};
		$deltas = [];
		$metadataEvents = [];

		$result = $model->streamResult(
			[['role' => 'user', 'content' => 'Look it up']],
			[['type' => 'function']],
			function(string $delta) use (&$deltas): void {
				$deltas[] = $delta;
			},
			function(array $metadata) use (&$metadataEvents): void {
				$metadataEvents[] = $metadata;
			}
		);

		$this->assertInstanceOf(AiChatResult::class, $result);
		$this->assertSame(['I will check.'], $deltas);
		$this->assertCount(3, $metadataEvents);
		$this->assertSame('I will check.', $result->getContent());
		$this->assertSame('tool_calls', $result->getMetadata()->getFinishReason());
		$this->assertCount(1, $result->getToolCalls());
		$this->assertSame('call-1', $result->getToolCalls()[0]->getId());
		$this->assertSame('lookup', $result->getToolCalls()[0]->getName());
		$this->assertSame(['query' => 'BASE3'], $result->getToolCalls()[0]->getArguments());
		$this->assertSame($metadataEvents, $result->getRaw());
		$this->assertCount(1, $events);
		$this->assertSame(11, $events[0]->getUsage()->getTotalTokens());
	}


	public function testStreamResultPreservesTextOnlyCompletion(): void {
		$events = [];
		$model = new class($this->createDispatcher($events)) {
			use NormalizedChatModelTrait;

			public function __construct(AiProviderRequestEventDispatcher $providerRequestEvents) {
				$this->initializeProviderRequestEvents($providerRequestEvents);
			}

			public static function getName(): string {
				return 'normalizedchatmodeltraittest';
			}

			public function raw(array $messages, array $tools = []): mixed {
				return [];
			}

			public function stream(
				array $messages,
				array $tools,
				callable $onData,
				callable $onMeta = null
			): void {
				$onData('Hello');
				$onData(' world');
				$onMeta([
					'event' => 'meta',
					'finish_reason' => 'stop'
				]);
			}

			public function getOptions(): array {
				return ['provider' => 'test'];
			}
		};
		$deltas = [];

		$result = $model->streamResult(
			[['role' => 'user', 'content' => 'Hello']],
			[],
			function(string $delta) use (&$deltas): void {
				$deltas[] = $delta;
			}
		);

		$this->assertSame(['Hello', ' world'], $deltas);
		$this->assertSame('Hello world', $result->getContent());
		$this->assertFalse($result->hasToolCalls());
		$this->assertSame('stop', $result->getMetadata()->getFinishReason());
		$this->assertCount(1, $events);
	}

	public function testStreamResultRejectsToolFinishWithoutToolMetadata(): void {
		$events = [];
		$model = new class($this->createDispatcher($events)) {
			use NormalizedChatModelTrait;

			public function __construct(AiProviderRequestEventDispatcher $providerRequestEvents) {
				$this->initializeProviderRequestEvents($providerRequestEvents);
			}

			public static function getName(): string {
				return 'normalizedchatmodeltraittest';
			}

			public function raw(array $messages, array $tools = []): mixed {
				return [];
			}

			public function stream(
				array $messages,
				array $tools,
				callable $onData,
				callable $onMeta = null
			): void {
				$onMeta([
					'event' => 'meta',
					'finish_reason' => 'tool_calls'
				]);
			}

			public function getOptions(): array {
				return ['provider' => 'test'];
			}
		};

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('did not provide reconstructable tool-call metadata');

		$model->streamResult(
			[['role' => 'user', 'content' => 'Look it up']],
			[['type' => 'function']],
			static function(string $delta): void {},
			static function(array $metadata): void {}
		);
	}

	/**
	 * @param array<int,AiProviderRequestCompletedEvent> $events
	 */
	private function createDispatcher(array &$events): AiProviderRequestEventDispatcher {
		$eventManager = new EventManager();
		$eventManager->on(
			AiProviderRequestCompletedEvent::class,
			static function(AiProviderRequestCompletedEvent $event) use (&$events): void {
				$events[] = $event;
			}
		);

		return new AiProviderRequestEventDispatcher($eventManager);
	}
}

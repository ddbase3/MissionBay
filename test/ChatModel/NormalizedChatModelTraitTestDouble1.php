<?php declare(strict_types=1);

namespace MissionBay\Test\ChatModel;

use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use PHPUnit\Framework\TestCase;

final class NormalizedChatModelTraitTestDouble1 {
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
		}

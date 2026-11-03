<?php declare(strict_types=1);

namespace MissionBay\Test\ChatModel;

use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use PHPUnit\Framework\TestCase;

final class NormalizedChatModelTraitTestDouble2 {
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
		}

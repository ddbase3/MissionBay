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

final class OpenAiResponsesChatModelTestDouble1 extends OpenAiResponsesChatModel {

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
		}

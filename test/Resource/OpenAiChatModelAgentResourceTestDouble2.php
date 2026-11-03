<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Api\IClassMap;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\OpenAiChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

final class OpenAiChatModelAgentResourceTestDouble2 extends OpenAiChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return ['choices' => [['message' => []]]];
			}
		}

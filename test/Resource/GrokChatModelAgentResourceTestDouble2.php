<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\GrokChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

final class GrokChatModelAgentResourceTestDouble2 extends GrokChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hello from Grok']]
					]
				];
			}
		}

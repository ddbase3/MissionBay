<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\GroqChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

final class GroqChatModelAgentResourceTestDouble2 extends GroqChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hello from Groq']]
					]
				];
			}
		}

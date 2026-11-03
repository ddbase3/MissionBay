<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\FireworksChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

final class FireworksChatModelAgentResourceTestDouble2 extends FireworksChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hello from Fireworks']]
					]
				];
			}
		}

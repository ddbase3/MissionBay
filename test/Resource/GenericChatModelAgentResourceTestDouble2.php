<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\GenericChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

final class GenericChatModelAgentResourceTestDouble2 extends GenericChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return ['choices' => [['message' => []]]];
			}
		}

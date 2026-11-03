<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\MistralChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

final class MistralChatModelAgentResourceTestDouble2 extends MistralChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hallo!']]
					]
				];
			}
		}

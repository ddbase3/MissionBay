<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\OpenRouterChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

final class OpenRouterChatModelAgentResourceTestDouble3 extends OpenRouterChatModelAgentResource {
			public function emitPayload(array $payload, callable $onData, callable $onMeta = null): void {
				$this->handleStreamPayload($payload, $onData, $onMeta);
			}
		}

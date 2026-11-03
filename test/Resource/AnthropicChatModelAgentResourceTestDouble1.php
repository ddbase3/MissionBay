<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\AnthropicChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;
use AssistantFoundation\Api\IAiChatModel;

final class AnthropicChatModelAgentResourceTestDouble1 implements IAgentConfigValueResolver {
			public function __construct(
				private bool $returnNull = false
			) {}

			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				return $this->returnNull ? null : $config;
			}
		}

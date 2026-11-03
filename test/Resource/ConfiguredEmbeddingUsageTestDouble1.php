<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IAiProvider;
use AssistantFoundation\Api\IServiceDriverDefinition;
use AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Api\IClassMap;
use Base3\Event\EventManager;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\EmbeddingModel\OpenAiEmbeddingModel;
use MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use MissionBay\ServiceDriver\OpenAiEmbeddingServiceDriverDefinition;
use MissionBay\Transport\OpenAiTransport;
use PHPUnit\Framework\TestCase;

final class ConfiguredEmbeddingUsageTestDouble1 implements IAgentConfigValueResolver  {
			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				if(is_array($config) && ($config['mode'] ?? null) === 'fixed') {
					return $config['value'] ?? null;
				}
				if(is_array($config) && ($config['mode'] ?? null) === 'env') {
					return 'test-secret';
				}

				return $config;
			}
		}

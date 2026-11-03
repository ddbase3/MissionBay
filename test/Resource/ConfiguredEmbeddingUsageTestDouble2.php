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

final class ConfiguredEmbeddingUsageTestDouble2 implements ISettingsStore  {
			public function get(string $group, string $name, array $default = []): array {
				if($group === 'service-embedding' && $name === 'embedding-main') {
					return [
						'id' => 'embedding-main',
						'name' => 'Embedding Main',
						'serviceType' => 'embedding',
						'connection' => 'openai',
						'driver' => 'openai-embedding',
						'model' => 'text-embedding-3-small',
						'enabled' => true,
						'options' => []
					];
				}

				if($group === 'connection' && $name === 'openai') {
					return [
						'id' => 'openai',
						'name' => 'OpenAI',
						'type' => 'http',
						'driver' => 'http',
						'baseUrl' => 'https://api.openai.com',
						'auth' => [
							'type' => 'bearer',
							'secret' => [
								'mode' => 'env',
								'name' => 'OPENAI_API_KEY'
							]
						],
						'timeoutSeconds' => 60,
						'scope' => 'global',
						'enabled' => true,
						'options' => []
					];
				}

				return $default;
			}

			public function set(string $group, string $name, array $settings): void {}
			public function has(string $group, string $name): bool { return $this->get($group, $name, []) !== []; }
			public function remove(string $group, string $name): void {}
			public function getGroup(string $group): array { return []; }
			public function save(): void {}
			public function reload(): void {}
		}

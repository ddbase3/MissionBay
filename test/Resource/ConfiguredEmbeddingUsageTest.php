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
use MissionBay\ServiceDriver\OpenAiEmbeddingServiceDriverDefinition;
use MissionBay\Transport\OpenAiTransport;
use PHPUnit\Framework\TestCase;

final class ConfiguredEmbeddingUsageTest extends TestCase {

	public function testConfiguredEmbeddingUsesProviderUsageEventWithoutDuplicateWrapperLogging(): void {
		$events = [];
		$eventManager = new EventManager();
		$eventManager->on(
			AiProviderRequestCompletedEvent::class,
			static function(AiProviderRequestCompletedEvent $event) use (&$events): void {
				$events[] = $event;
			}
		);

		$provider = $this->createMock(IAiProvider::class);
		$provider->method('request')->willReturn([
			'data' => [[
				'index' => 0,
				'embedding' => [0.1, 0.2, 0.3]
			]],
			'usage' => [
				'prompt_tokens' => 7,
				'total_tokens' => 7
			]
		]);

		$driverDefinition = new OpenAiEmbeddingServiceDriverDefinition();
		$classMap = $this->createMock(IClassMap::class);
		$embeddingModel = new OpenAiEmbeddingModel(
			$classMap,
			new AiProviderRequestEventDispatcher($eventManager)
		);

		$classMap->method('getInstancesByInterface')
			->with(IServiceDriverDefinition::class)
			->willReturn([$driverDefinition]);
		$classMap->method('getInstanceByInterfaceName')
			->willReturnCallback(static function(string $interface, string $name) use ($embeddingModel, $provider) {
				if($interface === IAiEmbeddingModel::class && $name === OpenAiEmbeddingModel::getName()) {
					return $embeddingModel;
				}
				if($interface === IAiProvider::class && $name === OpenAiTransport::getName()) {
					return $provider;
				}
				return null;
			});

		$resource = new ConfiguredEmbeddingModelAgentResource(
			$this->resolver(),
			$this->settingsStore(),
			$classMap,
			'configured-embedding'
		);
		$resource->setConfig(['service' => 'embedding-main']);

		$result = $resource->embedResult(['hello']);

		$this->assertSame([[0.1, 0.2, 0.3]], $result->getEmbeddings());
		$this->assertCount(1, $events);
		$this->assertSame('embedding', $events[0]->getMetadata()->getOperation());
		$this->assertSame(OpenAiEmbeddingModel::getName(), $events[0]->getSourceName());
		$this->assertSame(7, $events[0]->getUsage()->getInputTokens());
		$this->assertSame(7, $events[0]->getUsage()->getTotalTokens());
		$this->assertSame(1, $events[0]->getUsage()->getMetrics()['input_items']);
		$this->assertSame(1, $events[0]->getUsage()->getMetrics()['output_vectors']);
	}

	private function resolver(): IAgentConfigValueResolver {
		return new class implements IAgentConfigValueResolver {
			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				if(is_array($config) && ($config['mode'] ?? null) === 'fixed') {
					return $config['value'] ?? null;
				}
				if(is_array($config) && ($config['mode'] ?? null) === 'env') {
					return 'test-secret';
				}

				return $config;
			}
		};
	}

	private function settingsStore(): ISettingsStore {
		return new class implements ISettingsStore {
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
		};
	}
}

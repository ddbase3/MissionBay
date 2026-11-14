<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Api\IAiEmbeddingModel;
use Base3\AssistantFoundation\Api\IAiProvider;
use Base3\AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\Core\Event\EventManager;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\EmbeddingModel\OpenAiEmbeddingModel;
use Base3\MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use Base3\MissionBay\ServiceDriver\OpenAiEmbeddingServiceDriverDefinition;
use Base3\MissionBay\Transport\OpenAiTransport;
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

		$provider = $this->createStub(IAiProvider::class);
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
		$classMap = $this->createStub(IClassMap::class);
		$embeddingModel = new OpenAiEmbeddingModel(
			$classMap,
			new AiProviderRequestEventDispatcher($eventManager)
		);

		$classMap->method('getInstancesByInterface')
			->with(IServiceDriverDefinition::class)
			->willReturn([$driverDefinition]);
		$classMap->method('getClassByInterfaceName')
			->with(IAiEmbeddingModel::class, OpenAiEmbeddingModel::getName())
			->willReturn(OpenAiEmbeddingModel::class);
		$classMap->method('instantiate')
			->with(OpenAiEmbeddingModel::class)
			->willReturn($embeddingModel);
		$classMap->method('getInstanceByInterfaceName')
			->willReturnCallback(static function(string $interface, string $name) use ($provider) {
				if($interface === IAiProvider::class && $name === OpenAiTransport::getName()) {
					return $provider;
				}
				return null;
			});

		$resolver = $this->resolver();
		$settingsStore = $this->settingsStore();
		$resource = new ConfiguredEmbeddingModelAgentResource(
			$resolver,
			$settingsStore,
			new ConfiguredServiceRuntimeResolver($settingsStore, $classMap, $resolver),
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
		return new ConfiguredEmbeddingUsageTestDouble1();
	}

	private function settingsStore(): ISettingsStore {
		return new ConfiguredEmbeddingUsageTestDouble2();
	}
}

require_once __DIR__ . '/ConfiguredEmbeddingUsageTestDouble1.php';

require_once __DIR__ . '/ConfiguredEmbeddingUsageTestDouble2.php';

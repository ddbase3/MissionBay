<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\ConfiguredChatModelAgentResource;
use MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use MissionBay\Resource\ConfiguredImageModelAgentResource;
use MissionBay\Resource\ConfiguredParserServiceAgentResource;
use MissionBay\Resource\ConfiguredSearchServiceAgentResource;
use MissionBay\Resource\ConfiguredVectorSearchAgentResource;
use MissionBay\Resource\ConfiguredVectorStoreAgentResource;
use PHPUnit\Framework\TestCase;

final class ConfiguredServiceAgentResourceLifecycleTest extends TestCase {

	public function testSetConfigDoesNotResolveConfiguredServices(): void {
		$resolver = $this->createMock(IAgentConfigValueResolver::class);
		$resolver->expects($this->never())->method('resolveValue');

		$settingsStore = $this->createMock(ISettingsStore::class);
		$settingsStore->expects($this->never())->method('get');
		$settingsStore->expects($this->never())->method('getGroup');

		$classMap = $this->createMock(IClassMap::class);
		$classMap->expects($this->never())->method('getInstancesByInterface');
		$classMap->expects($this->never())->method('getInstanceByInterfaceName');

		$resources = [
			new ConfiguredChatModelAgentResource($resolver, $settingsStore, $classMap, 'chat'),
			new ConfiguredEmbeddingModelAgentResource($resolver, $settingsStore, $classMap, 'embedding'),
			new ConfiguredImageModelAgentResource($resolver, $settingsStore, $classMap, 'image'),
			new ConfiguredParserServiceAgentResource($resolver, $settingsStore, $classMap, 'parser'),
			new ConfiguredSearchServiceAgentResource($resolver, $settingsStore, $classMap, 'search'),
			new ConfiguredVectorSearchAgentResource($resolver, $settingsStore, $classMap, 'vector-search'),
			new ConfiguredVectorStoreAgentResource($resolver, $settingsStore, $classMap, 'vector-store')
		];

		foreach($resources as $resource) {
			$resource->setConfig(['service' => 'configured-service']);
			$resource->setOptions(['runtime_override' => true]);
		}

		$this->addToAssertionCount(count($resources));
	}
}

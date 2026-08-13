<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IConfiguredParserServiceResolver;
use MissionBay\Resource\ConfiguredChatModelAgentResource;
use MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use MissionBay\Resource\ConfiguredImageModelAgentResource;
use MissionBay\Resource\ConfiguredParserServiceAgentResource;
use MissionBay\Resource\ConfiguredSearchServiceAgentResource;
use MissionBay\Resource\ConfiguredVectorSearchAgentResource;
use MissionBay\Resource\ConfiguredVectorStoreAgentResource;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
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
		$classMap->expects($this->never())->method('getClassByInterfaceName');
		$classMap->expects($this->never())->method('instantiate');

		$runtimeResolver = new ConfiguredServiceRuntimeResolver($settingsStore, $classMap, $resolver);
		$parserResolver = $this->createMock(IConfiguredParserServiceResolver::class);
		$parserResolver->expects($this->never())->method('resolve');

		$resources = [
			new ConfiguredChatModelAgentResource($resolver, $settingsStore, $runtimeResolver, 'chat'),
			new ConfiguredEmbeddingModelAgentResource($resolver, $settingsStore, $runtimeResolver, 'embedding'),
			new ConfiguredImageModelAgentResource($resolver, $settingsStore, $runtimeResolver, 'image'),
			new ConfiguredParserServiceAgentResource($resolver, $settingsStore, $parserResolver, 'parser'),
			new ConfiguredSearchServiceAgentResource($resolver, $settingsStore, $runtimeResolver, 'search'),
			new ConfiguredVectorSearchAgentResource($resolver, $settingsStore, $runtimeResolver, 'vector-search'),
			new ConfiguredVectorStoreAgentResource($resolver, $settingsStore, $runtimeResolver, 'vector-store')
		];

		foreach($resources as $resource) {
			$resource->setConfig(['service' => 'configured-service']);
			$resource->setOptions(['runtime_override' => true]);
		}

		$this->addToAssertionCount(count($resources));
	}
}

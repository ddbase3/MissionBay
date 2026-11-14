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

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IConfiguredParserServiceResolver;
use Base3\MissionBay\Resource\ConfiguredChatModelAgentResource;
use Base3\MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use Base3\MissionBay\Resource\ConfiguredImageModelAgentResource;
use Base3\MissionBay\Resource\ConfiguredParserServiceAgentResource;
use Base3\MissionBay\Resource\ConfiguredSearchServiceAgentResource;
use Base3\MissionBay\Resource\ConfiguredVectorSearchAgentResource;
use Base3\MissionBay\Resource\ConfiguredVectorStoreAgentResource;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\MissionBay\Service\AiProviderFileReferenceStateRepository;
use Base3\ResourceFoundation\Api\IManagedFileStorageService;
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

		$stateStore = $this->createMock(IStateStore::class);
		$stateStore->expects($this->never())->method('get');
		$managedFileStorage = $this->createStub(IManagedFileStorageService::class);

		$resources = [
			new ConfiguredChatModelAgentResource($resolver, $settingsStore, $runtimeResolver, $stateStore, $managedFileStorage, new AiProviderFileReferenceStateRepository($stateStore), 'chat'),
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

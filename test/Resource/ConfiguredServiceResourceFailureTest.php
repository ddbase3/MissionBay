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
use Base3\MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use Base3\MissionBay\Resource\ConfiguredParserServiceAgentResource;
use Base3\MissionBay\Resource\ConfiguredVectorSearchAgentResource;
use Base3\MissionBay\Resource\ConfiguredVectorStoreAgentResource;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use PHPUnit\Framework\TestCase;

final class ConfiguredServiceResourceFailureTest extends TestCase {

	public function testEmbeddingOperationsRejectMissingServiceId(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$settings = $this->createStub(ISettingsStore::class);
		$resource = new ConfiguredEmbeddingModelAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$resource->setConfig([]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('requires config key "service"');
		$resource->embed(['text']);
	}

	public function testVectorSearchRejectsMissingServiceIdBeforeBackendAccess(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$settings = $this->createStub(ISettingsStore::class);
		$resource = new ConfiguredVectorSearchAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$resource->setConfig([]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('requires config key "service"');
		$resource->search([1.0, 0.1]);
	}

	public function testVectorStoreRejectsMissingServiceIdBeforeBackendAccess(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$settings = $this->createStub(ISettingsStore::class);
		$resource = new ConfiguredVectorStoreAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$resource->setConfig([]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('requires config key "service"');
		$resource->createCollection('public');
	}

	public function testParserRejectsMissingServiceIdBeforeAdapterAccess(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$settings = $this->createStub(ISettingsStore::class);
		$adapter = $this->createMock(IConfiguredParserServiceResolver::class);
		$adapter->expects($this->never())->method('resolve');
		$resource = new ConfiguredParserServiceAgentResource($resolver, $settings, $adapter);
		$resource->setConfig([]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('requires config key "service"');
		$resource->getPriority();
	}

	public function testVectorSearchSchemaRequiresExplicitService(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$settings = $this->createStub(ISettingsStore::class);
		$resource = new ConfiguredVectorSearchAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$this->assertSame(['service'], $resource->getSchema()['required']);
		$this->assertSame('string', $resource->getSchema()['properties']['service']['type']);
	}

	public function testEmbeddingSchemaListsOnlyEnabledEmbeddingServices(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects($this->once())->method('getGroup')->with('service-embedding')->willReturn([
			'z' => ['serviceType' => 'embedding', 'enabled' => true],
			'a' => ['serviceType' => 'embedding', 'enabled' => 'yes'],
			'bad' => ['serviceType' => 'llm', 'enabled' => true],
			'off' => ['serviceType' => 'embedding', 'enabled' => false]
		]);
		$resource = new ConfiguredEmbeddingModelAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$this->assertSame(['a', 'z'], $resource->getSchema()['properties']['service']['enum']);
	}

	public function testVectorStoreSchemaOmitsEnumWhenNoServiceIsConfigured(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturn([]);
		$resource = new ConfiguredVectorStoreAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$this->assertArrayNotHasKey('enum', $resource->getSchema()['properties']['service']);
	}

	private function runtime(ISettingsStore $settings, IAgentConfigValueResolver $resolver): ConfiguredServiceRuntimeResolver {
		return new ConfiguredServiceRuntimeResolver($settings, $this->createStub(IClassMap::class), $resolver);
	}
}

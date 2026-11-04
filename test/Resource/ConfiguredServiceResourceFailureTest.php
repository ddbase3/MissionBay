<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IConfiguredParserServiceResolver;
use MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use MissionBay\Resource\ConfiguredParserServiceAgentResource;
use MissionBay\Resource\ConfiguredVectorSearchAgentResource;
use MissionBay\Resource\ConfiguredVectorStoreAgentResource;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
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

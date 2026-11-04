<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IConfiguredParserServiceResolver;
use MissionBay\Api\IParserService;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;
use MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use MissionBay\Resource\ConfiguredParserServiceAgentResource;
use MissionBay\Resource\ConfiguredVectorSearchAgentResource;
use MissionBay\Resource\ConfiguredVectorStoreAgentResource;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use PHPUnit\Framework\TestCase;

final class ConfiguredAgentResourceBoundaryCoverageTest extends TestCase {

	private function configValues(): IAgentConfigValueResolver {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(static fn(mixed $value): mixed => $value);
		return $resolver;
	}

	private function runtime(ISettingsStore $settings, IAgentConfigValueResolver $resolver): ConfiguredServiceRuntimeResolver {
		return new ConfiguredServiceRuntimeResolver($settings, $this->createStub(IClassMap::class), $resolver);
	}

	private function item(): AgentContentItem {
		return new AgentContentItem('upsert', 'documents', 'item-a', 'hash-a', 'text/plain', 'hello world', false, 11);
	}

	public function testParserResourceExposesUniqueIdentity(): void {
		$resource = new ConfiguredParserServiceAgentResource($this->configValues(), $this->createStub(ISettingsStore::class),
			$this->createStub(IConfiguredParserServiceResolver::class), 'parser-resource');
		$this->assertSame('configuredparserserviceagentresource', $resource::getName());
		$this->assertSame('parser-resource', $resource->getId());
		$this->assertNotSame('', $resource->getDescription());
	}

	public function testParserResourceRequiresAConfiguredService(): void {
		$resource = new ConfiguredParserServiceAgentResource($this->configValues(), $this->createStub(ISettingsStore::class),
			$this->createStub(IConfiguredParserServiceResolver::class));
		$this->expectException(\RuntimeException::class);
		$resource->getPriority();
	}

	public function testParserResourceDelegatesPrioritySupportAndParsing(): void {
		$parser = $this->createStub(IParserService::class);
		$parsed = new AgentParsedContent('plain text', ['title' => 'Example']);
		$parser->method('getPriority')->willReturn(45);
		$parser->method('getOptions')->willReturn(['language' => 'de']);
		$parser->method('supports')->willReturn(true);
		$parser->method('parse')->willReturn($parsed);
		$backend = $this->createStub(IConfiguredParserServiceResolver::class);
		$backend->method('resolve')->willReturn($parser);
		$resource = new ConfiguredParserServiceAgentResource($this->configValues(), $this->createStub(ISettingsStore::class), $backend);
		$resource->setConfig(['service' => 'docling']);
		$this->assertSame(45, $resource->getPriority());
		$this->assertTrue($resource->supports($this->item()));
		$this->assertSame($parsed, $resource->parse($this->item()));
		$this->assertSame(['language' => 'de'], $resource->getOptions());
	}

	public function testChangingParserConfigInvalidatesCachedAdapter(): void {
		$parser = $this->createStub(IParserService::class);
		$parser->method('getPriority')->willReturn(9);
		$parser->method('getOptions')->willReturn([]);
		$calls = 0;
		$backend = $this->createStub(IConfiguredParserServiceResolver::class);
		$backend->method('resolve')->willReturnCallback(static function () use (&$calls, $parser): IParserService {
			$calls++;
			return $parser;
		});
		$resource = new ConfiguredParserServiceAgentResource($this->configValues(), $this->createStub(ISettingsStore::class), $backend);
		$resource->setConfig(['service' => 'parser-one']);
		$this->assertSame(9, $resource->getPriority());
		$resource->setConfig(['service' => 'parser-two']);
		$this->assertSame(9, $resource->getPriority());
		$this->assertSame(2, $calls);
	}

	public function testEmbeddingResourceSchemaListsOnlyMatchingEnabledServices(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturn([
			'active' => ['serviceType' => 'embedding', 'enabled' => true, 'model' => 'embed-one'],
			'disabled' => ['serviceType' => 'embedding', 'enabled' => false],
			'wrong-type' => ['serviceType' => 'llm', 'enabled' => true]
		]);
		$resolver = $this->configValues();
		$resource = new ConfiguredEmbeddingModelAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$this->assertSame(['active'], $resource->getSchema()['properties']['service']['enum']);
		$this->assertSame(['service'], $resource->getSchema()['required']);
	}

	public function testEmbeddingResourceRejectsMissingServiceBeforeBackendResolution(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$resolver = $this->configValues();
		$resource = new ConfiguredEmbeddingModelAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$this->expectException(\RuntimeException::class);
		$resource->embed(['test']);
	}

	public function testVectorSearchResourceDeclaresRequiredService(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$resolver = $this->configValues();
		$resource = new ConfiguredVectorSearchAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$this->assertSame(['service'], $resource->getSchema()['required']);
		$this->assertSame('string', $resource->getSchema()['properties']['service']['type']);
	}

	public function testVectorSearchResourceRejectsMissingServiceBeforeSearch(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$resolver = $this->configValues();
		$resource = new ConfiguredVectorSearchAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$this->expectException(\RuntimeException::class);
		$resource->search([0.5, 0.3]);
	}

	public function testVectorStoreSchemaExcludesDisabledAndWrongTypeServices(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturn([
			'store-one' => ['serviceType' => 'vectorstore', 'enabled' => true],
			'store-two' => ['serviceType' => 'vectorstore', 'enabled' => false],
			'search-one' => ['serviceType' => 'vectorsearch', 'enabled' => true]
		]);
		$resolver = $this->configValues();
		$resource = new ConfiguredVectorStoreAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$this->assertSame(['store-one'], $resource->getSchema()['properties']['service']['enum']);
	}

	public function testVectorStoreResourceRejectsMissingServiceBeforeInspectingIndex(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$resolver = $this->configValues();
		$resource = new ConfiguredVectorStoreAgentResource($resolver, $settings, $this->runtime($settings, $resolver));
		$this->expectException(\RuntimeException::class);
		$resource->getInfo('documents');
	}
}

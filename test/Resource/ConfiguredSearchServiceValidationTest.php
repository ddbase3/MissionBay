<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\ConfiguredSearchServiceAgentResource;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use PHPUnit\Framework\TestCase;

final class ConfiguredSearchServiceValidationTest extends TestCase {

	public function testSchemaDocumentsRequiredConfiguredSearchService(): void {
		$resource = $this->resource();
		$this->assertSame(['service'], $resource->getSchema()['required']);
		$this->assertSame('integer', $resource->getSchema()['properties']['maxresults']['type']);
		$this->assertSame(1, $resource->getSchema()['properties']['maxresults']['minimum']);
		$this->assertSame(false, $resource->getSchema()['properties']['includeraw']['default']);
	}

	public function testToolDefinitionIsExplicitlyReadOnlyAndHasRequiredQuery(): void {
		$definitions = $this->resource()->getToolDefinitions();
		$this->assertCount(1, $definitions);
		$this->assertSame('web_search', $definitions[0]['function']['name']);
		$this->assertTrue($definitions[0]['readOnlyHint']);
		$this->assertFalse($definitions[0]['mutation']);
		$this->assertFalse($definitions[0]['requiresApproval']);
		$this->assertSame(['query'], $definitions[0]['function']['parameters']['required']);
	}

	public function testUnknownFunctionCannotBeExecuted(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported tool');
		$this->resource()->callTool('unsupported', [], $this->createStub(IAgentContext::class));
	}

	public function testEmptyQueryIsRejectedWithoutServiceResolution(): void {
		$result = $this->resource()->callTool('web_search', ['query' => '   '], $this->createStub(IAgentContext::class));
		$this->assertFalse($result['ok']);
		$this->assertSame('missing_query', $result['error_code']);
		$this->assertArrayNotHasKey('query', $result);
		$this->assertArrayNotHasKey('diagnostic', $result);
	}

	public function testNonStringQueryIsRejectedWithoutCoercion(): void {
		$result = $this->resource()->callTool('web_search', ['query' => ['not-a-string']], $this->createStub(IAgentContext::class));
		$this->assertFalse($result['ok']);
		$this->assertSame('missing_query', $result['error_code']);
	}

	public function testMissingConfiguredServiceReturnsStructuredToolFailure(): void {
		$resource = $this->resource();
		$resource->setConfig(['maxresults' => 3]);
		$result = $resource->callTool('web_search', ['query' => '  findings ', 'max_results' => 25], $this->createStub(IAgentContext::class));
		$this->assertFalse($result['ok']);
		$this->assertSame('web_search_failed', $result['error_code']);
		$this->assertSame('findings', $result['query']);
		$this->assertSame(['max_results' => 3], $result['options']);
		$this->assertSame(\RuntimeException::class, $result['diagnostic']['type']);
		$this->assertStringContainsString('requires config key "service"', $result['error']);
	}

	public function testInvalidMaxResultsArgumentFallsBackToConfiguredCap(): void {
		$resource = $this->resource();
		$resource->setConfig(['maxresults' => 4]);
		$result = $resource->callTool('web_search', ['query' => 'text', 'max_results' => -100], $this->createStub(IAgentContext::class));
		$this->assertSame(['max_results' => 4], $result['options']);
	}

	public function testQueryFailureWithoutResultsLimitOmitsOptions(): void {
		$result = $this->resource()->callTool('web_search', ['query' => 'text'], $this->createStub(IAgentContext::class));
		$this->assertFalse($result['ok']);
		$this->assertArrayNotHasKey('options', $result);
	}

	public function testDirectSearchWithoutServiceThrowsRatherThanReturningFakeResults(): void {
		$resource = $this->resource();
		$resource->setConfig([]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('requires config key "service"');
		$resource->search('test');
	}

	public function testConfiguredOptionsAreStoredUntilServiceResolution(): void {
		$resource = $this->resource();
		$resource->setConfig(['service' => 'missing']);
		$resource->setOptions(['max_results' => 7]);
		$this->assertSame('configuredsearchserviceagentresource', $resource::getName());
		$this->assertNotSame('', $resource->getDescription());
	}

	private function resource(): ConfiguredSearchServiceAgentResource {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(static fn(mixed $value): mixed => $value);
		$settings = $this->createStub(ISettingsStore::class);
		$runtime = new ConfiguredServiceRuntimeResolver($settings, $this->createStub(IClassMap::class), $resolver);
		return new ConfiguredSearchServiceAgentResource($resolver, $settings, $runtime);
	}
}

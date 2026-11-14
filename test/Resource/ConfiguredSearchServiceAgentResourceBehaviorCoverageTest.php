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
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\ConfiguredSearchServiceAgentResource;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use PHPUnit\Framework\TestCase;

final class ConfiguredSearchServiceAgentResourceBehaviorCoverageTest extends TestCase {

	private function resource(): ConfiguredSearchServiceAgentResource {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(static fn(mixed $value): mixed => $value);
		$settings = $this->createStub(ISettingsStore::class);
		$runtime = new ConfiguredServiceRuntimeResolver($settings, $this->createStub(IClassMap::class), $resolver);
		return new ConfiguredSearchServiceAgentResource($resolver, $settings, $runtime, 'search-resource');
	}

	private function searchOptions(ConfiguredSearchServiceAgentResource $resource, array $arguments): array {
		return (new \ReflectionMethod($resource, 'buildToolSearchOptions'))->invoke($resource, $arguments);
	}

	public function testClassIdentityAndResourceIdentityAreStable(): void {
		$resource = $this->resource();
		$this->assertSame('configuredsearchserviceagentresource', $resource::getName());
		$this->assertSame('search-resource', $resource->getId());
		$this->assertNotSame('', $resource->getDescription());
	}

	public function testSchemaRequiresServiceAndConstrainsMaximumResults(): void {
		$schema = $this->resource()->getSchema();
		$this->assertSame(['service'], $schema['required']);
		$this->assertSame('integer', $schema['properties']['maxresults']['type']);
		$this->assertSame(1, $schema['properties']['maxresults']['minimum']);
		$this->assertFalse($schema['properties']['includeraw']['default']);
	}

	public function testWebSearchToolIsExplicitlyReadOnly(): void {
		$tools = $this->resource()->getToolDefinitions();
		$this->assertCount(1, $tools);
		$this->assertSame('web_search', $tools[0]['function']['name']);
		$this->assertTrue($tools[0]['readOnlyHint']);
		$this->assertFalse($tools[0]['mutation']);
		$this->assertFalse($tools[0]['requiresApproval']);
		$this->assertSame(['query'], $tools[0]['function']['parameters']['required']);
	}

	public function testUnsupportedToolNameIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->resource()->callTool('other_tool', ['query' => 'news'], new AgentContext());
	}

	public function testMissingQueryIsReturnedAsStructuredError(): void {
		$result = $this->resource()->callTool('web_search', [], new AgentContext());
		$this->assertFalse($result['ok']);
		$this->assertSame('missing_query', $result['error_code']);
		$this->assertArrayNotHasKey('query', $result);
	}

	public function testWhitespaceQueryIsRejectedWithoutBackendCall(): void {
		$result = $this->resource()->callTool('web_search', ['query' => " \t "], new AgentContext());
		$this->assertFalse($result['ok']);
		$this->assertSame('missing_query', $result['error_code']);
	}

	public function testNonStringQueryIsRejectedWithoutBackendCall(): void {
		$result = $this->resource()->callTool('web_search', ['query' => ['unexpected']], new AgentContext());
		$this->assertFalse($result['ok']);
		$this->assertSame('missing_query', $result['error_code']);
	}

	public function testMissingConfiguredServiceDoesNotReturnFabricatedResults(): void {
		$result = $this->resource()->callTool('web_search', ['query' => '  current news  '], new AgentContext());
		$this->assertFalse($result['ok']);
		$this->assertSame('web_search_failed', $result['error_code']);
		$this->assertSame('current news', $result['query']);
		$this->assertArrayHasKey('diagnostic', $result);
	}

	public function testConfiguredMaximumCapsRequestedResultCount(): void {
		$resource = $this->resource();
		$resource->setConfig(['maxresults' => 3]);
		$this->assertSame(['max_results' => 3], $this->searchOptions($resource, ['max_results' => 15]));
	}

	public function testConfiguredMaximumIsUsedWithoutToolArgument(): void {
		$resource = $this->resource();
		$resource->setConfig(['maxresults' => '4']);
		$this->assertSame(['max_results' => 4], $this->searchOptions($resource, []));
	}

	public function testSmallerToolMaximumRemainsEffective(): void {
		$resource = $this->resource();
		$resource->setConfig(['maxresults' => 7]);
		$this->assertSame(['max_results' => 2], $this->searchOptions($resource, ['max_results' => 2]));
	}

	public function testInvalidToolLimitDoesNotOverrideConfiguredMaximum(): void {
		$resource = $this->resource();
		$resource->setConfig(['maxresults' => 5]);
		$this->assertSame(['max_results' => 5], $this->searchOptions($resource, ['max_results' => -3]));
	}

	public function testUnconfiguredResultLimitIsNotInvented(): void {
		$this->assertSame([], $this->searchOptions($this->resource(), ['max_results' => 'not-a-number']));
	}

	public function testResultLimitChangesWhenConfigurationChanges(): void {
		$resource = $this->resource();
		$resource->setConfig(['maxresults' => 2]);
		$this->assertSame(['max_results' => 2], $this->searchOptions($resource, []));
		$resource->setConfig(['maxresults' => 6]);
		$this->assertSame(['max_results' => 6], $this->searchOptions($resource, []));
	}
}

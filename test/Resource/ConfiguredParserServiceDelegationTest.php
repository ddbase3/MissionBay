<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IConfiguredParserServiceResolver;
use MissionBay\Api\IParserService;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;
use MissionBay\Resource\ConfiguredParserServiceAgentResource;
use PHPUnit\Framework\TestCase;

final class ConfiguredParserServiceDelegationTest extends TestCase {

	public function testFirstOperationalCallResolvesConfiguredParserExactlyOnce(): void {
		$parser = $this->createStub(IParserService::class);
		$parser->method('getPriority')->willReturn(42);
		$parser->method('getOptions')->willReturn(['priority' => 42]);
		$adapter = $this->createMock(IConfiguredParserServiceResolver::class);
		$adapter->expects($this->once())->method('resolve')->with('parser', [])->willReturn($parser);
		$resource = $this->resource($adapter);
		$this->assertSame(42, $resource->getPriority());
		$this->assertSame(42, $resource->getPriority());
		$this->assertSame(['priority' => 42], $resource->getOptions());
	}

	public function testSupportsDelegatesOriginalContentItemWithoutCopying(): void {
		$item = $this->item();
		$parser = $this->createMock(IParserService::class);
		$parser->expects($this->once())->method('supports')->with($item)->willReturn(true);
		$adapter = $this->createStub(IConfiguredParserServiceResolver::class);
		$adapter->method('resolve')->willReturn($parser);
		$this->assertTrue($this->resource($adapter)->supports($item));
	}

	public function testParseReturnsTheExactNormalizedParserResult(): void {
		$item = $this->item();
		$result = new AgentParsedContent('Normalized', ['source' => 'document'], null, [], 'text/plain');
		$parser = $this->createMock(IParserService::class);
		$parser->expects($this->once())->method('parse')->with($item)->willReturn($result);
		$adapter = $this->createStub(IConfiguredParserServiceResolver::class);
		$adapter->method('resolve')->willReturn($parser);
		$this->assertSame($result, $this->resource($adapter)->parse($item));
	}

	public function testResolvedRuntimeOptionsAreAppliedToAnAlreadyLoadedParser(): void {
		$parser = $this->createMock(IParserService::class);
		$parser->method('getOptions')->willReturn(['priority' => 30]);
		$parser->method('getPriority')->willReturn(30);
		$parser->expects($this->once())->method('setOptions')->with(['priority' => 50]);
		$adapter = $this->createStub(IConfiguredParserServiceResolver::class);
		$adapter->method('resolve')->willReturn($parser);
		$resource = $this->resource($adapter);
		$this->assertSame(30, $resource->getPriority());
		$resource->setOptions(['priority' => 50]);
		$this->assertSame(['priority' => 50], $resource->getOptions());
	}

	public function testChangingServiceConfigInvalidatesPreviouslyResolvedAdapter(): void {
		$parser = $this->createStub(IParserService::class);
		$parser->method('getPriority')->willReturn(1);
		$adapter = $this->createMock(IConfiguredParserServiceResolver::class);
		$adapter->expects($this->exactly(2))->method('resolve')->willReturn($parser);
		$resource = $this->resource($adapter);
		$resource->getPriority();
		$resource->setConfig(['service' => 'other']);
		$resource->getPriority();
		$this->assertSame('other', $resource->getConfig()['service']);
	}

	public function testTechnicalIdentityAndDescriptionIdentifyParserResponsibility(): void {
		$adapter = $this->createStub(IConfiguredParserServiceResolver::class);
		$resource = $this->resource($adapter);
		$this->assertSame('configuredparserserviceagentresource', ConfiguredParserServiceAgentResource::getName());
		$this->assertStringContainsString('delegates content parsing', $resource->getDescription());
	}

	private function resource(IConfiguredParserServiceResolver $adapter): ConfiguredParserServiceAgentResource {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(static fn(mixed $value): mixed => $value);
		$resource = new ConfiguredParserServiceAgentResource($resolver, $this->createStub(ISettingsStore::class), $adapter, 'configured-parser');
		$resource->setConfig(['service' => 'parser']);
		return $resource;
	}

	private function item(): AgentContentItem {
		return new AgentContentItem('upsert', 'collection', 'item', 'hash', 'text/plain', 'Text', false, 4);
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Mcp\McpHostProviderRegistry;
use PHPUnit\Framework\TestCase;

final class McpHostProviderRegistryBehaviorTest extends TestCase {

	private array $resourcesBefore = [];
	private array $promptsBefore = [];

	protected function setUp(): void {
		parent::setUp();
		$this->resourcesBefore = McpHostProviderRegistry::getResourceProviders();
		$this->promptsBefore = McpHostProviderRegistry::getPromptProviders();
	}

	protected function tearDown(): void {
		// Static state is restored to protect subsequent framework tests.
		(new \ReflectionProperty(McpHostProviderRegistry::class, 'resourceProviders'))->setValue(null, $this->resourcesBefore);
		(new \ReflectionProperty(McpHostProviderRegistry::class, 'promptProviders'))->setValue(null, $this->promptsBefore);
		parent::tearDown();
	}

	public function testRegistryNameRemainsStable(): void {
		$this->assertSame('mcphostproviderregistry', McpHostProviderRegistry::getName());
	}

	public function testRegisteredResourceProviderIsAppendedInOrder(): void {
		$first = $this->createStub(IAgentResourceProvider::class);
		$second = $this->createStub(IAgentResourceProvider::class);
		McpHostProviderRegistry::addResourceProvider($first);
		McpHostProviderRegistry::addResourceProvider($second);
		$this->assertSame([...$this->resourcesBefore, $first, $second], McpHostProviderRegistry::getResourceProviders());
		$this->assertSame($this->promptsBefore, McpHostProviderRegistry::getPromptProviders());
	}

	public function testPromptProvidersDoNotChangeResourceRegistry(): void {
		$first = $this->createStub(IAgentPromptProvider::class);
		McpHostProviderRegistry::addPromptProvider($first);
		$this->assertSame([...$this->promptsBefore, $first], McpHostProviderRegistry::getPromptProviders());
		$this->assertSame($this->resourcesBefore, McpHostProviderRegistry::getResourceProviders());
	}
}

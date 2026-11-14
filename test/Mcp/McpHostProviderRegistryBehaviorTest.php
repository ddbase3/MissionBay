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

namespace Base3\MissionBay\Test\Mcp;

use Base3\MissionBay\Api\IAgentPromptProvider;
use Base3\MissionBay\Api\IAgentResourceProvider;
use Base3\MissionBay\Mcp\McpHostProviderRegistry;
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

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

use PHPUnit\Framework\TestCase;
use Base3\MissionBay\Resource\AbstractAgentResource;
use Base3\AssistantFoundation\Api\IAgentContext;

class AbstractAgentResourceTest extends TestCase {

	public function testConstructorGeneratesIdWhenNoneProvided(): void {
		$res = new TestAgentResource(null);

		$id = $res->getId();
		$this->assertNotSame('', $id);
		$this->assertStringStartsWith('resource_', $id);
	}

	public function testConstructorUsesProvidedId(): void {
		$res = new TestAgentResource('my_id');
		$this->assertSame('my_id', $res->getId());
	}

	public function testSetIdOverridesExistingId(): void {
		$res = new TestAgentResource('a');
		$res->setId('b');

		$this->assertSame('b', $res->getId());
	}

	public function testGetConfigReturnsDefaultEmptyArray(): void {
		$res = new TestAgentResource('x');
		$this->assertSame([], $res->getConfig());
	}

	public function testSetConfigStoresConfig(): void {
		$res = new TestAgentResource('x');

		$config = [
			'foo' => 'bar',
			'limit' => 123,
			'nested' => ['a' => 1],
		];

		$res->setConfig($config);
		$this->assertSame($config, $res->getConfig());
	}

	public function testGetDockDefinitionsReturnsEmptyArrayByDefault(): void {
		$res = new TestAgentResource('x');
		$this->assertSame([], $res->getDockDefinitions());
	}

	public function testInitIsNoOpByDefault(): void {
		$res = new TestAgentResource('x');

		$context = $this->createStub(IAgentContext::class);
		$resources = [
			'logger' => [],
			'memory' => [],
		];

		// Should not throw, and should keep state unchanged.
		$res->init($resources, $context);

		$this->assertSame('x', $res->getId());
		$this->assertSame([], $res->getConfig());
	}

	public function testAbstractMethodsAreImplementedByConcreteTestClass(): void {
		$this->assertSame('testagentresource', TestAgentResource::getName());

		$res = new TestAgentResource('x');
		$this->assertSame('Test resource description', $res->getDescription());
	}

}

require_once __DIR__ . '/TestAgentResource.php';

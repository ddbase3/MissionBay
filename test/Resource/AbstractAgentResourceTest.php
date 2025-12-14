<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\AbstractAgentResource;
use MissionBay\Api\IAgentContext;

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

class TestAgentResource extends AbstractAgentResource {

	public static function getName(): string {
		return 'testagentresource';
	}

	public function getDescription(): string {
		return 'Test resource description';
	}

}

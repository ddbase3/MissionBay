<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use MissionBay\Agent\AgentResourceFactory;
use MissionBay\Api\IAgentResource;
use Base3\Api\IClassMap;

#[AllowMockObjectsWithoutExpectations]
final class AgentResourceFactoryTest extends TestCase {

	public function testCreateResourceReturnsResourceWhenValid(): void {
		$resource = $this->createMock(IAgentResource::class);

		$classmap = $this->makeClassMapReturning($resource);

		$f = new AgentResourceFactory($classmap);
		$out = $f->createResource('x');

		$this->assertSame($resource, $out);
		$this->assertInstanceOf(IAgentResource::class, $out);
	}

	public function testCreateResourceReturnsNullWhenClassmapReturnsNull(): void {
		$classmap = $this->makeClassMapReturning(null);

		$f = new AgentResourceFactory($classmap);
		$out = $f->createResource('x');

		$this->assertNull($out);
	}

	public function testCreateResourceReturnsNullWhenClassmapReturnsWrongType(): void {
		$classmap = $this->makeClassMapReturning(new \stdClass());

		$f = new AgentResourceFactory($classmap);
		$out = $f->createResource('x');

		$this->assertNull($out);
	}

	private function makeClassMapReturning(mixed $instance): IClassMap {
		return new class($instance) implements IClassMap {

			private mixed $instance;

			public function __construct(mixed $instance) {
				$this->instance = $instance;
			}

			public function instantiate(string $class) {
				return null;
			}

			public function &getInstances(array $criteria = []) {
				$out = [];
				return $out;
			}

			public function getPlugins() {
				return [];
			}

			// Factory nutzt diese Methode (ist in deiner ClassMap-Implementierung vorhanden,
			// aber nicht im IClassMap-Interface deklariert).
			public function getInstanceByInterfaceName(string $interface, string $name) {
				return $this->instance;
			}
		};
	}
}

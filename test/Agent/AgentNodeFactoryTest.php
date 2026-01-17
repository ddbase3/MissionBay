<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use MissionBay\Agent\AgentNodeFactory;
use MissionBay\Api\IAgentNode;
use Base3\Api\IClassMap;

#[AllowMockObjectsWithoutExpectations]
final class AgentNodeFactoryTest extends TestCase {

	public function testCreateNodeReturnsNodeWhenValid(): void {
		$node = $this->createMock(IAgentNode::class);

		$classmap = $this->makeClassMapReturning($node);

		$f = new AgentNodeFactory($classmap);
		$out = $f->createNode('x');

		$this->assertSame($node, $out);
		$this->assertInstanceOf(IAgentNode::class, $out);
	}

	public function testCreateNodeReturnsNullWhenClassmapReturnsNull(): void {
		$classmap = $this->makeClassMapReturning(null);

		$f = new AgentNodeFactory($classmap);
		$out = $f->createNode('x');

		$this->assertNull($out);
	}

	public function testCreateNodeReturnsNullWhenClassmapReturnsWrongType(): void {
		$classmap = $this->makeClassMapReturning(new \stdClass());

		$f = new AgentNodeFactory($classmap);
		$out = $f->createNode('x');

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

			// AgentNodeFactory nutzt diese Methode (ist in deiner ClassMap-Implementierung vorhanden,
			// aber nicht im IClassMap-Interface deklariert).
			public function getInstanceByInterfaceName(string $interface, string $name) {
				return $this->instance;
			}
		};
	}
}

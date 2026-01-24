<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use MissionBay\Agent\AgentNodeFactory;
use MissionBay\Api\IAgentNode;
use Base3\Api\IClassMap;
use Base3\Test\Core\ClassMapStub;

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
		$cm = new ClassMapStub();

		if ($instance instanceof IAgentNode) {
			$cm->registerInstance($instance, 'x', [IAgentNode::class]);
			return $cm;
		}

		if ($instance instanceof \stdClass) {
			$cm->registerInstance($instance, 'x', [IAgentNode::class]);
			return $cm;
		}

		// null -> leave unregistered so lookup returns null
		return $cm;
	}
}

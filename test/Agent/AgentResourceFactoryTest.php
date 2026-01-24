<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use MissionBay\Agent\AgentResourceFactory;
use MissionBay\Api\IAgentResource;
use Base3\Api\IClassMap;
use Base3\Test\Core\ClassMapStub;

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
		$cm = new ClassMapStub();

		if ($instance instanceof IAgentResource) {
			$cm->registerInstance($instance, 'x', [IAgentResource::class]);
			return $cm;
		}

		if ($instance instanceof \stdClass) {
			$cm->registerInstance($instance, 'x', [IAgentResource::class]);
			return $cm;
		}

		// null -> leave unregistered so lookup returns null
		return $cm;
	}
}

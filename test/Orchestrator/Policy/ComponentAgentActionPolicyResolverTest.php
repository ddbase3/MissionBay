<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Policy;

use AssistantFoundation\Api\IAgentActionPolicy;
use Base3\Api\IComponentResolver;
use MissionBay\Orchestrator\Policy\ComponentAgentActionPolicyResolver;
use PHPUnit\Framework\TestCase;

final class ComponentAgentActionPolicyResolverTest extends TestCase {

	public function testResolvesOnlyRequestedIdsInOrder(): void {
		$first = $this->createStub(IAgentActionPolicy::class);
		$second = $this->createStub(IAgentActionPolicy::class);
		$seen = [];
		$components = $this->createStub(IComponentResolver::class);
		$components->method('get')->willReturnCallback(static function(string $interface, string $id) use (&$seen, $first, $second) {
			$seen[] = [$interface, $id];
			return $id === 'one' ? $first : $second;
		});
		$resolved = (new ComponentAgentActionPolicyResolver($components))->resolve([' two ', 'one']);
		$this->assertSame([$second, $first], $resolved);
		$this->assertSame([[IAgentActionPolicy::class, 'two'], [IAgentActionPolicy::class, 'one']], $seen);
	}

	public function testRejectsMissingConfiguredPolicy(): void {
		$components = $this->createStub(IComponentResolver::class);
		$components->method('get')->willReturn(null);
		$this->expectException(\RuntimeException::class);
		(new ComponentAgentActionPolicyResolver($components))->resolve(['missing']);
	}

	public function testRejectsInvalidIdSelectionsBeforeResolverIsCalled(): void {
		$components = $this->createStub(IComponentResolver::class);
		$resolver = new ComponentAgentActionPolicyResolver($components);
		foreach ([[], [null], [' '], [1], ['a', ' a ']] as $ids) {
			try {
				$resolver->resolve($ids);
				$this->fail('Invalid policy id accepted.');
			} catch (\RuntimeException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}
}

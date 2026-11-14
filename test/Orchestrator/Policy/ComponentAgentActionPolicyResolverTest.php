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

namespace Base3\MissionBay\Test\Orchestrator\Policy;

use Base3\AssistantFoundation\Api\IAgentActionPolicy;
use Base3\CoreFoundation\Api\IComponentResolver;
use Base3\MissionBay\Orchestrator\Policy\ComponentAgentActionPolicyResolver;
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

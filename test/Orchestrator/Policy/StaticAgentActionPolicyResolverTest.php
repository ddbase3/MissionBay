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
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use PHPUnit\Framework\TestCase;

final class StaticAgentActionPolicyResolverTest extends TestCase {

	public function testResolvesTrimmedIdsInRequestedOrder(): void {
		$first = $this->policy('first');
		$second = $this->policy('second');
		$resolver = new StaticAgentActionPolicyResolver([$first, $second]);
		$this->assertSame([$second, $first], $resolver->resolve([' second ', 'first']));
	}

	public function testRejectsEmptyPolicySet(): void {
		$this->expectException(\InvalidArgumentException::class);
		new StaticAgentActionPolicyResolver([]);
	}

	public function testRejectsNonPolicyValues(): void {
		$this->expectException(\InvalidArgumentException::class);
		new StaticAgentActionPolicyResolver([new \stdClass()]);
	}

	public function testRejectsBlankAndDuplicateDeclaredPolicyIds(): void {
		foreach ([[' ', 'valid'], ['first', ' first ']] as $ids) {
			try {
				new StaticAgentActionPolicyResolver([$this->policy($ids[0]), $this->policy($ids[1])]);
				$this->fail('Invalid policy identity accepted.');
			} catch (\InvalidArgumentException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}

	public function testRejectsEmptyInvalidAndRepeatedRequestedIds(): void {
		$resolver = new StaticAgentActionPolicyResolver([$this->policy('first')]);
		foreach ([[], [''], [7], ['missing'], ['first', ' first ']] as $ids) {
			try {
				$resolver->resolve($ids);
				$this->fail('Invalid policy selection accepted.');
			} catch (\RuntimeException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}

	private function policy(string $id): IAgentActionPolicy {
		$policy = $this->createStub(IAgentActionPolicy::class);
		$policy->method('id')->willReturn($id);
		return $policy;
	}
}

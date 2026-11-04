<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Policy;

use AssistantFoundation\Api\IAgentActionPolicy;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
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

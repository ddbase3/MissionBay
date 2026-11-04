<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Session\Api\ISession;
use PHPUnit\Framework\TestCase;

final class GlobalAgentKnowledgePrecedenceTest extends TestCase {

	public function testUserScopeOverridesSessionScope(): void {
		$this->assertTrue($this->service()->shouldReplace(['scope' => 'session'], ['scope' => 'user']));
	}

	public function testSessionScopeOverridesGlobalScope(): void {
		$this->assertTrue($this->service()->shouldReplace(['scope' => 'global'], ['scope' => 'session']));
	}

	public function testGlobalScopeCannotOverrideUserScope(): void {
		$this->assertFalse($this->service()->shouldReplace(['scope' => 'user'], ['scope' => 'global']));
	}

	public function testGlobalIdentityIsRecognizedWithoutScope(): void {
		$this->assertTrue($this->service()->shouldReplace(
			['scope' => 'other', 'ident' => 'g:global'],
			['scope' => 'session']
		));
	}

	public function testHigherPriorityOverridesWithinSameScope(): void {
		$this->assertTrue($this->service()->shouldReplace(
			['scope' => 'user', 'priority' => 3],
			['scope' => 'user', 'priority' => 4]
		));
	}

	public function testHigherPriorityOverridesEvenWithAnOlderTimestamp(): void {
		$this->assertTrue($this->service()->shouldReplace(
			['scope' => 'user', 'priority' => 3, 'updated_at' => '2026-10-01'],
			['scope' => 'user', 'priority' => 4, 'updated_at' => '2026-01-01']
		));
	}

	public function testMoreRecentTimestampBreaksEqualPriorityTie(): void {
		$this->assertTrue($this->service()->shouldReplace(
			['scope' => 'session', 'priority' => 1, 'updated_at' => '2026-01-01'],
			['scope' => 'session', 'priority' => 1, 'updated_at' => '2026-02-01']
		));
	}

	public function testSamePriorityAndTimestampKeepsExistingItem(): void {
		$this->assertFalse($this->service()->shouldReplace(
			['scope' => 'global', 'priority' => 1, 'updated_at' => '2026-01-01'],
			['scope' => 'global', 'priority' => 1, 'updated_at' => '2026-01-01']
		));
	}

	public function testGlobalEntryCanBeReadWithoutIdentityLookup(): void {
		$service = $this->service();
		$this->assertTrue($service->canReadGlobal(['scope' => 'global']));
		$this->assertTrue($service->canReadGlobal(['scope' => 'other', 'ident' => 'g:global']));
	}

	private function service(): GlobalAgentKnowledgeRankProbe {
		return new GlobalAgentKnowledgeRankProbe(
			$this->createStub(IDatabase::class),
			$this->createStub(IAccesscontrol::class),
			$this->createStub(ISession::class)
		);
	}
}

require_once __DIR__ . '/GlobalAgentKnowledgeRankProbe.php';

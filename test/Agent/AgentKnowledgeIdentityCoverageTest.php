<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Session\Api\ISession;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentKnowledgeRulesProbe.php';

final class AgentKnowledgeIdentityCoverageTest extends TestCase {

	public function testAuthenticatedUserHasDistinctUserAndSessionIdentity(): void {
		$service = $this->service('42', 'session-abc');
		$this->assertSame([
			'userid' => '42', 'session' => 'session-abc',
			'user_ident' => 'u:42', 'session_ident' => 's:session-abc'
		], $service->identityRefs());
	}

	public function testAnonymousIdentityHasOnlySessionReference(): void {
		$service = $this->service(null, 'session-abc');
		$this->assertSame(null, $service->identityRefs()['user_ident']);
		$this->assertSame('s:session-abc', $service->identityRefs()['session_ident']);
	}

	public function testAbsentSessionAndUserProduceNoIdentityReference(): void {
		$service = $this->service(null, '');
		$this->assertNull($service->identityRefs()['user_ident']);
		$this->assertNull($service->identityRefs()['session_ident']);
		$this->assertSame(['1=0'], $service->identityConditions(true));
	}

	public function testDefaultWriteScopePrefersAuthenticatedUser(): void {
		$this->assertSame('user', $this->service('42', 'session')->scopeFor(''));
		$this->assertSame('user', $this->service('42', 'session')->scopeFor('unknown'));
	}

	public function testAnonymousDefaultAndRequestedUserScopeUseSession(): void {
		$this->assertSame('session', $this->service(null, 'session')->scopeFor(''));
		$this->assertSame('session', $this->service(null, 'session')->scopeFor('user'));
	}

	public function testExplicitSessionScopeWinsOverAuthenticatedIdentity(): void {
		$this->assertSame('session', $this->service('42', 'session')->scopeFor(' SESSION '));
	}

	public function testUserScopeIdentifiersRetainSessionForAuditButUseUserIdentity(): void {
		$this->assertSame([
			'userid' => '42', 'session' => 'session-abc', 'ident' => 'u:42'
		], $this->service('42', 'session-abc')->scopeIdsFor('user'));
	}

	public function testSessionScopeDoesNotCarryUserIdIntoOwnerField(): void {
		$this->assertSame([
			'userid' => null, 'session' => 'session-abc', 'ident' => 's:session-abc'
		], $this->service('42', 'session-abc')->scopeIdsFor('session'));
	}

	public function testUserScopeWithoutUserFallsBackToSessionIdentity(): void {
		$this->assertSame('s:session-abc', $this->service(null, 'session-abc')->scopeIdsFor('user')['ident']);
	}

	public function testAuthorizedIdentitiesCanReadTheirOwnRows(): void {
		$service = $this->service('42', 'session-abc');
		$this->assertTrue($service->accessFor(['ident' => 'u:42']));
		$this->assertTrue($service->accessFor(['ident' => 's:session-abc']));
	}

	public function testOtherUsersAndSessionsCannotReadRows(): void {
		$service = $this->service('42', 'session-abc');
		$this->assertFalse($service->accessFor(['ident' => 'u:43']));
		$this->assertFalse($service->accessFor(['ident' => 's:session-other']));
		$this->assertFalse($service->accessFor(['ident' => '']));
		$this->assertFalse($service->accessFor([]));
	}

	public function testAnonymousIdentityCannotReadUserRows(): void {
		$service = $this->service(null, 'session-abc');
		$this->assertFalse($service->accessFor(['ident' => 'u:42']));
		$this->assertTrue($service->accessFor(['ident' => 's:session-abc']));
	}

	public function testIdentityConditionsIncludeBothWhenRequested(): void {
		$service = $this->service('42', 'session-abc');
		$this->assertSame(["`ident` IN ('s:session-abc','u:42')"], $service->identityConditions(true));
	}

	public function testIdentityConditionsCanRequireAuthenticatedUser(): void {
		$service = $this->service('42', 'session-abc');
		$this->assertSame(["`ident` IN ('u:42')"], $service->identityConditions(false));
	}

	public function testIdentityConditionsForAnonymousSessionAreRestrictive(): void {
		$service = $this->service(null, 'session-abc');
		$this->assertSame(["`ident` IN ('s:session-abc')"], $service->identityConditions(true));
		$this->assertSame(["`ident` IN ('s:session-abc')"], $service->identityConditions(false));
	}

	public function testMergePrefersAuthenticatedUserRowOverEarlierSessionRow(): void {
		$rows = [
			['id' => 1, 'memory_type' => 'semantic', 'memory_key' => 'policy', 'scope' => 'session', 'title' => 'old'],
			['id' => 2, 'memory_type' => 'semantic', 'memory_key' => ' POLICY ', 'scope' => 'user', 'title' => 'new']
		];
		$this->assertSame([2], array_column($this->service('42', 'session')->mergedEntries($rows), 'id'));
	}

	public function testMergeKeepsDifferentMemoryTypesAndKeysSeparate(): void {
		$rows = [
			['id' => 1, 'memory_type' => 'semantic', 'memory_key' => 'policy', 'scope' => 'session'],
			['id' => 2, 'memory_type' => 'procedural', 'memory_key' => 'policy', 'scope' => 'user'],
			['id' => 3, 'memory_type' => 'semantic', 'memory_key' => 'other', 'scope' => 'user']
		];
		$this->assertSame([1, 2, 3], array_column($this->service('42', 'session')->mergedEntries($rows), 'id'));
	}

	public function testMergeUsesSubtypeAndTitleWhenMemoryKeyIsMissing(): void {
		$rows = [
			['id' => 1, 'memory_type' => 'task', 'memory_subtype' => 'goal', 'title' => ' Prepare report ', 'scope' => 'session'],
			['id' => 2, 'memory_type' => 'task', 'memory_subtype' => 'goal', 'title' => 'prepare report', 'scope' => 'user']
		];
		$this->assertSame([2], array_column($this->service('42', 'session')->mergedEntries($rows), 'id'));
	}

	public function testMergeUsesHigherPriorityWithinSameScope(): void {
		$rows = [
			['id' => 1, 'memory_type' => 'semantic', 'memory_key' => 'k', 'scope' => 'user', 'priority' => 2],
			['id' => 2, 'memory_type' => 'semantic', 'memory_key' => 'k', 'scope' => 'user', 'priority' => 3]
		];
		$this->assertSame([2], array_column($this->service('42', 'session')->mergedEntries($rows), 'id'));
	}

	public function testMergeUsesMoreRecentTimestampForEqualPriority(): void {
		$rows = [
			['id' => 1, 'memory_type' => 'semantic', 'memory_key' => 'k', 'scope' => 'user', 'priority' => 2, 'updated_at' => '2025-01-01'],
			['id' => 2, 'memory_type' => 'semantic', 'memory_key' => 'k', 'scope' => 'user', 'priority' => 2, 'updated_at' => '2026-01-01']
		];
		$this->assertSame([2], array_column($this->service('42', 'session')->mergedEntries($rows), 'id'));
	}

	private function service(mixed $userId, string $sessionId): AgentKnowledgeRulesProbe {
		$db = $this->createStub(IDatabase::class);
		$db->method('escape')->willReturnCallback(static fn(string $s): string => addslashes($s));
		$accesscontrol = $this->createStub(IAccesscontrol::class);
		$accesscontrol->method('getUserId')->willReturn($userId);
		$session = $this->createStub(ISession::class);
		$session->method('getId')->willReturn($sessionId);
		return new AgentKnowledgeRulesProbe($db, $accesscontrol, $session);
	}
}

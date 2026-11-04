<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Session\Api\ISession;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentKnowledgeRulesProbe.php';

final class AgentKnowledgeValidityCoverageTest extends TestCase {

	public function testSupportedKnowledgeTypesAndDistinctStatusCatalogs(): void {
		$service = $this->service();
		$this->assertSame(['task', 'episodic', 'semantic', 'procedural'], $service->getMemoryTypes());
		$this->assertTrue(in_array('blocked', $service->getAllowedStatuses('task'), true));
		$this->assertTrue(in_array('valid', $service->getAllowedStatuses('semantic'), true));
		$this->assertTrue(in_array('archived', $service->getAllowedStatuses('procedural'), true));
		$this->assertSame([], $service->getAllowedStatuses('unknown'));
	}

	public function testNullAndEmptyStatusesAreValidForSupportedTypes(): void {
		$service = $this->service();
		foreach ($service->getMemoryTypes() as $type) {
			$this->assertTrue($service->isValidStatusForType($type, null));
			$this->assertTrue($service->isValidStatusForType($type, ''));
		}
	}

	public function testStatusesMustMatchTheirSpecificKnowledgeType(): void {
		$service = $this->service();
		$this->assertFalse($service->isValidStatusForType('task', 'valid'));
		$this->assertFalse($service->isValidStatusForType('semantic', 'blocked'));
		$this->assertFalse($service->isValidStatusForType('procedural', 'resolved'));
		$this->assertTrue($service->isValidStatusForType('episodic', 'resolved'));
	}

	public function testUnsupportedKnowledgeTypesAreRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service()->isValidStatusForType('private', 'valid');
	}

	public function testLockedKnowledgeCannotBeMutatedOrDeletedByLlm(): void {
		$service = $this->service();
		$this->assertFalse($service->isMutableByLlm(['is_locked' => 1, 'is_mutable_by_llm' => 1]));
		$this->assertFalse($service->isDeletableByLlm(['is_locked' => 1, 'is_deletable_by_llm' => 1]));
	}

	public function testLlmFlagsCanIndependentlyDisableMutationAndDeletion(): void {
		$service = $this->service();
		$this->assertFalse($service->isMutableByLlm(['is_mutable_by_llm' => 0]));
		$this->assertTrue($service->isDeletableByLlm(['is_mutable_by_llm' => 0]));
		$this->assertTrue($service->isMutableByLlm(['is_deletable_by_llm' => 0]));
		$this->assertFalse($service->isDeletableByLlm(['is_deletable_by_llm' => 0]));
	}

	public function testDefaultLlmFlagsAllowUnlockedKnowledge(): void {
		$service = $this->service();
		$this->assertTrue($service->isMutableByLlm([]));
		$this->assertTrue($service->isDeletableByLlm([]));
	}

	public function testValidityWindowAcceptsInclusiveBoundaries(): void {
		$service = $this->service();
		$entry = ['valid_from' => '2025-01-01 00:00:00', 'valid_to' => '2025-12-31 23:59:59'];
		$this->assertTrue($service->isEntryValidAt($entry, '2025-01-01 00:00:00'));
		$this->assertTrue($service->isEntryValidAt($entry, '2025-12-31 23:59:59'));
		$this->assertTrue($service->isEntryValidAt($entry, '2025-06-15 12:00:00'));
	}

	public function testValidityWindowRejectsDatesBeforeAndAfter(): void {
		$service = $this->service();
		$entry = ['valid_from' => '2025-01-01', 'valid_to' => '2025-12-31'];
		$this->assertFalse($service->isEntryValidAt($entry, '2024-12-31'));
		$this->assertFalse($service->isEntryValidAt($entry, '2026-01-01'));
	}

	public function testValidityWindowWithoutParsableDatesIsUnbounded(): void {
		$service = $this->service();
		$this->assertTrue($service->isEntryValidAt([], '2025-01-01'));
		$this->assertTrue($service->isEntryValidAt(['valid_from' => 'not-a-date', 'valid_to' => 'not-a-date'], '2025-01-01'));
	}

	public function testExpirationIsExclusiveOfExpiryTimestamp(): void {
		$service = $this->service();
		$entry = ['expires_at' => '2025-08-01 12:00:00'];
		$this->assertFalse($service->isEntryExpired($entry, '2025-08-01 11:59:59'));
		$this->assertTrue($service->isEntryExpired($entry, '2025-08-01 12:00:00'));
		$this->assertTrue($service->isEntryExpired($entry, '2025-08-02'));
	}

	public function testNoExpirationIsNotExpired(): void {
		$service = $this->service();
		$this->assertFalse($service->isEntryExpired([], '2025-08-01'));
		$this->assertFalse($service->isEntryExpired(['expires_at' => 'invalid-date'], '2025-08-01'));
	}

	public function testHydrationConvertsNumericAndJsonFields(): void {
		$row = $this->service()->hydratedEntry([
			'id' => '8', 'priority' => '3', 'is_locked' => '1',
			'is_mutable_by_llm' => '0', 'is_deletable_by_llm' => '1',
			'is_deleted' => '0', 'confidence' => '0.75',
			'tags_json' => '["alpha","beta"]',
			'entity_refs_json' => '{"doc":7}',
			'meta_json' => '{"pinned":true}'
		]);
		$this->assertSame(8, $row['id']);
		$this->assertSame(3, $row['priority']);
		$this->assertSame(1, $row['is_locked']);
		$this->assertSame(0, $row['is_mutable_by_llm']);
		$this->assertSame(0.75, $row['confidence']);
		$this->assertSame(['alpha', 'beta'], $row['tags_json']);
		$this->assertSame(['doc' => 7], $row['entity_refs_json']);
		$this->assertSame(['pinned' => true], $row['meta_json']);
	}

	public function testHydrationNormalizesMissingAndInvalidFields(): void {
		$row = $this->service()->hydratedEntry(['confidence' => null, 'tags_json' => '{oops', 'meta_json' => [], 'entity_refs_json' => '']);
		$this->assertNull($row['id']);
		$this->assertSame(0, $row['priority']);
		$this->assertSame(0, $row['is_locked']);
		$this->assertSame(1, $row['is_mutable_by_llm']);
		$this->assertSame(1, $row['is_deletable_by_llm']);
		$this->assertNull($row['confidence']);
		$this->assertNull($row['tags_json']);
		$this->assertSame([], $row['meta_json']);
		$this->assertNull($row['entity_refs_json']);
	}

	public function testBooleanSqlFlagsUseRecognizedValuesOnly(): void {
		$service = $this->service();
		foreach ([true, 1, -1, '1', 'true', 'YES', ' on '] as $value) {
			$this->assertTrue($service->boolForSql($value));
		}
		foreach ([false, 0, 'false', 'no', '', 'enabled', null] as $value) {
			$this->assertFalse($service->boolForSql($value));
		}
	}

	public function testKeyNormalizationAndTruncationAvoidUnnecessaryChanges(): void {
		$service = $this->service();
		$this->assertSame('alpha beta', $service->normalizedKey(' Alpha Beta '));
		$this->assertSame('', $service->normalizedKey(' '));
		$this->assertSame('alpha', $service->truncateForPrompt(' alpha ', 20));
		$this->assertSame('abc...', $service->truncateForPrompt('abcdefghij', 6));
		$this->assertSame('', $service->truncateForPrompt('  ', 6));
	}

	private function service(): AgentKnowledgeRulesProbe {
		return new AgentKnowledgeRulesProbe(
			$this->createStub(IDatabase::class),
			$this->createStub(IAccesscontrol::class),
			$this->createStub(ISession::class)
		);
	}
}

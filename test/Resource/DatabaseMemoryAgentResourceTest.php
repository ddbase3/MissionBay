<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\DatabaseMemoryAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;
use Base3\Database\Api\IDatabase;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Session\Api\ISession;
use Base3\Logger\Api\ILogger;

class DatabaseMemoryAgentResourceTest extends TestCase {

	protected function tearDown(): void {
		parent::tearDown();
		unset($_SERVER['REMOTE_ADDR']);
	}

	public function testGetNameAndDescriptionAndDockDefinitions(): void {
		$db = new DbMemoryDatabaseStub();
		$resolver = new DbMemoryConfigResolverStub();
		$ac = new DbMemoryAccesscontrolStub(null);
		$session = new DbMemorySessionStub('sess-1');

		$res = new DatabaseMemoryAgentResource($db, $resolver, $ac, $session, 'id1');

		$this->assertSame('databasememoryagentresource', DatabaseMemoryAgentResource::getName());
		$this->assertSame(
			'Provides database-backed node chat history using IDatabase. Supports user-aware sessions and logging.',
			$res->getDescription()
		);

		$docks = $res->getDockDefinitions();
		$this->assertIsArray($docks);
		$this->assertCount(1, $docks);
		$this->assertInstanceOf(\MissionBay\Agent\AgentNodeDock::class, $docks[0]);

		$this->assertSame('logger', $docks[0]->name ?? null);
	}

	public function testConstructorEnsuresTablesAreCreated(): void {
		$db = new DbMemoryDatabaseStub();
		$res = new DatabaseMemoryAgentResource(
			$db,
			new DbMemoryConfigResolverStub(),
			new DbMemoryAccesscontrolStub(null),
			new DbMemorySessionStub('sess-1'),
			'id1'
		);

		$this->assertTrue($db->connectCalled > 0);
		$this->assertGreaterThanOrEqual(2, $db->nonQueriesCountMatching('CREATE TABLE IF NOT EXISTS missionbay_memory_'));
		$this->assertSame('id1', $res->getId());
	}

	public function testSetConfigAppliesDefaultsAndResolverCasts(): void {
		$db = new DbMemoryDatabaseStub();
		$resolver = new DbMemoryConfigResolverStub();

		$res = new DatabaseMemoryAgentResource(
			$db,
			$resolver,
			new DbMemoryAccesscontrolStub(null),
			new DbMemorySessionStub('sess-1'),
			'id1'
		);

		$resolver->returnMap = [null => null];
		$res->setConfig([]);

		$this->assertSame(80, $res->getPriority());
		$this->assertSame('default', $this->readProp($res, 'namespace'));
		$this->assertSame(20, $this->readProp($res, 'max'));
		$this->assertSame(false, $this->readProp($res, 'trimHistory'));

		$resolver->returnMap = [
			'ns' => 'custom',
			'5' => '5',
			'99' => 99,
			'1' => 1,
		];

		$res->setConfig([
			'namespace' => 'ns',
			'max' => '5',
			'priority' => '99',
			'trim' => '1',
		]);

		$this->assertSame(99, $res->getPriority());
		$this->assertSame('custom', $this->readProp($res, 'namespace'));
		$this->assertSame(5, $this->readProp($res, 'max'));
		$this->assertSame(true, $this->readProp($res, 'trimHistory'));
	}

	public function testInitDocksLoggerAndWritesLog(): void {
		$db = new DbMemoryDatabaseStub();
		$res = new DatabaseMemoryAgentResource(
			$db,
			new DbMemoryConfigResolverStub(),
			new DbMemoryAccesscontrolStub(null),
			new DbMemorySessionStub('sess-1'),
			'id1'
		);

		$res->setConfig(['namespace' => 'x']);
		$logger = new DbMemoryLoggerStub();

		$context = new DbMemoryAgentContextStub();
		$res->init(['logger' => [$logger]], $context);

		$this->assertNotEmpty($logger->entries);
		$this->assertSame('dbmemory', $logger->entries[0][0]);
		$this->assertStringContainsString('logger docked into DatabaseMemoryAgentResource', $logger->entries[0][1]);
	}

	public function testInitIgnoresMissingOrWrongLogger(): void {
		$db = new DbMemoryDatabaseStub();
		$res = new DatabaseMemoryAgentResource(
			$db,
			new DbMemoryConfigResolverStub(),
			new DbMemoryAccesscontrolStub(null),
			new DbMemorySessionStub('sess-1'),
			'id1'
		);

		$context = new DbMemoryAgentContextStub();

		$res->init([], $context);
		$this->assertNull($this->readProp($res, 'logger'));

		$res->init(['logger' => [new \stdClass()]], $context);
		$this->assertNull($this->readProp($res, 'logger'));

		$res->init(['logger' => []], $context);
		$this->assertNull($this->readProp($res, 'logger'));
	}

	public function testLoadNodeHistoryMergesPayloadExtrasAndLogs(): void {
		$db = new DbMemoryDatabaseStub();
		$db->multiQueryResult = [
			[
				'messageid' => 'm1',
				'role' => 'user',
				'content' => 'hello',
				'payload' => json_encode(['timestamp' => 't', 'feedback' => 'f']),
			],
			[
				'messageid' => 'm2',
				'role' => 'assistant',
				'content' => 'hi',
				'payload' => 'not-json',
			],
			[
				'messageid' => 'm3',
				'role' => 'user',
				'content' => 'x',
				'payload' => null,
			],
		];

		$logger = new DbMemoryLoggerStub();

		$res = $this->makeResourceWithLogger($db, $logger);
		$out = $res->loadNodeHistory('node1');

		$this->assertSame([
			[
				'id' => 'm1',
				'role' => 'user',
				'content' => 'hello',
				'timestamp' => 't',
				'feedback' => 'f',
			],
			[
				'id' => 'm2',
				'role' => 'assistant',
				'content' => 'hi',
			],
			[
				'id' => 'm3',
				'role' => 'user',
				'content' => 'x',
			],
		], $out);

		$this->assertSame('dbmemory', $logger->lastScope());
		$this->assertStringContainsString('load history for node1: 3', $logger->lastMessage());
		$this->assertStringContainsString('[ns=default]', $logger->lastMessage());
	}

	public function testAppendNodeHistoryStoresExtrasInPayloadAndTrimsWhenEnabled(): void {
		$db = new DbMemoryDatabaseStub();
		$logger = new DbMemoryLoggerStub();

		$res = $this->makeResourceWithLogger($db, $logger);

		// IMPORTANT: interface expects array|string|null into resolver, so use strings.
		$res->setConfig([
			'trim' => '1',
			'max' => '1',
		]);

		$db->multiQueryResult = [
			['id' => 10],
			['id' => 11],
		];

		$res->appendNodeHistory('node1', [
			'id' => 'm1',
			'role' => 'user',
			'content' => 'hello',
			'timestamp' => 't',
			'feedback' => 'f',
		]);

		$insert = $db->lastNonQueryContaining('INSERT INTO missionbay_memory_message');
		$this->assertNotNull($insert);
		$this->assertStringContainsString("'node1'", $insert);
		$this->assertStringContainsString("'m1'", $insert);
		$this->assertStringContainsString("'user'", $insert);
		$this->assertStringContainsString("'hello'", $insert);
		$this->assertStringContainsString('\\"timestamp\\":\\"t\\"', $insert);
		$this->assertStringContainsString('\\"feedback\\":\\"f\\"', $insert);

		$delete = $db->lastNonQueryContaining('DELETE FROM missionbay_memory_message WHERE id IN (');
		$this->assertNotNull($delete);
		$this->assertStringContainsString('10,11', $delete);

		$this->assertStringContainsString('append message for node1', $logger->lastMessage());
	}

	public function testAppendNodeHistoryPayloadNullAndNoTrimWhenDisabled(): void {
		$db = new DbMemoryDatabaseStub();
		$logger = new DbMemoryLoggerStub();

		$res = $this->makeResourceWithLogger($db, $logger);

		// IMPORTANT: use string '0' to satisfy resolver input type
		$res->setConfig([
			'trim' => '0',
		]);

		$res->appendNodeHistory('node1', [
			'role' => 'assistant',
			'content' => 'hi',
		]);

		$insert = $db->lastNonQueryContaining('INSERT INTO missionbay_memory_message');
		$this->assertNotNull($insert);

		$this->assertStringContainsString('NULL', $insert);
		$this->assertNull($db->lastNonQueryContaining('DELETE FROM missionbay_memory_message WHERE id IN'));
	}

	public function testSetFeedbackReturnsFalseIfMessageNotFoundAndLogs(): void {
		$db = new DbMemoryDatabaseStub();
		$db->singleQueryResult = null;

		$logger = new DbMemoryLoggerStub();
		$res = $this->makeResourceWithLogger($db, $logger);

		$ok = $res->setFeedback('node1', 'm1', 'good');
		$this->assertFalse($ok);
		$this->assertStringContainsString('setFeedback failed', $logger->lastMessage());
	}

	public function testSetFeedbackHandlesInvalidPayloadAndReturnsAffectedRowsResult(): void {
		$db = new DbMemoryDatabaseStub();
		$logger = new DbMemoryLoggerStub();
		$res = $this->makeResourceWithLogger($db, $logger);

		$db->singleQueryResult = ['payload' => 'not-json'];
		$db->affectedRowsValue = 1;

		$ok = $res->setFeedback('node1', 'm1', null);
		$this->assertTrue($ok);

		$update = $db->lastNonQueryContaining('UPDATE missionbay_memory_message');
		$this->assertNotNull($update);

		// JSON is escaped in SQL: {\"feedback\":null}
		$this->assertStringContainsString('{\"feedback\":null}', $update);

		$this->assertStringContainsString('(ok=yes)', $logger->lastMessage());

		$db->singleQueryResult = ['payload' => json_encode(['x' => 1])];
		$db->affectedRowsValue = 0;

		$ok2 = $res->setFeedback('node1', 'm2', 'nice');
		$this->assertFalse($ok2);

		$update2 = $db->lastNonQueryContaining('UPDATE missionbay_memory_message');
		$this->assertNotNull($update2);
		$this->assertStringContainsString('{\"x\":1,\"feedback\":\"nice\"}', $update2);

		$this->assertStringContainsString('(ok=no)', $logger->lastMessage());
	}

	public function testResetNodeHistoryDeletesAndLogs(): void {
		$db = new DbMemoryDatabaseStub();
		$logger = new DbMemoryLoggerStub();

		$res = $this->makeResourceWithLogger($db, $logger);
		$res->resetNodeHistory('nodeA');

		$delete = $db->lastNonQueryContaining('DELETE FROM missionbay_memory_message');
		$this->assertNotNull($delete);
		$this->assertStringContainsString("nodeid='nodeA'", $delete);

		$this->assertStringContainsString('reset history for nodeA', $logger->lastMessage());
	}

	public function testEnsureSessionUsesRemoteAddrIfSetAndUserIdIfPresent(): void {
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$db = new DbMemoryDatabaseStub();
		$db->insertIdValue = 77;

		$res = new DatabaseMemoryAgentResource(
			$db,
			new DbMemoryConfigResolverStub(),
			new DbMemoryAccesscontrolStub('u1'),
			new DbMemorySessionStub('sess-xyz'),
			'id1'
		);

		$db->multiQueryResult = [];
		$res->loadNodeHistory('node1');

		$insert = $db->lastNonQueryContaining('INSERT INTO missionbay_memory_session');
		$this->assertNotNull($insert);
		$this->assertStringContainsString("'sess-xyz'", $insert);
		$this->assertStringContainsString("'u1'", $insert);

		$iphash = hash('sha256', '127.0.0.1');
		$this->assertStringContainsString("'" . $iphash . "'", $insert);
	}

	public function testEnsureSessionUsesNullsIfRemoteAddrAndUserIdMissing(): void {
		unset($_SERVER['REMOTE_ADDR']);

		$db = new DbMemoryDatabaseStub();
		$db->insertIdValue = 88;

		$res = new DatabaseMemoryAgentResource(
			$db,
			new DbMemoryConfigResolverStub(),
			new DbMemoryAccesscontrolStub(null),
			new DbMemorySessionStub('sess-xyz'),
			'id1'
		);

		$db->multiQueryResult = [];
		$res->loadNodeHistory('node1');

		$insert = $db->lastNonQueryContaining('INSERT INTO missionbay_memory_session');
		$this->assertNotNull($insert);
		$this->assertStringContainsString("VALUES ('sess-xyz', NULL, NULL)", $insert);
	}

	private function makeResourceWithLogger(DbMemoryDatabaseStub $db, DbMemoryLoggerStub $logger): DatabaseMemoryAgentResource {
		$res = new DatabaseMemoryAgentResource(
			$db,
			new DbMemoryConfigResolverStub(),
			new DbMemoryAccesscontrolStub(null),
			new DbMemorySessionStub('sess-1'),
			'id1'
		);

		$res->init(['logger' => [$logger]], new DbMemoryAgentContextStub());
		return $res;
	}

	private function readProp(object $obj, string $prop): mixed {
		$r = new \ReflectionObject($obj);
		$p = $r->getProperty($prop);
		$p->setAccessible(true);
		return $p->getValue($obj);
	}

}

/**
 * ---- Unique stubs for this test file ----
 */

class DbMemoryConfigResolverStub implements IAgentConfigValueResolver {

	/** @var array<mixed,mixed> */
	public array $returnMap = [];

	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		$key = $config;
		if (is_array($key)) {
			$key = json_encode($key);
		}
		if (array_key_exists($key, $this->returnMap)) {
			return $this->returnMap[$key];
		}
		return $config;
	}

}

class DbMemoryAccesscontrolStub implements IAccesscontrol {

	public function __construct(private mixed $userId) {}

	public function getUserId() {
		return $this->userId;
	}

	public function authenticate(): void {}

}

class DbMemorySessionStub implements ISession {

	private bool $started = false;

	public function __construct(private string $id) {}

	public function started(): bool { return $this->started; }
	public function getId(): string { return $this->id; }

	public function start(): bool {
		$this->started = true;
		return true;
	}

	public function destroy(): bool {
		$this->started = false;
		return true;
	}

	public function get(string $key, mixed $default = null): mixed { return $default; }
	public function set(string $key, mixed $value): void {}
	public function has(string $key): bool { return false; }
	public function remove(string $key): void {}

}

class DbMemoryLoggerStub implements ILogger {

	/** @var array<int, array{0:string,1:string,2:?int}> */
	public array $entries = [];

	public function lastScope(): ?string {
		return $this->entries ? $this->entries[count($this->entries) - 1][0] : null;
	}

	public function lastMessage(): ?string {
		return $this->entries ? $this->entries[count($this->entries) - 1][1] : null;
	}

	public function log(string $scope, string $log, ?int $timestamp = null): bool {
		$this->entries[] = [$scope, $log, $timestamp];
		return true;
	}

	public function emergency(string|\Stringable $message, array $context = []): void {}
	public function alert(string|\Stringable $message, array $context = []): void {}
	public function critical(string|\Stringable $message, array $context = []): void {}
	public function error(string|\Stringable $message, array $context = []): void {}
	public function warning(string|\Stringable $message, array $context = []): void {}
	public function notice(string|\Stringable $message, array $context = []): void {}
	public function info(string|\Stringable $message, array $context = []): void {}
	public function debug(string|\Stringable $message, array $context = []): void {}
	public function logLevel(string $level, string|\Stringable $message, array $context = []): void {}
	public function getScopes(): array { return []; }
	public function getNumOfScopes() { return 0; }
	public function getLogs(string $scope, int $num = 50, bool $reverse = true): array { return []; }

}

class DbMemoryAgentContextStub implements IAgentContext {

	private array $vars = [];
	private IAgentMemory $memory;

	public function __construct() {
		$this->memory = new DbMemoryDummyMemory();
	}

	public static function getName(): string {
		return 'dbmemoryagentcontextstub';
	}

	public function getMemory(): IAgentMemory { return $this->memory; }
	public function setMemory(IAgentMemory $memory): void { $this->memory = $memory; }

	public function setVar(string $key, mixed $value): void { $this->vars[$key] = $value; }
	public function getVar(string $key): mixed { return $this->vars[$key] ?? null; }
	public function forgetVar(string $key): void { unset($this->vars[$key]); }
	public function listVars(): array { return array_keys($this->vars); }

}

class DbMemoryDummyMemory implements IAgentMemory {

	public static function getName(): string {
		return 'dbmemorydummymemory';
	}

	public function loadNodeHistory(string $nodeId): array { return []; }
	public function appendNodeHistory(string $nodeId, array $message): void {}
	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
	public function resetNodeHistory(string $nodeId): void {}
	public function getPriority(): int { return 0; }

}

class DbMemoryDatabaseStub implements IDatabase {

	public int $connectCalled = 0;

	/** @var string[] */
	public array $nonQueries = [];

	/** @var array */
	public array $multiQueryResult = [];

	public mixed $singleQueryResult = null;

	public int $affectedRowsValue = 1;
	public int|string $insertIdValue = 1;

	public function connect(): void {
		$this->connectCalled++;
	}

	public function connected(): bool {
		return true;
	}

	public function disconnect(): void {
	}

	public function nonQuery(string $query): void {
		$this->nonQueries[] = $query;
	}

	public function scalarQuery(string $query): mixed {
		return null;
	}

	public function singleQuery(string $query): ?array {
		$this->nonQueries[] = $query;
		return $this->singleQueryResult;
	}

	public function &listQuery(string $query): array {
		$out = [];
		return $out;
	}

	public function &multiQuery(string $query): array {
		$this->nonQueries[] = $query;
		$out = $this->multiQueryResult;
		return $out;
	}

	public function affectedRows(): int {
		return $this->affectedRowsValue;
	}

	public function insertId(): int|string {
		return $this->insertIdValue;
	}

	public function escape(string $str): string {
		return addslashes($str);
	}

	public function isError(): bool {
		return false;
	}

	public function errorNumber(): int {
		return 0;
	}

	public function errorMessage(): string {
		return '';
	}

	public function nonQueriesCountMatching(string $needle): int {
		$c = 0;
		foreach ($this->nonQueries as $q) {
			if (str_contains($q, $needle)) {
				$c++;
			}
		}
		return $c;
	}

	public function lastNonQueryContaining(string $needle): ?string {
		for ($i = count($this->nonQueries) - 1; $i >= 0; $i--) {
			if (str_contains($this->nonQueries[$i], $needle)) {
				return $this->nonQueries[$i];
			}
		}
		return null;
	}
}

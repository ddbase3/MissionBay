<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use Base3\Session\Api\ISession;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContext;
use MissionBay\Resource\UserPrefsAgentResource;

/**
 * @covers \MissionBay\Resource\UserPrefsAgentResource
 */
class UserPrefsAgentResourceTest extends TestCase {

	private function makeResolverStub(array $map): IAgentConfigValueResolver {
		return new class($map) implements IAgentConfigValueResolver {
			private array $map;

			public function __construct(array $map) {
				$this->map = $map;
			}

			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				$key = is_array($config) ? json_encode($config) : (string)$config;
				if (array_key_exists($key, $this->map)) {
					return $this->map[$key];
				}
				return $config;
			}
		};
	}

	private function makeSessionStub(string $id): ISession {
		$s = $this->createStub(ISession::class);
		$s->method('start')->willReturn(true);
		$s->method('getId')->willReturn($id);
		return $s;
	}

	private function makeAccesscontrolStub(?string $userId): IAccesscontrol {
		$a = $this->createStub(IAccesscontrol::class);
		$a->method('getUserId')->willReturn($userId);
		return $a;
	}

	private function makeDatabaseStub(): IDatabase {
		$db = $this->createStub(IDatabase::class);

		// connect() is void: do not configure return values.
		$db->method('connect');

		// nonQuery() is void/mixed: do not configure return values.
		$db->method('nonQuery');

		$db->method('affectedRows')->willReturn(0);

		$db->method('escape')->willReturnCallback(function (string $s): string {
			return $s;
		});

		$db->method('multiQuery')->willReturn([]);
		$db->method('singleQuery')->willReturn(null);

		return $db;
	}

	public function testGetName(): void {
		$this->assertSame('userprefsagentresource', UserPrefsAgentResource::getName());
	}

	public function testGetDescription(): void {
		$r = new UserPrefsAgentResource(
			database: $this->makeDatabaseStub(),
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub(null),
			session: $this->makeSessionStub('s1'),
			id: 'x1'
		);

		$this->assertSame(
			'Stores user/session preferences via tool calls and injects them as system prompt addendum via memory.',
			$r->getDescription()
		);
	}

	public function testConstructorEnsuresTables(): void {
		$db = $this->createMock(IDatabase::class);

		$db->expects($this->once())
			->method('connect');

		$db->expects($this->exactly(2))
			->method('nonQuery')
			->with($this->callback(function (string $sql): bool {
				$s = strtolower($sql);
				return str_contains($s, 'create table if not exists base3_missionbay_userpref_def')
					|| str_contains($s, 'create table if not exists base3_missionbay_userpref_value');
			}));

		new UserPrefsAgentResource(
			database: $db,
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub(null),
			session: $this->makeSessionStub('s1'),
			id: 'x2'
		);

		$this->assertTrue(true);
	}

	public function testSetConfigResolvesPriorityAndSystemTitle(): void {
		$db = $this->makeDatabaseStub();

		$resolver = $this->createMock(IAgentConfigValueResolver::class);
		$resolver->expects($this->exactly(2))
			->method('resolveValue')
			->willReturnMap([
				['prio_spec', 99],
				['title_spec', 'Prefs Title']
			]);

		$r = new UserPrefsAgentResource(
			database: $db,
			resolver: $resolver,
			accesscontrol: $this->makeAccesscontrolStub(null),
			session: $this->makeSessionStub('s1'),
			id: 'x3'
		);

		$r->setConfig([
			'priority' => 'prio_spec',
			'systemtitle' => 'title_spec'
		]);

		$this->assertSame(99, $r->getPriority());

		// With no pref defs/values, loadNodeHistory returns [] (documented behavior).
		$this->assertSame([], $r->loadNodeHistory('n1'));
	}

	public function testInitDocksLoggerAndLogs(): void {
		$r = new UserPrefsAgentResource(
			database: $this->makeDatabaseStub(),
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub(null),
			session: $this->makeSessionStub('s1'),
			id: 'x4'
		);

		$logger = $this->createMock(ILogger::class);
		$logger->expects($this->once())
			->method('log')
			->with(
				'userprefsagentresource',
				$this->callback(function (string $msg): bool {
					return str_contains($msg, 'logger docked into UserPrefsAgentResource');
				})
			);

		$r->init(['logger' => [$logger]], $this->createStub(IAgentContext::class));

		$this->assertTrue(true);
	}

	public function testLoadNodeHistoryBuildsSystemMessageFromMergedValues(): void {
		$db = $this->createMock(IDatabase::class);
		$db->method('connect');
		$db->method('nonQuery');
		$db->method('escape')->willReturnCallback(function (string $s): string {
			return $s;
		});

		$db->expects($this->exactly(2))
			->method('multiQuery')
			->willReturnOnConsecutiveCalls(
				[
					[
						'pref_key' => 'language',
						'description' => 'Language',
						'system_template' => 'User language: {{value}}',
						'value_type' => 'string',
						'allowed_values' => null,
						'default_scope' => 'user',
						'sort_order' => 10,
						'enabled' => 1
					],
					[
						'pref_key' => 'compact',
						'description' => 'Compact mode',
						'system_template' => 'Use compact mode.',
						'value_type' => 'bool',
						'allowed_values' => null,
						'default_scope' => 'session',
						'sort_order' => 20,
						'enabled' => 1
					]
				],
				[
					// Session first
					['scope' => 'session', 'pref_key' => 'compact', 'pref_value' => '0', 'updated' => '2026-01-01 00:00:00'],
					['scope' => 'session', 'pref_key' => 'language', 'pref_value' => 'de', 'updated' => '2026-01-01 00:00:00'],
					// User overrides session
					['scope' => 'user', 'pref_key' => 'language', 'pref_value' => 'en', 'updated' => '2026-01-02 00:00:00']
				]
			);

		$r = new UserPrefsAgentResource(
			database: $db,
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub('42'),
			session: $this->makeSessionStub('sess-1'),
			id: 'x5'
		);

		$r->setConfig([
			'priority' => 20,
			'systemtitle' => 'User preferences'
		]);

		$history = $r->loadNodeHistory('node1');

		$this->assertCount(1, $history);
		$this->assertSame('system', $history[0]['role']);
		$this->assertSame("User preferences:\n- User language: en", $history[0]['content']);
	}

	public function testGetToolDefinitionsContainsExpectedFunctionNames(): void {
		$r = new UserPrefsAgentResource(
			database: $this->makeDatabaseStub(),
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub(null),
			session: $this->makeSessionStub('s1'),
			id: 'x6'
		);

		$defs = $r->getToolDefinitions();
		$this->assertIsArray($defs);
		$this->assertCount(4, $defs);

		$names = [];
		foreach ($defs as $d) {
			$names[] = $d['function']['name'] ?? null;
		}

		$this->assertSame(
			['list_allowed_prefs', 'set_user_pref', 'unset_user_pref', 'list_user_prefs'],
			$names
		);
	}

	public function testCallToolThrowsOnUnsupportedTool(): void {
		$r = new UserPrefsAgentResource(
			database: $this->makeDatabaseStub(),
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub(null),
			session: $this->makeSessionStub('s1'),
			id: 'x7'
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported tool: nope');

		$r->callTool('nope', [], $this->createStub(IAgentContext::class));
	}

	public function testCallToolSetUserPrefReturnsErrorOnMissingKey(): void {
		$r = new UserPrefsAgentResource(
			database: $this->makeDatabaseStub(),
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub(null),
			session: $this->makeSessionStub('s1'),
			id: 'x8'
		);

		$out = $r->callTool('set_user_pref', ['value' => 'x'], $this->createStub(IAgentContext::class));

		$this->assertSame(['error' => 'Missing parameter: key'], $out);
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use Base3\Session\Api\ISession;
use MissionBay\Api\IAgentConfigValueResolver;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentMutationGuardedTool;
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
			'Stores user/session preferences via tool calls and contributes them to the system context.',
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

	public function testSetConfigResolvesPriority(): void {
		$db = $this->makeDatabaseStub();

		$resolver = $this->createMock(IAgentConfigValueResolver::class);
		$resolver->expects($this->once())
			->method('resolveValue')
			->with('prio_spec')
			->willReturn(99);

		$r = new UserPrefsAgentResource(
			database: $db,
			resolver: $resolver,
			accesscontrol: $this->makeAccesscontrolStub(null),
			session: $this->makeSessionStub('s1'),
			id: 'x3'
		);

		$r->setConfig(['priority' => 'prio_spec']);

		$this->assertSame(99, $r->getPriority());

		// With no preference definitions or values, the contributor stays silent.
		$this->assertSame([], [...$r->contribute($this->createStub(IAgentContext::class))]);
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

	public function testContributeBuildsSystemMessageFromMergedValues(): void {
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

		$blocks = [...$r->contribute($this->createStub(IAgentContext::class))];

		$this->assertCount(1, $blocks);
		$this->assertSame("User preferences:\n- User language: en", $blocks[0]->getContent());
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


	public function testToolDefinitionsSeparateReadOnlyAndUnconfirmedPreferenceWrites(): void {
		$r = new UserPrefsAgentResource(
			database: $this->makeDatabaseStub(),
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub('42'),
			session: $this->makeSessionStub('sess-1'),
			id: 'prefs-main'
		);

		$this->assertNotInstanceOf(IAgentMutationGuardedTool::class, $r);

		$definitions = [];
		foreach ($r->getToolDefinitions() as $definition) {
			$definitions[$definition['function']['name']] = $definition;
		}

		$this->assertTrue($definitions['list_allowed_prefs']['readOnlyHint']);
		$this->assertFalse($definitions['list_allowed_prefs']['mutation']);
		$this->assertFalse($definitions['list_allowed_prefs']['requiresApproval']);
		$this->assertTrue($definitions['list_user_prefs']['readOnlyHint']);
		$this->assertFalse($definitions['list_user_prefs']['mutation']);
		$this->assertFalse($definitions['list_user_prefs']['requiresApproval']);
		$this->assertTrue($definitions['set_user_pref']['mutation']);
		$this->assertFalse($definitions['set_user_pref']['requiresApproval']);
		$this->assertFalse($definitions['set_user_pref']['commitGuardRequired']);
		$this->assertTrue($definitions['unset_user_pref']['mutation']);
		$this->assertFalse($definitions['unset_user_pref']['requiresApproval']);
		$this->assertFalse($definitions['unset_user_pref']['commitGuardRequired']);
	}

	public function testToolDefinitionsExposeExactPreferenceKeysAndValues(): void {
		$definition = $this->preferenceDefinition();
		$db = $this->createMock(IDatabase::class);
		$db->method('connect');
		$db->method('nonQuery');
		$db->method('multiQuery')->willReturn([$definition]);

		$resource = new UserPrefsAgentResource(
			database: $db,
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub('42'),
			session: $this->makeSessionStub('sess-1'),
			id: 'prefs-main'
		);

		$definitions = [];
		foreach ($resource->getToolDefinitions() as $toolDefinition) {
			$definitions[$toolDefinition['function']['name']] = $toolDefinition;
		}

		$this->assertSame(
			['address_form'],
			$definitions['set_user_pref']['function']['parameters']['properties']['key']['enum']
		);
		$this->assertStringContainsString(
			'address_form=[Du|Sie]',
			$definitions['set_user_pref']['function']['parameters']['properties']['value']['description']
		);
		$this->assertStringContainsString(
			'Call this before set_user_pref or unset_user_pref',
			$definitions['list_allowed_prefs']['function']['description']
		);
	}

	public function testListAllowedPreferencesReturnsDecodedAllowedValues(): void {
		$definition = $this->preferenceDefinition();
		$db = $this->createMock(IDatabase::class);
		$db->method('connect');
		$db->method('nonQuery');
		$db->method('multiQuery')->willReturn([$definition]);

		$resource = new UserPrefsAgentResource(
			database: $db,
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub('42'),
			session: $this->makeSessionStub('sess-1'),
			id: 'prefs-main'
		);

		$result = $resource->callTool(
			'list_allowed_prefs',
			['enabled_only' => true],
			$this->createStub(IAgentContext::class)
		);

		$this->assertSame(['Du', 'Sie'], $result['allowed'][0]['allowed_values']);
	}

	public function testListCurrentPreferencesIncludesExactDefinitions(): void {
		$definition = $this->preferenceDefinition();
		$value = $this->preferenceValue('user', 'u:42', 'Sie', 1);
		$db = $this->createMock(IDatabase::class);
		$db->method('connect');
		$db->method('nonQuery');
		$db->method('escape')->willReturnCallback(static fn(string $input): string => $input);
		$db->method('multiQuery')->willReturnCallback(
			static function (string $sql) use ($definition, $value): array {
				if (str_contains($sql, 'base3_missionbay_userpref_value')) {
					return [$value];
				}

				return [$definition];
			}
		);

		$resource = new UserPrefsAgentResource(
			database: $db,
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub('42'),
			session: $this->makeSessionStub('sess-1'),
			id: 'prefs-main'
		);

		$result = $resource->callTool(
			'list_user_prefs',
			['scope' => 'user'],
			$this->createStub(IAgentContext::class)
		);

		$this->assertSame('address_form', $result['prefs'][0]['definition']['key']);
		$this->assertSame(['Du', 'Sie'], $result['prefs'][0]['definition']['allowed_values']);
		$this->assertSame(['Du', 'Sie'], $result['allowed'][0]['allowed_values']);
	}

	public function testSetPreferenceCanonicalizesAllowedEnumCase(): void {
		$definition = $this->preferenceDefinition();
		$db = $this->createMock(IDatabase::class);
		$db->method('connect');
		$db->method('nonQuery');
		$db->method('affectedRows')->willReturn(0);
		$db->method('escape')->willReturnCallback(static fn(string $input): string => $input);
		$db->method('singleQuery')->willReturn($definition);

		$resource = new UserPrefsAgentResource(
			database: $db,
			resolver: $this->makeResolverStub([]),
			accesscontrol: $this->makeAccesscontrolStub('42'),
			session: $this->makeSessionStub('sess-1'),
			id: 'prefs-main'
		);

		$result = $resource->callTool(
			'set_user_pref',
			['key' => 'address_form', 'value' => 'du', 'scope' => 'user'],
			$this->createStub(IAgentContext::class)
		);

		$this->assertTrue($result['ok']);
		$this->assertSame('Du', $result['value']);
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


	/** @return array<string,mixed> */
	private function preferenceDefinition(): array {
		return [
			'pref_key' => 'address_form',
			'description' => 'Address form',
			'system_template' => 'Address the user as {{value}}.',
			'value_type' => 'enum',
			'allowed_values' => '["Du","Sie"]',
			'default_scope' => 'user',
			'sort_order' => 10,
			'enabled' => 1,
			'updated' => '2026-07-12 09:00:00'
		];
	}

	/** @return array<string,mixed> */
	private function preferenceValue(string $scope, string $ident, string $value, int $id): array {
		return [
			'id' => $id,
			'scope' => $scope,
			'ident' => $ident,
			'pref_key' => 'address_form',
			'pref_value' => $value,
			'updated' => '2026-07-12 10:00:00'
		];
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

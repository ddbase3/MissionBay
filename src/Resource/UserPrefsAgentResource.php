<?php declare(strict_types=1);

namespace MissionBay\Resource;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use Base3\Session\Api\ISession;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentTool;

class UserPrefsAgentResource extends AbstractAgentResource implements IAgentMemory, IAgentTool {

	private ?ILogger $logger = null;

	private int $priority = 20;
	private string $systemTitle = 'User preferences';

	public function __construct(
		private readonly IDatabase $database,
		private readonly IAgentConfigValueResolver $resolver,
		private readonly IAccesscontrol $accesscontrol,
		private readonly ISession $session,
		?string $id = null
	) {
		parent::__construct($id);
		$this->ensureTables();
	}

	public static function getName(): string {
		return 'userprefsagentresource';
	}

	public function getDescription(): string {
		return 'Stores user/session preferences via tool calls and injects them as system prompt addendum via memory.';
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger for user prefs events.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->priority = (int)($this->resolver->resolveValue($config['priority'] ?? null) ?? 20);
		$this->systemTitle = (string)($this->resolver->resolveValue($config['systemtitle'] ?? null) ?? 'User preferences');
	}

	public function init(array $resources, IAgentContext $context): void {
		if (!empty($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
			$this->log('logger docked into UserPrefsAgentResource');
		}
	}

	// ----------------------------------------------------
	// IAgentMemory
	// ----------------------------------------------------

	public function loadNodeHistory(string $nodeId): array {
		$lines = $this->buildSystemLines();
		if (!$lines) {
			return [];
		}

		$content = $this->systemTitle . ":\n- " . implode("\n- ", $lines);

		return [[
			'role' => 'system',
			'content' => $content
		]];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		// no-op (preferences are not chat history)
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		// no-op
		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		// no-op
	}

	public function getPriority(): int {
		return $this->priority;
	}

	// ----------------------------------------------------
	// IAgentTool
	// ----------------------------------------------------

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'User Preferences',
			'category' => 'memory',
			'tags' => ['preferences', 'memory', 'user', 'session'],
			'priority' => 60,
			'function' => [
				'name' => 'list_allowed_prefs',
				'description' => 'Lists allowed user preference keys and their metadata.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'enabled_only' => [
							'type' => 'boolean',
							'description' => 'If true, only returns enabled preference definitions.'
						]
					]
				]
			]
		], [
			'type' => 'function',
			'label' => 'Set User Preference',
			'category' => 'memory',
			'tags' => ['preferences', 'memory', 'user', 'session'],
			'priority' => 60,
			'function' => [
				'name' => 'set_user_pref',
				'description' => 'Sets a user/session preference value by key.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'key' => [
							'type' => 'string',
							'description' => 'Preference key (must be allowed).'
						],
						'value' => [
							'description' => 'Preference value (type depends on key).'
						],
						'scope' => [
							'type' => 'string',
							'description' => 'Optional scope: "user" or "session". Resource will decide final scope.'
						]
					],
					'required' => ['key', 'value']
				]
			]
		], [
			'type' => 'function',
			'label' => 'Unset User Preference',
			'category' => 'memory',
			'tags' => ['preferences', 'memory', 'user', 'session'],
			'priority' => 60,
			'function' => [
				'name' => 'unset_user_pref',
				'description' => 'Removes a user/session preference value by key.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'key' => [
							'type' => 'string',
							'description' => 'Preference key (must be allowed).'
						],
						'scope' => [
							'type' => 'string',
							'description' => 'Optional scope: "user" or "session". If omitted, removes both for the current identity.'
						]
					],
					'required' => ['key']
				]
			]
		], [
			'type' => 'function',
			'label' => 'List Current Preferences',
			'category' => 'memory',
			'tags' => ['preferences', 'memory', 'user', 'session'],
			'priority' => 55,
			'function' => [
				'name' => 'list_user_prefs',
				'description' => 'Lists currently stored preference values for the current user/session.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'scope' => [
							'type' => 'string',
							'description' => 'Optional scope filter: "user" or "session". If omitted, returns both.'
						]
					]
				]
			]
		]];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		return match ($toolName) {
			'list_allowed_prefs' => $this->toolListAllowedPrefs($arguments),
			'set_user_pref' => $this->toolSetUserPref($arguments),
			'unset_user_pref' => $this->toolUnsetUserPref($arguments),
			'list_user_prefs' => $this->toolListUserPrefs($arguments),
			default => throw new \InvalidArgumentException("Unsupported tool: $toolName")
		};
	}

	// ----------------------------------------------------
	// Tools
	// ----------------------------------------------------

	private function toolListAllowedPrefs(array $arguments): array {
		$enabledOnly = (bool)($arguments['enabled_only'] ?? true);

		$defs = $this->loadPrefDefinitions($enabledOnly);

		$out = [];
		foreach ($defs as $d) {
			$out[] = [
				'key' => $d['pref_key'],
				'description' => $d['description'],
				'value_type' => $d['value_type'],
				'allowed_values' => $d['allowed_values'],
				'default_scope' => $d['default_scope'],
				'enabled' => (bool)$d['enabled']
			];
		}

		$this->log('tool list_allowed_prefs => ' . count($out) . ' defs');
		return [
			'count' => count($out),
			'allowed' => $out
		];
	}

	private function toolSetUserPref(array $arguments): array {
		$key = trim((string)($arguments['key'] ?? ''));
		$value = $arguments['value'] ?? null;

		if ($key === '') {
			return ['error' => 'Missing parameter: key'];
		}

		$def = $this->loadPrefDefinition($key);
		if (!$def) {
			return ['error' => 'Preference key not allowed: ' . $key];
		}
		if (!(bool)$def['enabled']) {
			return ['error' => 'Preference key is disabled: ' . $key];
		}

		$validated = $this->validateValue($def, $value);
		if (($validated['ok'] ?? false) !== true) {
			return ['error' => $validated['error'] ?? 'Invalid value'];
		}

		$requestedScope = trim((string)($arguments['scope'] ?? ''));
		$scope = $this->resolveFinalScope($requestedScope, (string)($def['default_scope'] ?? 'user'));

		$ids = $this->resolveScopeIds($scope);

		$this->upsertPrefValue(
			$scope,
			$key,
			(string)$validated['value'],
			$ids['ident'],
			$ids['userid'],
			$ids['session']
		);

		// Patch 1: If we just saved a user-pref, remove any session-pref for the same key (so user wins cleanly).
		if ($scope === 'user') {
			$this->cleanupSessionPref($key);
		}

		$this->log("tool set_user_pref key=$key scope=$scope ident={$ids['ident']}");

		return [
			'ok' => true,
			'key' => $key,
			'scope' => $scope,
			'value' => $validated['value']
		];
	}

	private function toolUnsetUserPref(array $arguments): array {
		$key = trim((string)($arguments['key'] ?? ''));
		if ($key === '') {
			return ['error' => 'Missing parameter: key'];
		}

		$def = $this->loadPrefDefinition($key);
		if (!$def) {
			return ['error' => 'Preference key not allowed: ' . $key];
		}

		$scopeRaw = trim((string)($arguments['scope'] ?? ''));
		$scopeReq = strtolower($scopeRaw);

		// If scope is explicitly given, delete only that scope (with normal fallback rules).
		if ($scopeReq === 'user' || $scopeReq === 'session') {

			$scope = $this->resolveFinalScope($scopeReq, (string)($def['default_scope'] ?? 'user'));
			$ids = $this->resolveScopeIds($scope);

			$deleted = $this->deletePrefValue($scope, $key, $ids['ident']);

			$this->log("tool unset_user_pref key=$key scope=$scope ident={$ids['ident']} deleted=" . ($deleted ? 'yes' : 'no'));

			return [
				'ok' => true,
				'key' => $key,
				'scope' => $scope,
				'deleted' => $deleted
			];
		}

		// Patch 2: Otherwise delete both scopes for current identity (best UX).
		$deletedUser = $this->deleteUserPrefForCurrentUser($key);
		$deletedSession = $this->deleteSessionPrefForCurrentSession($key);
		$deleted = ($deletedUser || $deletedSession);

		$this->log("tool unset_user_pref key=$key scope=both deletedUser=" . ($deletedUser ? 'yes' : 'no') . " deletedSession=" . ($deletedSession ? 'yes' : 'no'));

		return [
			'ok' => true,
			'key' => $key,
			'scope' => 'both',
			'deleted' => $deleted,
			'deleted_user' => $deletedUser,
			'deleted_session' => $deletedSession
		];
	}

	private function toolListUserPrefs(array $arguments): array {
		$scopeFilter = trim((string)($arguments['scope'] ?? ''));

		$rows = $this->loadCurrentPrefValues($scopeFilter);

		$out = [];
		foreach ($rows as $r) {
			$out[] = [
				'key' => $r['pref_key'],
				'value' => $r['pref_value'],
				'scope' => $r['scope'],
				'updated' => $r['updated']
			];
		}

		$this->log('tool list_user_prefs scope=' . ($scopeFilter ?: 'both') . ' => ' . count($out));

		return [
			'count' => count($out),
			'prefs' => $out
		];
	}

	// ----------------------------------------------------
	// System text generation
	// ----------------------------------------------------

	private function buildSystemLines(): array {
		$defs = $this->loadPrefDefinitions(true);
		if (!$defs) {
			return [];
		}

		$values = $this->loadMergedValuesForCurrentIdentity();

		$lines = [];
		foreach ($defs as $def) {
			$key = (string)$def['pref_key'];

			if (!array_key_exists($key, $values)) {
				continue;
			}

			$line = $this->renderSystemTemplate($def, $values[$key]);
			if ($line !== null && trim($line) !== '') {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	private function renderSystemTemplate(array $def, mixed $value): ?string {
		$type = (string)($def['value_type'] ?? 'string');

		if ($type === 'bool') {
			if ($value === '0' || $value === 0 || $value === false || $value === 'false') {
				return null;
			}
			return (string)($def['system_template'] ?? '');
		}

		$template = (string)($def['system_template'] ?? '');
		if ($template === '') {
			return null;
		}

		return str_replace('{{value}}', (string)$value, $template);
	}

	// ----------------------------------------------------
	// Validation + Scope + Identity
	// ----------------------------------------------------

	private function validateValue(array $def, mixed $value): array {
		$type = (string)($def['value_type'] ?? 'string');

		if ($type === 'bool') {
			$bool = $this->toBool($value);
			return ['ok' => true, 'value' => $bool ? '1' : '0'];
		}

		$val = is_scalar($value) ? (string)$value : json_encode($value);
		$val = trim((string)$val);

		if ($val === '') {
			return ['ok' => false, 'error' => 'Value must not be empty'];
		}

		$allowed = $this->decodeJsonArray($def['allowed_values'] ?? null);
		if ($allowed !== null && $allowed !== []) {
			if (!in_array($val, $allowed, true)) {
				return ['ok' => false, 'error' => 'Value not allowed for this key'];
			}
		}

		return ['ok' => true, 'value' => $val];
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) return $value;
		if (is_int($value)) return $value !== 0;

		$s = strtolower(trim((string)$value));
		return in_array($s, ['1', 'true', 'yes', 'on'], true);
	}

	private function resolveFinalScope(string $requestedScope, string $defaultScope): string {
		$requestedScope = strtolower(trim($requestedScope));
		$defaultScope = strtolower(trim($defaultScope));

		$requestedScope = ($requestedScope === 'user' || $requestedScope === 'session') ? $requestedScope : '';
		$defaultScope = ($defaultScope === 'user' || $defaultScope === 'session') ? $defaultScope : 'user';

		$userid = $this->accesscontrol->getUserId();
		$hasUser = ($userid !== null && (string)$userid !== '');

		if ($requestedScope === 'user' && !$hasUser) {
			return 'session';
		}

		if ($requestedScope !== '') {
			return $requestedScope;
		}

		if ($defaultScope === 'user' && !$hasUser) {
			return 'session';
		}

		return $defaultScope;
	}

	private function resolveScopeIds(string $scope): array {
		$this->session->start();
		$sessionKey = (string)$this->session->getId();

		$userid = $this->accesscontrol->getUserId();
		$useridStr = $userid !== null ? (string)$userid : null;

		if ($scope === 'user') {
			$ident = $this->buildIdent('user', $useridStr, $sessionKey);
			return [
				'userid' => $useridStr,
				'session' => $sessionKey,
				'ident' => $ident
			];
		}

		$ident = $this->buildIdent('session', $useridStr, $sessionKey);
		return [
			'userid' => null,
			'session' => $sessionKey,
			'ident' => $ident
		];
	}

	private function buildIdent(string $scope, ?string $userid, string $sessionKey): string {
		if ($scope === 'user') {
			if ($userid === null || $userid === '') {
				return 's:' . $sessionKey;
			}
			return 'u:' . $userid;
		}
		return 's:' . $sessionKey;
	}

	private function deleteUserPrefForCurrentUser(string $key): bool {
		$this->session->start();
		$sessionKey = (string)$this->session->getId();

		$userid = $this->accesscontrol->getUserId();
		$useridStr = $userid !== null ? (string)$userid : null;

		if ($useridStr === null || $useridStr === '') {
			return false;
		}

		$ident = $this->buildIdent('user', $useridStr, $sessionKey);
		return $this->deletePrefValue('user', $key, $ident);
	}

	private function deleteSessionPrefForCurrentSession(string $key): bool {
		$this->session->start();
		$sessionKey = (string)$this->session->getId();

		if ($sessionKey === '') {
			return false;
		}

		$ident = $this->buildIdent('session', null, $sessionKey);
		return $this->deletePrefValue('session', $key, $ident);
	}

	// ----------------------------------------------------
	// Database I/O
	// ----------------------------------------------------

	private function ensureTables(): void {
		$this->database->connect();

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_missionbay_userpref_def (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				pref_key VARCHAR(100) NOT NULL,
				description VARCHAR(255) NULL,
				system_template TEXT NOT NULL,
				value_type VARCHAR(20) NOT NULL DEFAULT 'string',
				allowed_values LONGTEXT NULL,
				default_scope VARCHAR(20) NOT NULL DEFAULT 'user',
				sort_order INT NOT NULL DEFAULT 100,
				enabled TINYINT(1) NOT NULL DEFAULT 1,
				created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				UNIQUE KEY uq_pref_key (pref_key),
				KEY idx_enabled_sort (enabled, sort_order)
			)
		");

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_missionbay_userpref_value (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				scope VARCHAR(20) NOT NULL,
				ident VARCHAR(128) NOT NULL,
				userid VARCHAR(50) NULL,
				session VARCHAR(64) NULL,
				pref_key VARCHAR(100) NOT NULL,
				pref_value TEXT NOT NULL,
				updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				UNIQUE KEY uq_scope_key_ident (scope, pref_key, ident),
				KEY idx_ident (ident),
				KEY idx_key (pref_key),
				KEY idx_user (userid),
				KEY idx_session (session)
			)
		");
	}

	private function loadPrefDefinitions(bool $enabledOnly): array {
		$this->database->connect();

		$where = $enabledOnly ? 'WHERE enabled=1' : '';
		$q = "SELECT pref_key, description, system_template, value_type, allowed_values, default_scope, sort_order, enabled
			  FROM base3_missionbay_userpref_def
			  $where
			  ORDER BY sort_order ASC, pref_key ASC";

		return $this->database->multiQuery($q) ?? [];
	}

	private function loadPrefDefinition(string $key): ?array {
		$this->database->connect();

		$q = "SELECT pref_key, description, system_template, value_type, allowed_values, default_scope, sort_order, enabled
			  FROM base3_missionbay_userpref_def
			  WHERE pref_key='" . $this->database->escape($key) . "'
			  LIMIT 1";

		$row = $this->database->singleQuery($q);
		return $row ?: null;
	}

	private function upsertPrefValue(string $scope, string $key, string $value, string $ident, ?string $userid, ?string $sessionKey): void {
		$this->database->connect();

		$useridSql = $userid !== null ? "'" . $this->database->escape($userid) . "'" : 'NULL';
		$sessionSql = $sessionKey !== null ? "'" . $this->database->escape($sessionKey) . "'" : 'NULL';

		$q = "INSERT INTO base3_missionbay_userpref_value (scope, ident, userid, session, pref_key, pref_value)
			  VALUES (
				'" . $this->database->escape($scope) . "',
				'" . $this->database->escape($ident) . "',
				$useridSql,
				$sessionSql,
				'" . $this->database->escape($key) . "',
				'" . $this->database->escape($value) . "'
			  )
			  ON DUPLICATE KEY UPDATE
				pref_value=VALUES(pref_value),
				userid=VALUES(userid),
				session=VALUES(session),
				updated=CURRENT_TIMESTAMP";

		$this->database->nonQuery($q);
	}

	private function deletePrefValue(string $scope, string $key, string $ident): bool {
		$this->database->connect();

		$q = "DELETE FROM base3_missionbay_userpref_value
			  WHERE scope='" . $this->database->escape($scope) . "'
				AND pref_key='" . $this->database->escape($key) . "'
				AND ident='" . $this->database->escape($ident) . "'";

		$this->database->nonQuery($q);
		return $this->database->affectedRows() > 0;
	}

	private function cleanupSessionPref(string $key): void {
		$this->session->start();
		$sessionKey = (string)$this->session->getId();
		if ($sessionKey === '') {
			return;
		}

		$ident = $this->buildIdent('session', null, $sessionKey);

		$q = "DELETE FROM base3_missionbay_userpref_value
			  WHERE scope='session'
				AND pref_key='" . $this->database->escape($key) . "'
				AND ident='" . $this->database->escape($ident) . "'";

		$this->database->connect();
		$this->database->nonQuery($q);

		$deleted = $this->database->affectedRows();
		if ($deleted > 0) {
			$this->log("cleanup session pref key=$key deleted=$deleted");
		}
	}

	private function loadCurrentPrefValues(string $scopeFilter): array {
		$this->database->connect();

		$this->session->start();
		$sessionKey = (string)$this->session->getId();

		$userid = $this->accesscontrol->getUserId();
		$useridStr = $userid !== null ? (string)$userid : null;

		$idents = [];
		if ($sessionKey !== '') {
			$idents[] = "'" . $this->database->escape($this->buildIdent('session', null, $sessionKey)) . "'";
		}
		if ($useridStr !== null && $useridStr !== '') {
			$idents[] = "'" . $this->database->escape($this->buildIdent('user', $useridStr, $sessionKey)) . "'";
		}

		if (!$idents) {
			return [];
		}

		$conds = [];
		$scopeFilter = strtolower(trim($scopeFilter));
		if ($scopeFilter === 'user' || $scopeFilter === 'session') {
			$conds[] = "scope='" . $this->database->escape($scopeFilter) . "'";
		}

		$conds[] = 'ident IN (' . implode(',', $idents) . ')';

		$q = "SELECT scope, pref_key, pref_value, updated
			  FROM base3_missionbay_userpref_value
			  WHERE " . implode(' AND ', $conds) . "
			  ORDER BY scope ASC, pref_key ASC";

		return $this->database->multiQuery($q) ?? [];
	}

	private function loadMergedValuesForCurrentIdentity(): array {
		$rows = $this->loadCurrentPrefValues('');
		if (!$rows) {
			return [];
		}

		$values = [];

		// Session first
		foreach ($rows as $r) {
			if (($r['scope'] ?? '') === 'session') {
				$values[(string)$r['pref_key']] = (string)$r['pref_value'];
			}
		}

		// User overrides session
		foreach ($rows as $r) {
			if (($r['scope'] ?? '') === 'user') {
				$values[(string)$r['pref_key']] = (string)$r['pref_value'];
			}
		}

		return $values;
	}

	private function decodeJsonArray(mixed $value): ?array {
		if ($value === null || $value === '') {
			return null;
		}
		if (is_array($value)) {
			return $value;
		}
		$decoded = json_decode((string)$value, true);
		return is_array($decoded) ? $decoded : null;
	}

	// ----------------------------------------------------
	// Logging
	// ----------------------------------------------------

	private function log(string $msg): void {
		if ($this->logger) {
			$this->logger->log(static::getName(), '[' . $this->id . '] ' . $msg);
		}
	}
}

/*
# Example prefs

INSERT INTO base3_missionbay_userpref_def (pref_key, description, system_template, value_type, allowed_values, default_scope, sort_order, enabled)
VALUES
	( "address_form", "Anredeform des Nutzers (Du/Sie).", "Sprich den Nutzer konsequent mit „{{value}}“ an.", "enum", "[\"Du\",\"Sie\"]", "user", 10, 1),
	( "answer_style", "Antwortstil (kurz, normal, ausführlich).", "Antworte im Stil: {{value}}.", "enum", "[\"kurz\",\"normal\",\"ausführlich\"]", "user", 20, 1),
	( "technical_depth", "Technischer Detailgrad (einfach, technisch, expert).", "Passe den technischen Detailgrad an: {{value}}.", "enum", "[\"einfach\",\"technisch\",\"expert\"]", "user", 30, 1),
	( "code_indent", "Einrückung in Codeblöcken (tabs/spaces).", "Bei Code: verwende für Einrückungen {{value}}.", "enum", "[\"tabs\",\"spaces\"]", "user", 40, 1),
	( "code_language", "Kommentare in Code (de/en).", "Bei Code: schreibe Kommentare auf {{value}}.", "enum", "[\"Deutsch\",\"Englisch\"]", "user", 50, 1),
	( "format_preference", "Bevorzugtes Antwortformat (Bulletpoints/Fließtext).", "Bevorzuge als Ausgabeformat: {{value}}.", "enum", "[\"Bulletpoints\",\"Fließtext\"]", "user", 60, 1),
	( "no_long_dash", "Vermeide lange Gedankenstriche in Markdown.", "Vermeide in Markdown lange Gedankenstriche.", "bool", NULL, "user", 70, 1),
	( "ask_clarifying_questions", "Rückfragen stellen, wenn etwas unklar ist.", "Stelle Rückfragen, wenn wichtige Informationen fehlen.", "bool", NULL, "user", 80, 1);
*/

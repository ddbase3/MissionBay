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

namespace MissionBay\Resource;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use Base3\Session\Api\ISession;
use Base3\Usermanager\Api\IUsermanager;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use MissionBay\Api\IAgentTool;

class FocusAgentResource extends AbstractAgentResource implements IAgentMemory, IAgentTool {

	private ?ILogger $logger = null;

	private bool $enabled = true;
	private int $priority = 15;
	private int $toolPriority = 60;
	private int $strength = 30;
	private int $maxFocusLength = 200;
	private string $systemTitle = 'Conversation focus';
	private string $identityScope = 'user';

	public function __construct(
		private readonly IDatabase $database,
		private readonly IAgentConfigValueResolver $resolver,
		private readonly IAccesscontrol $accesscontrol,
		private readonly ISession $session,
		private readonly IUsermanager $usermanager,
		?string $id = null
	) {
		parent::__construct($id);
		$this->ensureTables();
	}

	public static function getName(): string {
		return 'focusagentresource';
	}

	public function getDescription(): string {
		return 'Stores a short conversation focus via tool calls and injects focus rules as system prompt addendum.';
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger for focus events.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->enabled = $this->toBool($this->resolver->resolveValue($config['enabled'] ?? true) ?? true);
		$this->priority = $this->clampInt((int)($this->resolver->resolveValue($config['priority'] ?? null) ?? 15), 0, 1000);
		$this->toolPriority = $this->clampInt((int)($this->resolver->resolveValue($config['toolpriority'] ?? null) ?? 60), 0, 1000);
		$this->strength = $this->clampInt((int)($this->resolver->resolveValue($config['strength'] ?? null) ?? 30), 0, 100);
		$this->maxFocusLength = $this->clampInt((int)($this->resolver->resolveValue($config['maxfocuslength'] ?? null) ?? 200), 20, 1000);
		$this->systemTitle = (string)($this->resolver->resolveValue($config['systemtitle'] ?? null) ?? 'Conversation focus');

		$scope = strtolower(trim((string)($this->resolver->resolveValue($config['identityscope'] ?? ($config['scope'] ?? null)) ?? 'user')));
		$this->identityScope = in_array($scope, ['user', 'session'], true) ? $scope : 'user';
	}

	public function init(array $resources, IAgentContext $context): void {
		if (!empty($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
			$this->log('logger docked into FocusAgentResource');
		}
	}

	// ----------------------------------------------------
	// IAgentMemory
	// ----------------------------------------------------

	public function loadNodeHistory(string $nodeId): array {
		if (!$this->enabled) {
			return [];
		}

		$lines = $this->buildSystemLines();
		$content = $this->systemTitle . ":\n- " . implode("\n- ", $lines);

		return [[
			'role' => 'system',
			'content' => $content
		]];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		// no-op (focus is controlled by explicit tool calls)
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		// no-op
		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		$this->deleteFocusState();
	}

	public function getPriority(): int {
		return $this->priority;
	}

	// ----------------------------------------------------
	// IAgentTool
	// ----------------------------------------------------

	public function getToolDefinitions(): array {
		if (!$this->enabled) {
			return [];
		}

		return [[
			'type' => 'function',
			'label' => 'Set Conversation Focus',
			'category' => 'memory',
			'tags' => ['focus', 'memory', 'conversation', 'task'],
			'priority' => $this->toolPriority,
			'function' => [
				'name' => 'set_focus',
				'description' => 'Stores the current main working focus of the conversation. Use only for clear main topics, tasks, tickets, or working goals. Do not use for short side questions or small digressions.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'focus' => [
							'type' => 'string',
							'maxLength' => $this->maxFocusLength,
							'description' => 'Short focus text. Keep it concise and task-oriented.'
						]
					],
					'required' => ['focus']
				]
			]
		], [
			'type' => 'function',
			'label' => 'Clear Conversation Focus',
			'category' => 'memory',
			'tags' => ['focus', 'memory', 'conversation', 'task'],
			'priority' => $this->toolPriority,
			'function' => [
				'name' => 'clear_focus',
				'description' => 'Clears the current conversation focus when the focused task is completed or the user explicitly ends it.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'reason' => [
							'type' => 'string',
							'description' => 'Optional short reason why the focus is cleared.'
						]
					]
				]
			]
		]];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if (!$this->enabled) {
			return ['error' => 'Focus resource is disabled'];
		}

		return match ($toolName) {
			'set_focus' => $this->toolSetFocus($arguments),
			'clear_focus' => $this->toolClearFocus($arguments),
			default => throw new \InvalidArgumentException("Unsupported tool: $toolName")
		};
	}

	// ----------------------------------------------------
	// Tools
	// ----------------------------------------------------

	private function toolSetFocus(array $arguments): array {
		$rawFocus = $arguments['focus'] ?? '';

		if (!is_scalar($rawFocus)) {
			return ['error' => 'Focus must be a scalar string'];
		}

		$cleanFocus = $this->cleanFocus((string)$rawFocus);
		if ($cleanFocus === '') {
			return ['error' => 'Missing parameter: focus'];
		}

		$truncated = $this->textLength($cleanFocus) > $this->maxFocusLength;
		$focus = $this->cutText($cleanFocus, $this->maxFocusLength);

		$ids = $this->resolveScopeIds();

		$this->upsertFocusState(
			$focus,
			$ids['scope'],
			$ids['ident'],
			$ids['userid'],
			$ids['session']
		);

		$this->log("tool set_focus scope={$ids['scope']} ident={$ids['ident']} userid=" . ($ids['userid'] ?? 'NULL') . ' focus=' . $this->shortLogValue($focus));

		return [
			'ok' => true,
			'focus' => $focus,
			'truncated' => $truncated,
			'strength' => $this->strength,
			'maxfocuslength' => $this->maxFocusLength,
			'scope' => $ids['scope'],
			'ident' => $ids['ident'],
			'userid' => $ids['userid']
		];
	}

	private function toolClearFocus(array $arguments): array {
		$reason = '';
		if (isset($arguments['reason']) && is_scalar($arguments['reason'])) {
			$reason = $this->cutText($this->cleanFocus((string)$arguments['reason']), 255);
		}

		$deleted = $this->deleteFocusState();

		$this->log('tool clear_focus deleted=' . ($deleted ? 'yes' : 'no') . ($reason !== '' ? ' reason=' . $this->shortLogValue($reason) : ''));

		return [
			'ok' => true,
			'deleted' => $deleted
		];
	}

	// ----------------------------------------------------
	// System text generation
	// ----------------------------------------------------

	private function buildSystemLines(): array {
		$focus = $this->loadCurrentFocus();

		$lines = [];

		if ($focus !== null && trim((string)$focus['focus_text']) !== '') {
			$lines[] = 'Current focus: ' . trim((string)$focus['focus_text']);
		} else {
			$lines[] = 'Current focus: none.';
		}

		$lines[] = 'Use set_focus(focus) when the user starts a clear main topic, task, ticket, or working goal.';
		$lines[] = 'Do not change the focus for short side questions, clarifications, examples, or small digressions. Answer them briefly when possible.';
		$lines[] = 'Use clear_focus(reason) when the focused task is completed or the user explicitly ends it.';
		$lines[] = 'Maximum focus length is ' . $this->maxFocusLength . ' characters. Longer focus texts are shortened automatically.';
		$lines[] = 'Focus strength is ' . $this->strength . '/100. ' . $this->buildStrengthGuidance();

		return $lines;
	}

	private function buildStrengthGuidance(): string {
		if ($this->strength <= 0) {
			return 'Do not actively redirect based on focus.';
		}

		if ($this->strength <= 20) {
			return 'Use the focus only as weak orientation.';
		}

		if ($this->strength <= 40) {
			return 'Use the focus as a gentle anchor. Answer simple side questions briefly when possible, then return to the focus naturally.';
		}

		if ($this->strength <= 70) {
			return 'Keep the conversation aligned with the focus. Answer side questions briefly and return to the focus.';
		}

		return 'Keep the conversation strongly aligned with the focus. Avoid topic changes unless the user clearly requests them.';
	}

	// ----------------------------------------------------
	// Validation + Identity
	// ----------------------------------------------------

	private function cleanFocus(string $value): string {
		$value = strip_tags($value);
		$value = trim($value);

		$normalized = preg_replace('/\s+/u', ' ', $value);
		if (is_string($normalized)) {
			$value = $normalized;
		}

		return trim($value);
	}

	private function cutText(string $value, int $maxLength): string {
		if ($this->textLength($value) <= $maxLength) {
			return $value;
		}

		if (function_exists('mb_substr')) {
			return trim((string)mb_substr($value, 0, $maxLength));
		}

		return trim(substr($value, 0, $maxLength));
	}

	private function textLength(string $value): int {
		if (function_exists('mb_strlen')) {
			return (int)mb_strlen($value);
		}

		return strlen($value);
	}

	private function clampInt(int $value, int $min, int $max): int {
		return max($min, min($max, $value));
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) return $value;
		if (is_int($value)) return $value !== 0;

		$s = strtolower(trim((string)$value));
		return in_array($s, ['1', 'true', 'yes', 'on'], true);
	}

	private function resolveScopeIds(): array {
		$useridStr = $this->resolveUserId();
		$sessionKey = $this->resolveSessionKey();

		$scope = $this->identityScope;

		if ($scope === 'user' && ($useridStr === null || $useridStr === '')) {
			$scope = 'session';
		}

		if ($scope === 'session' && $sessionKey === '' && $useridStr !== null && $useridStr !== '') {
			$scope = 'user';
		}

		$ident = $this->buildIdent($scope, $useridStr, $sessionKey);

		return [
			'scope' => $scope,
			'userid' => $useridStr,
			'session' => $sessionKey !== '' ? $sessionKey : null,
			'ident' => $ident
		];
	}

	private function resolveUserId(): ?string {
		try {
			$this->accesscontrol->authenticate();
		} catch (\Throwable $e) {
			$this->log('accesscontrol authenticate failed: ' . $e->getMessage());
		}

		try {
			$userid = $this->accesscontrol->getUserId();
			$useridStr = $userid !== null ? trim((string)$userid) : '';

			if ($useridStr !== '') {
				return $useridStr;
			}
		} catch (\Throwable $e) {
			$this->log('accesscontrol getUserId failed: ' . $e->getMessage());
		}

		try {
			$user = $this->usermanager->getUser();
			return $this->extractUserId($user);
		} catch (\Throwable $e) {
			$this->log('usermanager getUser failed: ' . $e->getMessage());
		}

		return null;
	}

	private function resolveSessionKey(): string {
		try {
			$this->session->start();
		} catch (\Throwable $e) {
			$this->log('session start failed: ' . $e->getMessage());
		}

		return trim((string)$this->session->getId());
	}

	private function extractUserId(mixed $user): ?string {
		if ($user === null) {
			return null;
		}

		if (is_scalar($user)) {
			$value = trim((string)$user);
			return $value !== '' ? $value : null;
		}

		if (is_array($user)) {
			return $this->extractUserIdFromArray($user);
		}

		if (is_object($user)) {
			return $this->extractUserIdFromObject($user);
		}

		return null;
	}

	private function extractUserIdFromArray(array $user): ?string {
		foreach (['id', 'user_id', 'userid', 'usr_id', 'login'] as $key) {
			if (!array_key_exists($key, $user)) {
				continue;
			}

			if (!is_scalar($user[$key])) {
				continue;
			}

			$value = trim((string)$user[$key]);
			if ($value !== '') {
				return $value;
			}
		}

		return null;
	}

	private function extractUserIdFromObject(object $user): ?string {
		foreach (['getId', 'getUserId', 'getUsrId', 'getLogin'] as $method) {
			if (!method_exists($user, $method)) {
				continue;
			}

			$value = $user->$method();
			if (!is_scalar($value)) {
				continue;
			}

			$value = trim((string)$value);
			if ($value !== '') {
				return $value;
			}
		}

		foreach (['id', 'user_id', 'userid', 'usr_id', 'login'] as $property) {
			if (!property_exists($user, $property)) {
				continue;
			}

			$value = $user->{$property};
			if (!is_scalar($value)) {
				continue;
			}

			$value = trim((string)$value);
			if ($value !== '') {
				return $value;
			}
		}

		return null;
	}

	private function buildIdent(string $scope, ?string $userid, string $sessionKey): string {
		if ($scope === 'user' && $userid !== null && $userid !== '') {
			return 'u:' . $userid;
		}

		if ($sessionKey !== '') {
			return 's:' . $sessionKey;
		}

		if ($userid !== null && $userid !== '') {
			return 'u:' . $userid;
		}

		return 'anon:' . sha1($this->id);
	}

	// ----------------------------------------------------
	// Database I/O
	// ----------------------------------------------------

	private function ensureTables(): void {
		$this->database->connect();

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_missionbay_focus_state (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				scope VARCHAR(20) NOT NULL,
				ident VARCHAR(128) NOT NULL,
				userid VARCHAR(50) NULL,
				session VARCHAR(64) NULL,
				resource_id VARCHAR(100) NOT NULL,
				focus_text TEXT NOT NULL,
				updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				UNIQUE KEY uq_focus_scope_ident_resource (scope, ident, resource_id),
				KEY idx_ident (ident),
				KEY idx_user (userid),
				KEY idx_session (session),
				KEY idx_resource (resource_id)
			)
		");
	}

	private function loadCurrentFocus(): ?array {
		$this->database->connect();

		$ids = $this->resolveScopeIds();

		$q = "SELECT focus_text, updated, created
			FROM base3_missionbay_focus_state
			WHERE scope='" . $this->database->escape($ids['scope']) . "'
				AND ident='" . $this->database->escape($ids['ident']) . "'
				AND resource_id='" . $this->database->escape($this->id) . "'
			LIMIT 1";

		$row = $this->database->singleQuery($q);
		return $row ?: null;
	}

	private function upsertFocusState(string $focus, string $scope, string $ident, ?string $userid, ?string $sessionKey): void {
		$this->database->connect();

		$useridSql = $userid !== null ? "'" . $this->database->escape($userid) . "'" : 'NULL';
		$sessionSql = $sessionKey !== null ? "'" . $this->database->escape($sessionKey) . "'" : 'NULL';

		$q = "INSERT INTO base3_missionbay_focus_state (scope, ident, userid, session, resource_id, focus_text)
			VALUES (
				'" . $this->database->escape($scope) . "',
				'" . $this->database->escape($ident) . "',
				$useridSql,
				$sessionSql,
				'" . $this->database->escape($this->id) . "',
				'" . $this->database->escape($focus) . "'
			)
			ON DUPLICATE KEY UPDATE
				focus_text=VALUES(focus_text),
				userid=VALUES(userid),
				session=VALUES(session),
				updated=CURRENT_TIMESTAMP";

		$this->database->nonQuery($q);
	}

	private function deleteFocusState(): bool {
		$this->database->connect();

		$ids = $this->resolveScopeIds();

		$q = "DELETE FROM base3_missionbay_focus_state
			WHERE scope='" . $this->database->escape($ids['scope']) . "'
				AND ident='" . $this->database->escape($ids['ident']) . "'
				AND resource_id='" . $this->database->escape($this->id) . "'";

		$this->database->nonQuery($q);
		return $this->database->affectedRows() > 0;
	}

	// ----------------------------------------------------
	// Logging
	// ----------------------------------------------------

	private function shortLogValue(string $value): string {
		return $this->cutText($value, 120);
	}

	private function log(string $msg): void {
		if ($this->logger) {
			$this->logger->log(static::getName(), '[' . $this->id . '] ' . $msg);
		}
	}
}

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

namespace MissionBay\Listener;

use Base3\Database\Api\IDatabase;
use Base3\Usermanager\Api\IUsermanager;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;

class MissionBayToolEventDisplayListener {

	private const TABLE = 'base3_missionbay_tooluse';

	private IDatabase $database;
	private IUsermanager $usermanager;
	private bool $schemaEnsured = false;

	public function __construct(IDatabase $database, IUsermanager $usermanager) {
		$this->database = $database;
		$this->usermanager = $usermanager;
	}

	public function onToolStarted(MissionBayToolStartedEvent $event): void {
		$this->storeToolEvent($event, 'started');
	}

	public function onToolFinished(MissionBayToolFinishedEvent $event): void {
		$this->storeToolEvent($event, 'finished');
	}

	public function onToolFailed(MissionBayToolFailedEvent $event): void {
		$this->storeToolEvent($event, 'failed');
	}

	public function onAgentActionAudit(MissionBayAgentActionAuditEvent $event): void {
		try {
			$this->database->connect();
			$this->ensureSchema();

			$action = $event->getAction();
			$trace = $event->getTrace();
			$nodeId = $this->traceString($trace, 'node_id', 'agent_action');
			$callId = trim($action->getId());

			if ($callId === '') {
				return;
			}

			$current = $this->getRecord($nodeId, $callId);
			$currentMeta = $this->decodeJsonArray($current['meta_json'] ?? null);
			$status = $this->resolveActionStatus($event, $current['status'] ?? '');
			$time = $this->normalizeTimestamp($event->getTimestamp());
			$record = $this->buildActionRecord(
				$event,
				$nodeId,
				$callId,
				$status,
				$time,
				$currentMeta,
				$current ?? []
			);

			$this->writeRecord($record, $current !== null);
		} catch (\Throwable $e) {
		}
	}

	private function storeToolEvent(object $event, string $phase): void {
		try {
			$this->database->connect();
			$this->ensureSchema();

			$nodeId = $event->getNodeId();
			$callId = $event->getCallId();
			$current = $this->getRecord($nodeId, $callId);
			$currentMeta = $this->decodeJsonArray($current['meta_json'] ?? null);
			$status = $this->resolveToolStatus($phase, $current['status'] ?? '', $currentMeta);
			$time = $this->normalizeTimestamp($event->getTimestamp());
			$record = $this->buildToolRecord($event, $status, $time, $currentMeta);

			$this->writeRecord($record, $current !== null);
		} catch (\Throwable $e) {
		}
	}

	private function ensureSchema(): void {
		if ($this->schemaEnsured) {
			return;
		}

		$sql = '
			CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`turn_id` VARCHAR(191) NOT NULL DEFAULT \'unknown_turn\',
				`node_id` VARCHAR(191) NOT NULL,
				`call_id` VARCHAR(191) NOT NULL,
				`call_index` INT NOT NULL DEFAULT 0,
				`chatbot_key` VARCHAR(191) NOT NULL DEFAULT \'unknown_chatbot\',
				`config_group` VARCHAR(191) NOT NULL DEFAULT \'unknown_group\',
				`config_name` VARCHAR(191) NOT NULL DEFAULT \'unknown_config\',
				`user_id` INT NOT NULL DEFAULT 0,
				`user_login` VARCHAR(191) NOT NULL DEFAULT \'unknown_user\',
				`prompt_text` LONGTEXT NULL,
				`meta_json` LONGTEXT NULL,
				`tool_name` VARCHAR(191) NOT NULL,
				`label` VARCHAR(191) NOT NULL,
				`iteration` INT NOT NULL DEFAULT 0,
				`status` VARCHAR(32) NOT NULL,
				`arguments_json` LONGTEXT NULL,
				`result_json` LONGTEXT NULL,
				`error_message` LONGTEXT NULL,
				`error_type` VARCHAR(255) NULL,
				`error_code` VARCHAR(64) NULL,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				`finished_at` DATETIME NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_node_call` (`node_id`, `call_id`),
				KEY `idx_turn_id` (`turn_id`),
				KEY `idx_tool_name` (`tool_name`),
				KEY `idx_status` (`status`),
				KEY `idx_created_at` (`created_at`),
				KEY `idx_chatbot_key` (`chatbot_key`),
				KEY `idx_user_id` (`user_id`),
				KEY `idx_chatbot_user` (`chatbot_key`, `user_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		';

		$this->database->nonQuery($sql);
		$this->schemaEnsured = true;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function getRecord(string $nodeId, string $callId): ?array {
		$sql = '
			SELECT *
			FROM `' . self::TABLE . '`
			WHERE `node_id` = ' . $this->quote($nodeId) . '
				AND `call_id` = ' . $this->quote($callId) . '
			LIMIT 1
		';

		$row = $this->database->singleQuery($sql);
		return is_array($row) ? $row : null;
	}

	/**
	 * @param array<string,mixed> $record
	 */
	private function writeRecord(array $record, bool $exists): void {
		if ($exists) {
			$this->updateRecord($record);
			return;
		}

		$this->insertRecord($record);
	}

	/**
	 * @param array<string,mixed> $record
	 */
	private function insertRecord(array $record): void {
		$sql = '
			INSERT INTO `' . self::TABLE . '` (
				`turn_id`,
				`node_id`,
				`call_id`,
				`call_index`,
				`chatbot_key`,
				`config_group`,
				`config_name`,
				`user_id`,
				`user_login`,
				`prompt_text`,
				`meta_json`,
				`tool_name`,
				`label`,
				`iteration`,
				`status`,
				`arguments_json`,
				`result_json`,
				`error_message`,
				`error_type`,
				`error_code`,
				`created_at`,
				`updated_at`,
				`finished_at`
			) VALUES (
				' . $this->quote($record['turn_id']) . ',
				' . $this->quote($record['node_id']) . ',
				' . $this->quote($record['call_id']) . ',
				' . (int)$record['call_index'] . ',
				' . $this->quote($record['chatbot_key']) . ',
				' . $this->quote($record['config_group']) . ',
				' . $this->quote($record['config_name']) . ',
				' . (int)$record['user_id'] . ',
				' . $this->quote($record['user_login']) . ',
				' . $this->quoteNullable($record['prompt_text']) . ',
				' . $this->quoteNullable($record['meta_json']) . ',
				' . $this->quote($record['tool_name']) . ',
				' . $this->quote($record['label']) . ',
				' . (int)$record['iteration'] . ',
				' . $this->quote($record['status']) . ',
				' . $this->quoteNullable($record['arguments_json']) . ',
				' . $this->quoteNullable($record['result_json']) . ',
				' . $this->quoteNullable($record['error_message']) . ',
				' . $this->quoteNullable($record['error_type']) . ',
				' . $this->quoteNullable($record['error_code']) . ',
				' . $this->quote($record['created_at']) . ',
				' . $this->quote($record['updated_at']) . ',
				' . $this->quoteNullable($record['finished_at']) . '
			)
		';

		$this->database->nonQuery($sql);
	}

	/**
	 * @param array<string,mixed> $record
	 */
	private function updateRecord(array $record): void {
		$sql = '
			UPDATE `' . self::TABLE . '`
			SET
				`turn_id` = ' . $this->quote($record['turn_id']) . ',
				`call_index` = ' . (int)$record['call_index'] . ',
				`chatbot_key` = ' . $this->quote($record['chatbot_key']) . ',
				`config_group` = ' . $this->quote($record['config_group']) . ',
				`config_name` = ' . $this->quote($record['config_name']) . ',
				`user_id` = ' . (int)$record['user_id'] . ',
				`user_login` = ' . $this->quote($record['user_login']) . ',
				`prompt_text` = ' . $this->quoteNullable($record['prompt_text']) . ',
				`meta_json` = ' . $this->quoteNullable($record['meta_json']) . ',
				`tool_name` = ' . $this->quote($record['tool_name']) . ',
				`label` = ' . $this->quote($record['label']) . ',
				`iteration` = ' . (int)$record['iteration'] . ',
				`status` = ' . $this->quote($record['status']) . ',
				`arguments_json` = ' . $this->quoteNullable($record['arguments_json']) . ',
				`result_json` = ' . $this->quoteNullable($record['result_json']) . ',
				`error_message` = ' . $this->quoteNullable($record['error_message']) . ',
				`error_type` = ' . $this->quoteNullable($record['error_type']) . ',
				`error_code` = ' . $this->quoteNullable($record['error_code']) . ',
				`updated_at` = ' . $this->quote($record['updated_at']) . ',
				`finished_at` = ' . $this->quoteNullable($record['finished_at']) . '
			WHERE `node_id` = ' . $this->quote($record['node_id']) . '
				AND `call_id` = ' . $this->quote($record['call_id']) . '
		';

		$this->database->nonQuery($sql);
	}

	/**
	 * @param MissionBayToolStartedEvent|MissionBayToolFinishedEvent|MissionBayToolFailedEvent $event
	 * @param array<string,mixed> $currentMeta
	 * @return array<string,mixed>
	 */
	private function buildToolRecord(object $event, string $status, string $time, array $currentMeta): array {
		$base = $this->buildBaseRecord($event);
		$trace = $this->getTrace($event);
		$meta = $this->mergeMeta($currentMeta, [
			'source' => $this->traceString($trace, 'source', 'unknown'),
			'resource_id' => $this->traceString($trace, 'resource_id', $event->getNodeId()),
			'original_tool_name' => $this->traceString($trace, 'original_tool_name', $event->getToolName()),
			'trace' => $trace
		]);

		$result = null;
		$errorMessage = null;
		$errorType = null;
		$errorCode = null;
		$finishedAt = null;

		if ($event instanceof MissionBayToolFinishedEvent) {
			$result = $this->encodeJson($event->getResult());
			$finishedAt = $time;
		}

		if ($event instanceof MissionBayToolFailedEvent) {
			$errorMessage = $event->getErrorMessage();
			$errorType = $event->getErrorType();
			$errorCode = (string)$event->getErrorCode();
			$finishedAt = $time;
		}

		return [
			'turn_id' => $base['turn_id'],
			'node_id' => $event->getNodeId(),
			'call_id' => $event->getCallId(),
			'call_index' => $base['call_index'],
			'chatbot_key' => $base['chatbot_key'],
			'config_group' => $base['config_group'],
			'config_name' => $base['config_name'],
			'user_id' => $base['user_id'],
			'user_login' => $base['user_login'],
			'prompt_text' => null,
			'meta_json' => $this->encodeJson($meta),
			'tool_name' => $event->getToolName(),
			'label' => $event->getLabel(),
			'iteration' => $event->getIteration(),
			'status' => $status,
			'arguments_json' => $this->encodeJson($event->getArguments()),
			'result_json' => $result,
			'error_message' => $errorMessage,
			'error_type' => $errorType,
			'error_code' => $errorCode,
			'created_at' => $time,
			'updated_at' => $time,
			'finished_at' => $finishedAt
		];
	}

	/**
	 * @param array<string,mixed> $currentMeta
	 * @param array<string,mixed> $current
	 * @return array<string,mixed>
	 */
	private function buildActionRecord(
		MissionBayAgentActionAuditEvent $event,
		string $nodeId,
		string $callId,
		string $status,
		string $time,
		array $currentMeta,
		array $current
	): array {
		$action = $event->getAction();
		$trace = $event->getTrace();
		$user = $this->getCurrentUser();
		$actionMetadata = $action->getMetadata();
		$iteration = max(0, (int)($actionMetadata['iteration'] ?? 0));
		$callIndex = max(0, (int)($actionMetadata['call_index'] ?? $iteration));
		$meta = $this->buildActionMeta($event, $currentMeta);

		return [
			'turn_id' => $this->traceString($trace, 'turn_id', $this->recordString($current, 'turn_id', $callId)),
			'node_id' => $nodeId,
			'call_id' => $callId,
			'call_index' => $callIndex > 0 ? $callIndex : (int)($current['call_index'] ?? 0),
			'chatbot_key' => $this->traceString($trace, 'chatbot_key', $this->recordString($current, 'chatbot_key', 'unknown_chatbot')),
			'config_group' => $this->traceString($trace, 'config_group', $this->recordString($current, 'config_group', 'unknown_group')),
			'config_name' => $this->traceString($trace, 'config_name', $this->recordString($current, 'config_name', 'unknown_config')),
			'user_id' => $user['id'],
			'user_login' => $user['login'],
			'prompt_text' => $this->nullableRecordString($current, 'prompt_text'),
			'meta_json' => $this->encodeJson($meta),
			'tool_name' => $action->getName(),
			'label' => $this->traceString($trace, 'label', $this->recordString($current, 'label', $action->getName())),
			'iteration' => $iteration > 0 ? $iteration : (int)($current['iteration'] ?? 0),
			'status' => $status,
			'arguments_json' => $this->encodeJson($action->getInput()),
			'result_json' => $this->nullableRecordString($current, 'result_json'),
			'error_message' => $this->nullableRecordString($current, 'error_message'),
			'error_type' => $this->nullableRecordString($current, 'error_type'),
			'error_code' => $this->nullableRecordString($current, 'error_code'),
			'created_at' => $this->recordString($current, 'created_at', $time),
			'updated_at' => $time,
			'finished_at' => $event->getType() === MissionBayAgentActionAuditEvent::TYPE_APPROVAL_DENIED
				? $time
				: $this->nullableRecordString($current, 'finished_at')
		];
	}

	/**
	 * @param array<string,mixed> $currentMeta
	 * @return array<string,mixed>
	 */
	private function buildActionMeta(MissionBayAgentActionAuditEvent $event, array $currentMeta): array {
		$type = $event->getType();
		$trace = $event->getTrace();
		$entry = [
			'type' => $type,
			'reason' => $event->getReason(),
			'timestamp' => $event->getTimestamp(),
			'metadata' => $event->getMetadata()
		];
		$patch = [
			'source' => $this->traceString($trace, 'source', 'agent'),
			'trace' => $trace,
			'action_audit' => $entry
		];

		if (in_array($type, [
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_REQUESTED,
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_GRANTED,
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_DENIED
		], true)) {
			$patch['approval'] = [
				'status' => str_replace('approval_', '', $type),
				'reason' => $event->getReason(),
				'timestamp' => $event->getTimestamp(),
				'metadata' => $event->getMetadata()
			];
		}

		if (in_array($type, [
			MissionBayAgentActionAuditEvent::TYPE_COMMIT_ALLOWED,
			MissionBayAgentActionAuditEvent::TYPE_COMMIT_BLOCKED,
			MissionBayAgentActionAuditEvent::TYPE_COMMIT_SUCCEEDED,
			MissionBayAgentActionAuditEvent::TYPE_COMMIT_FAILED
		], true)) {
			$patch['commit'] = [
				'status' => str_replace('commit_', '', $type),
				'reason' => $event->getReason(),
				'timestamp' => $event->getTimestamp(),
				'metadata' => $event->getMetadata()
			];
		}

		return $this->mergeMeta($currentMeta, $patch);
	}

	/**
	 * @param array<string,mixed> $currentMeta
	 */
	private function resolveToolStatus(string $phase, string $currentStatus, array $currentMeta): string {
		if (!$this->wasApproved($currentStatus, $currentMeta)) {
			return $phase;
		}

		return match ($phase) {
			'started' => 'approved_started',
			'finished' => 'approved_finished',
			'failed' => 'approved_failed',
			default => $phase
		};
	}

	/**
	 * @param array<string,mixed> $currentMeta
	 */
	private function resolveActionStatus(MissionBayAgentActionAuditEvent $event, string $currentStatus): string {
		return match ($event->getType()) {
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_REQUESTED => 'approval_requested',
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_GRANTED => $this->approvedStatusFor($currentStatus),
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_DENIED => 'approval_denied',
			default => trim($currentStatus) !== '' ? $currentStatus : $event->getType()
		};
	}

	private function approvedStatusFor(string $currentStatus): string {
		return match ($currentStatus) {
			'started', 'approved_started' => 'approved_started',
			'finished', 'approved_finished' => 'approved_finished',
			'failed', 'error', 'approved_failed' => 'approved_failed',
			default => 'approval_granted'
		};
	}

	/**
	 * @param array<string,mixed> $meta
	 */
	private function wasApproved(string $status, array $meta): bool {
		if (in_array($status, [
			'approval_granted',
			'approved_started',
			'approved_finished',
			'approved_failed'
		], true)) {
			return true;
		}

		$approval = $meta['approval'] ?? null;
		return is_array($approval) && trim((string)($approval['status'] ?? '')) === 'granted';
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildBaseRecord(object $event): array {
		$trace = $this->getTrace($event);
		$user = $this->getCurrentUser();
		$callIndex = $this->getEventCallIndex($event);

		return [
			'turn_id' => $this->traceString($trace, 'turn_id', 'unknown_turn'),
			'call_index' => $callIndex > 0 ? $callIndex : (int)$event->getIteration(),
			'chatbot_key' => $this->traceString($trace, 'chatbot_key', 'unknown_chatbot'),
			'config_group' => $this->traceString($trace, 'config_group', 'unknown_group'),
			'config_name' => $this->traceString($trace, 'config_name', 'unknown_config'),
			'user_id' => $user['id'],
			'user_login' => $user['login']
		];
	}

	/**
	 * @return array{id:int,login:string}
	 */
	private function getCurrentUser(): array {
		try {
			$user = $this->usermanager->getUser();
		} catch (\Throwable $e) {
			$user = null;
		}

		$userId = $this->readUserId($user);
		$userLogin = $this->readUserLogin($user, $userId);

		return [
			'id' => $userId,
			'login' => $userLogin
		];
	}

	private function readUserId(mixed $user): int {
		if (is_int($user)) {
			return $user;
		}

		if (is_string($user) && is_numeric($user)) {
			return (int)$user;
		}

		if (is_float($user)) {
			return (int)$user;
		}

		$value = $this->readUserValue($user, ['id', 'user_id', 'usr_id'], ['getId', 'getUserId', 'getUsrId']);
		return $this->normalizeUserId($value);
	}

	private function readUserLogin(mixed $user, int $userId): string {
		$value = $this->readUserValue($user, ['login', 'name', 'username', 'user_name', 'email'], ['getLogin', 'getName', 'getUsername', 'getUserName', 'getEmail']);

		if (is_scalar($value)) {
			$value = trim((string)$value);
			if ($value !== '') {
				return $value;
			}
		}

		if ($userId > 0) {
			return 'user_' . $userId;
		}

		return 'unknown_user';
	}

	/**
	 * @param array<int,string> $keys
	 * @param array<int,string> $methods
	 */
	private function readUserValue(mixed $user, array $keys, array $methods): mixed {
		if (is_array($user)) {
			foreach ($keys as $key) {
				if (array_key_exists($key, $user)) {
					return $user[$key];
				}
			}
		}

		if (is_object($user)) {
			foreach ($keys as $key) {
				if (property_exists($user, $key)) {
					return $user->$key;
				}
			}

			foreach ($methods as $method) {
				if (method_exists($user, $method)) {
					return $user->$method();
				}
			}
		}

		return null;
	}

	private function normalizeUserId(mixed $value): int {
		if (is_int($value)) {
			return $value;
		}

		if (is_string($value) && is_numeric($value)) {
			return (int)$value;
		}

		if (is_float($value)) {
			return (int)$value;
		}

		return 0;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function getTrace(object $event): array {
		$trace = $event->getTrace();
		return is_array($trace) ? $trace : [];
	}

	private function getEventCallIndex(object $event): int {
		return max(0, (int)$event->getCallIndex());
	}

	/**
	 * @param array<string,mixed> $trace
	 */
	private function traceString(array $trace, string $key, string $default): string {
		$value = $trace[$key] ?? null;

		if (is_scalar($value)) {
			$value = trim((string)$value);
			if ($value !== '') {
				return $value;
			}
		}

		return $default;
	}


	/**
	 * @param array<string,mixed> $record
	 */
	private function recordString(array $record, string $key, string $default): string {
		$value = $record[$key] ?? null;

		if (is_scalar($value)) {
			$value = trim((string)$value);
			if ($value !== '') {
				return $value;
			}
		}

		return $default;
	}

	/**
	 * @param array<string,mixed> $record
	 */
	private function nullableRecordString(array $record, string $key): ?string {
		$value = $record[$key] ?? null;

		if ($value === null) {
			return null;
		}

		return (string)$value;
	}

	private function normalizeTimestamp(string $timestamp): string {
		try {
			return (new \DateTimeImmutable($timestamp))->format('Y-m-d H:i:s');
		} catch (\Throwable $e) {
			return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeJsonArray(mixed $value): array {
		if (!is_string($value) || trim($value) === '') {
			return [];
		}

		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * @param array<string,mixed> $base
	 * @param array<string,mixed> $patch
	 * @return array<string,mixed>
	 */
	private function mergeMeta(array $base, array $patch): array {
		return array_replace_recursive($base, $patch);
	}

	private function encodeJson(mixed $value): ?string {
		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return $json === false ? null : $json;
	}

	private function quote(string $value): string {
		return "'" . $this->database->escape($value) . "'";
	}

	private function quoteNullable(?string $value): string {
		if ($value === null) {
			return 'NULL';
		}

		return $this->quote($value);
	}
}

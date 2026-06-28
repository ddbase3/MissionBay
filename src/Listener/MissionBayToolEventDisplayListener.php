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
		try {
			$this->database->connect();
			$this->ensureSchema();

			if ($this->recordExists($event->getNodeId(), $event->getCallId())) {
				$this->updateStarted($event);
				return;
			}

			$this->insertStarted($event);
		} catch (\Throwable $e) {
		}
	}

	public function onToolFinished(MissionBayToolFinishedEvent $event): void {
		try {
			$this->database->connect();
			$this->ensureSchema();

			if ($this->recordExists($event->getNodeId(), $event->getCallId())) {
				$this->updateFinished($event);
				return;
			}

			$this->insertFinished($event);
		} catch (\Throwable $e) {
		}
	}

	public function onToolFailed(MissionBayToolFailedEvent $event): void {
		try {
			$this->database->connect();
			$this->ensureSchema();

			if ($this->recordExists($event->getNodeId(), $event->getCallId())) {
				$this->updateFailed($event);
				return;
			}

			$this->insertFailed($event);
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

	private function recordExists(string $nodeId, string $callId): bool {
		$sql = '
			SELECT `id`
			FROM `' . self::TABLE . '`
			WHERE `node_id` = ' . $this->quote($nodeId) . '
				AND `call_id` = ' . $this->quote($callId) . '
			LIMIT 1
		';

		$row = $this->database->singleQuery($sql);
		return is_array($row);
	}

	private function insertStarted(MissionBayToolStartedEvent $event): void {
		$time = $this->normalizeTimestamp($event->getTimestamp());
		$record = $this->buildBaseRecord($event);

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
				' . $this->quote($event->getNodeId()) . ',
				' . $this->quote($event->getCallId()) . ',
				' . (int)$record['call_index'] . ',
				' . $this->quote($record['chatbot_key']) . ',
				' . $this->quote($record['config_group']) . ',
				' . $this->quote($record['config_name']) . ',
				' . (int)$record['user_id'] . ',
				' . $this->quote($record['user_login']) . ',
				NULL,
				NULL,
				' . $this->quote($event->getToolName()) . ',
				' . $this->quote($event->getLabel()) . ',
				' . (int)$event->getIteration() . ',
				' . $this->quote('started') . ',
				' . $this->quoteNullable($this->encodeJson($event->getArguments())) . ',
				NULL,
				NULL,
				NULL,
				NULL,
				' . $this->quote($time) . ',
				' . $this->quote($time) . ',
				NULL
			)
		';

		$this->database->nonQuery($sql);
	}

	private function updateStarted(MissionBayToolStartedEvent $event): void {
		$time = $this->normalizeTimestamp($event->getTimestamp());
		$record = $this->buildBaseRecord($event);

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
				`tool_name` = ' . $this->quote($event->getToolName()) . ',
				`label` = ' . $this->quote($event->getLabel()) . ',
				`iteration` = ' . (int)$event->getIteration() . ',
				`status` = ' . $this->quote('started') . ',
				`arguments_json` = ' . $this->quoteNullable($this->encodeJson($event->getArguments())) . ',
				`updated_at` = ' . $this->quote($time) . '
			WHERE `node_id` = ' . $this->quote($event->getNodeId()) . '
				AND `call_id` = ' . $this->quote($event->getCallId()) . '
		';

		$this->database->nonQuery($sql);
	}

	private function insertFinished(MissionBayToolFinishedEvent $event): void {
		$time = $this->normalizeTimestamp($event->getTimestamp());
		$record = $this->buildBaseRecord($event);

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
				' . $this->quote($event->getNodeId()) . ',
				' . $this->quote($event->getCallId()) . ',
				' . (int)$record['call_index'] . ',
				' . $this->quote($record['chatbot_key']) . ',
				' . $this->quote($record['config_group']) . ',
				' . $this->quote($record['config_name']) . ',
				' . (int)$record['user_id'] . ',
				' . $this->quote($record['user_login']) . ',
				NULL,
				NULL,
				' . $this->quote($event->getToolName()) . ',
				' . $this->quote($event->getLabel()) . ',
				' . (int)$event->getIteration() . ',
				' . $this->quote('finished') . ',
				' . $this->quoteNullable($this->encodeJson($event->getArguments())) . ',
				' . $this->quoteNullable($this->encodeJson($event->getResult())) . ',
				NULL,
				NULL,
				NULL,
				' . $this->quote($time) . ',
				' . $this->quote($time) . ',
				' . $this->quote($time) . '
			)
		';

		$this->database->nonQuery($sql);
	}

	private function updateFinished(MissionBayToolFinishedEvent $event): void {
		$time = $this->normalizeTimestamp($event->getTimestamp());
		$record = $this->buildBaseRecord($event);

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
				`tool_name` = ' . $this->quote($event->getToolName()) . ',
				`label` = ' . $this->quote($event->getLabel()) . ',
				`iteration` = ' . (int)$event->getIteration() . ',
				`status` = ' . $this->quote('finished') . ',
				`arguments_json` = ' . $this->quoteNullable($this->encodeJson($event->getArguments())) . ',
				`result_json` = ' . $this->quoteNullable($this->encodeJson($event->getResult())) . ',
				`error_message` = NULL,
				`error_type` = NULL,
				`error_code` = NULL,
				`updated_at` = ' . $this->quote($time) . ',
				`finished_at` = ' . $this->quote($time) . '
			WHERE `node_id` = ' . $this->quote($event->getNodeId()) . '
				AND `call_id` = ' . $this->quote($event->getCallId()) . '
		';

		$this->database->nonQuery($sql);
	}

	private function insertFailed(MissionBayToolFailedEvent $event): void {
		$time = $this->normalizeTimestamp($event->getTimestamp());
		$record = $this->buildBaseRecord($event);

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
				' . $this->quote($event->getNodeId()) . ',
				' . $this->quote($event->getCallId()) . ',
				' . (int)$record['call_index'] . ',
				' . $this->quote($record['chatbot_key']) . ',
				' . $this->quote($record['config_group']) . ',
				' . $this->quote($record['config_name']) . ',
				' . (int)$record['user_id'] . ',
				' . $this->quote($record['user_login']) . ',
				NULL,
				NULL,
				' . $this->quote($event->getToolName()) . ',
				' . $this->quote($event->getLabel()) . ',
				' . (int)$event->getIteration() . ',
				' . $this->quote('failed') . ',
				' . $this->quoteNullable($this->encodeJson($event->getArguments())) . ',
				NULL,
				' . $this->quoteNullable($event->getErrorMessage()) . ',
				' . $this->quoteNullable($event->getErrorType()) . ',
				' . $this->quoteNullable((string)$event->getErrorCode()) . ',
				' . $this->quote($time) . ',
				' . $this->quote($time) . ',
				' . $this->quote($time) . '
			)
		';

		$this->database->nonQuery($sql);
	}

	private function updateFailed(MissionBayToolFailedEvent $event): void {
		$time = $this->normalizeTimestamp($event->getTimestamp());
		$record = $this->buildBaseRecord($event);

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
				`tool_name` = ' . $this->quote($event->getToolName()) . ',
				`label` = ' . $this->quote($event->getLabel()) . ',
				`iteration` = ' . (int)$event->getIteration() . ',
				`status` = ' . $this->quote('failed') . ',
				`arguments_json` = ' . $this->quoteNullable($this->encodeJson($event->getArguments())) . ',
				`result_json` = NULL,
				`error_message` = ' . $this->quoteNullable($event->getErrorMessage()) . ',
				`error_type` = ' . $this->quoteNullable($event->getErrorType()) . ',
				`error_code` = ' . $this->quoteNullable((string)$event->getErrorCode()) . ',
				`updated_at` = ' . $this->quote($time) . ',
				`finished_at` = ' . $this->quote($time) . '
			WHERE `node_id` = ' . $this->quote($event->getNodeId()) . '
				AND `call_id` = ' . $this->quote($event->getCallId()) . '
		';

		$this->database->nonQuery($sql);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildBaseRecord(mixed $event): array {
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
	private function getTrace(mixed $event): array {
		if (!method_exists($event, 'getTrace')) {
			return [];
		}

		$trace = $event->getTrace();
		return is_array($trace) ? $trace : [];
	}

	private function getEventCallIndex(mixed $event): int {
		if (!method_exists($event, 'getCallIndex')) {
			return 0;
		}

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

	private function normalizeTimestamp(string $timestamp): string {
		try {
			return (new \DateTimeImmutable($timestamp))->format('Y-m-d H:i:s');
		} catch (\Throwable $e) {
			return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
		}
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

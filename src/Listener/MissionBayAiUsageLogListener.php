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

use AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Api\IRequest;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use Base3\Usermanager\Api\IUsermanager;
use RuntimeException;
use Throwable;

final class MissionBayAiUsageLogListener {

	private const TABLE = 'base3_missionbay_ai_usage';

	private bool $schemaEnsured = false;

	public function __construct(
		private readonly IDatabase $database,
		private readonly ILogger $logger,
		private readonly IUsermanager $usermanager,
		private readonly IRequest $request
	) {}

	public function onProviderRequestCompleted(AiProviderRequestCompletedEvent $event): void {
		try {
			$this->database->connect();
			$this->ensureSchema();
			$this->insertEvent($event);
		} catch(Throwable $e) {
			$this->logFailure($e, $event);
		}
	}

	private function ensureSchema(): void {
		if($this->schemaEnsured) {
			return;
		}

		$this->database->nonQuery(
			'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`operation` VARCHAR(64) NOT NULL,
				`source_name` VARCHAR(191) NOT NULL,
				`provider` VARCHAR(191) NOT NULL DEFAULT \'\',
				`model` VARCHAR(191) NOT NULL DEFAULT \'\',
				`request_id` VARCHAR(191) NULL,
				`user_id` INT NOT NULL DEFAULT 0,
				`user_login` VARCHAR(191) NOT NULL DEFAULT \'unknown_user\',
				`request_context` VARCHAR(32) NOT NULL DEFAULT \'unknown\',
				`input_tokens` BIGINT NULL,
				`output_tokens` BIGINT NULL,
				`total_tokens` BIGINT NULL,
				`cached_input_tokens` BIGINT NULL,
				`reasoning_tokens` BIGINT NULL,
				`duration_ms` DECIMAL(18,3) NULL,
				`finish_reason` VARCHAR(191) NULL,
				`provider_created_at` DATETIME NULL,
				`metrics_json` LONGTEXT NULL,
				`details_json` LONGTEXT NULL,
				`extra_json` LONGTEXT NULL,
				`occurred_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_ai_usage_occurred_at` (`occurred_at`),
				KEY `idx_ai_usage_provider_model` (`provider`, `model`, `occurred_at`),
				KEY `idx_ai_usage_operation` (`operation`, `occurred_at`),
				KEY `idx_ai_usage_request_id` (`request_id`),
				KEY `idx_ai_usage_user_time` (`user_id`, `occurred_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
		);

		$this->assertDatabaseSuccess('Unable to create MissionBay AI usage table.');
		$this->schemaEnsured = true;
	}

	private function insertEvent(AiProviderRequestCompletedEvent $event): void {
		$metadata = $event->getMetadata();
		$usage = $event->getUsage();
		$createdAt = $metadata->getCreatedAt();
		$durationMs = $metadata->getDurationMs();
		$user = $this->getCurrentUser();
		$requestContext = trim($this->request->getContext());

		if($requestContext === '') {
			$requestContext = 'unknown';
		}

		$sql = '
			INSERT INTO `' . self::TABLE . '` (
				`operation`,
				`source_name`,
				`provider`,
				`model`,
				`request_id`,
				`user_id`,
				`user_login`,
				`request_context`,
				`input_tokens`,
				`output_tokens`,
				`total_tokens`,
				`cached_input_tokens`,
				`reasoning_tokens`,
				`duration_ms`,
				`finish_reason`,
				`provider_created_at`,
				`metrics_json`,
				`details_json`,
				`extra_json`,
				`occurred_at`
			) VALUES (
				' . $this->quote($metadata->getOperation()) . ',
				' . $this->quote($event->getSourceName()) . ',
				' . $this->quote($metadata->getProvider()) . ',
				' . $this->quote($metadata->getModel()) . ',
				' . $this->quoteNullable($this->emptyToNull($metadata->getRequestId())) . ',
				' . $user['id'] . ',
				' . $this->quote($user['login']) . ',
				' . $this->quote($requestContext) . ',
				' . $this->intNullable($usage->getInputTokens()) . ',
				' . $this->intNullable($usage->getOutputTokens()) . ',
				' . $this->intNullable($usage->getTotalTokens()) . ',
				' . $this->intNullable($usage->getCachedInputTokens()) . ',
				' . $this->intNullable($usage->getReasoningTokens()) . ',
				' . $this->floatNullable($durationMs) . ',
				' . $this->quoteNullable($this->emptyToNull($metadata->getFinishReason())) . ',
				' . $this->quoteNullable($createdAt !== null ? $this->formatTimestamp($createdAt) : null) . ',
				' . $this->quoteNullable($this->encodeJson($usage->getMetrics())) . ',
				' . $this->quoteNullable($this->encodeJson($usage->getDetails())) . ',
				' . $this->quoteNullable($this->encodeJson($metadata->getExtra())) . ',
				' . $this->quote($this->formatTimestamp($event->getOccurredAt())) . '
			)
		';

		$this->database->nonQuery($sql);
		$this->assertDatabaseSuccess('Unable to store MissionBay AI usage event.');
	}

	private function assertDatabaseSuccess(string $message): void {
		if(!$this->database->isError()) {
			return;
		}

		$error = trim($this->database->errorMessage());
		throw new RuntimeException($error !== '' ? $message . ' ' . $error : $message);
	}

	private function logFailure(Throwable $error, AiProviderRequestCompletedEvent $event): void {
		try {
			$this->logger->error('Unable to persist MissionBay AI usage event.', [
				'exception' => $error,
				'operation' => $event->getMetadata()->getOperation(),
				'provider' => $event->getMetadata()->getProvider(),
				'model' => $event->getMetadata()->getModel(),
				'source_name' => $event->getSourceName()
			]);
		} catch(Throwable $ignored) {
		}
	}

	/**
	 * @return array{id:int,login:string}
	 */
	private function getCurrentUser(): array {
		try {
			$user = $this->usermanager->getUser();
		} catch(Throwable $e) {
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
		if(is_int($user)) {
			return $user;
		}

		if(is_string($user) && is_numeric($user)) {
			return (int)$user;
		}

		if(is_float($user)) {
			return (int)$user;
		}

		$value = $this->readUserValue($user, ['id', 'user_id', 'usr_id'], ['getId', 'getUserId', 'getUsrId']);
		return $this->normalizeUserId($value);
	}

	private function readUserLogin(mixed $user, int $userId): string {
		$value = $this->readUserValue(
			$user,
			['login', 'name', 'username', 'user_name', 'email'],
			['getLogin', 'getName', 'getUsername', 'getUserName', 'getEmail']
		);

		if(is_scalar($value)) {
			$value = trim((string)$value);
			if($value !== '') {
				return $value;
			}
		}

		if($userId > 0) {
			return 'user_' . $userId;
		}

		return 'unknown_user';
	}

	/**
	 * @param array<int,string> $keys
	 * @param array<int,string> $methods
	 */
	private function readUserValue(mixed $user, array $keys, array $methods): mixed {
		if(is_array($user)) {
			foreach($keys as $key) {
				if(array_key_exists($key, $user)) {
					return $user[$key];
				}
			}
		}

		if(is_object($user)) {
			foreach($keys as $key) {
				if(property_exists($user, $key)) {
					return $user->$key;
				}
			}

			foreach($methods as $method) {
				if(method_exists($user, $method)) {
					return $user->$method();
				}
			}
		}

		return null;
	}

	private function normalizeUserId(mixed $value): int {
		if(is_int($value)) {
			return $value;
		}

		if(is_string($value) && is_numeric($value)) {
			return (int)$value;
		}

		if(is_float($value)) {
			return (int)$value;
		}

		return 0;
	}

	private function formatTimestamp(int $timestamp): string {
		return gmdate('Y-m-d H:i:s', $timestamp);
	}

	/**
	 * @param array<string,mixed> $value
	 */
	private function encodeJson(array $value): ?string {
		if($value === []) {
			return null;
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return $json === false ? null : $json;
	}

	private function emptyToNull(?string $value): ?string {
		if($value === null) {
			return null;
		}

		$value = trim($value);
		return $value !== '' ? $value : null;
	}

	private function intNullable(?int $value): string {
		return $value === null ? 'NULL' : (string)$value;
	}

	private function floatNullable(?float $value): string {
		if($value === null || !is_finite($value)) {
			return 'NULL';
		}

		return number_format($value, 3, '.', '');
	}

	private function quote(string $value): string {
		return "'" . $this->database->escape($value) . "'";
	}

	private function quoteNullable(?string $value): string {
		return $value === null ? 'NULL' : $this->quote($value);
	}
}

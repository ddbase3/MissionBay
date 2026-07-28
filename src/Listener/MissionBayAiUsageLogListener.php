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
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use RuntimeException;
use Throwable;

final class MissionBayAiUsageLogListener {

	private const TABLE = 'base3_missionbay_ai_usage';

	private bool $schemaEnsured = false;

	public function __construct(
		private readonly IDatabase $database,
		private readonly ILogger $logger
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
				KEY `idx_ai_usage_request_id` (`request_id`)
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

		$sql = '
			INSERT INTO `' . self::TABLE . '` (
				`operation`,
				`source_name`,
				`provider`,
				`model`,
				`request_id`,
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

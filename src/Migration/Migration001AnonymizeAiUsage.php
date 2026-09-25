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

namespace MissionBay\Migration;

use Base3\Database\Api\IDatabase;
use Base3\Migration\Api\IDatabaseMigration;
use RuntimeException;

final class Migration001AnonymizeAiUsage implements IDatabaseMigration {

	private const TABLE = 'base3_missionbay_ai_usage';

	public function __construct(
		private readonly IDatabase $database
	) {}

	public static function getName(): string {
		return 'missionbay_ai_usage_001_anonymize_user_data';
	}

	public function getVersion(): string {
		return '001';
	}

	public function getDescription(): string {
		return 'Anonymizes stored MissionBay AI usage user data and removes the user login column.';
	}

	public function up(): void {
		$this->database->connect();

		if(!$this->tableExists()) {
			return;
		}

		$this->database->nonQuery(
			'UPDATE `' . self::TABLE . '` SET `user_id` = 0 WHERE `user_id` <> 0'
		);
		$this->assertDatabaseSuccess('Unable to anonymize MissionBay AI usage user IDs.');

		if(!$this->columnExists('user_login')) {
			return;
		}

		$this->database->nonQuery(
			'ALTER TABLE `' . self::TABLE . '` DROP COLUMN `user_login`'
		);
		$this->assertDatabaseSuccess('Unable to remove MissionBay AI usage user login column.');
	}

	private function tableExists(): bool {
		$count = $this->database->scalarQuery(
			"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . self::TABLE . "'"
		);

		return (int)$count > 0;
	}

	private function columnExists(string $column): bool {
		$count = $this->database->scalarQuery(
			"SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '" . self::TABLE . "' AND column_name = '" . $this->database->escape($column) . "'"
		);

		return (int)$count > 0;
	}

	private function assertDatabaseSuccess(string $message): void {
		if(!$this->database->isError()) {
			return;
		}

		$error = trim($this->database->errorMessage());
		throw new RuntimeException($error !== '' ? $message . ' ' . $error : $message);
	}
}

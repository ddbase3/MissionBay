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

/**
 * Add transparent owner identity and independently versioned encryption metadata.
 * Legacy hashes stay available for safe, on-access backfill and DSR deletion;
 * a later migration may drop them only after remaining records are resolved.
 */
final class Migration001ConversationIdentityAndEncryption implements IDatabaseMigration {

	private const CONVERSATION_TABLE = 'base3_missionbay_conversation';
	private const MESSAGE_TABLE = 'base3_missionbay_conversation_message';

	public function __construct(private readonly IDatabase $database) {}

	public static function getName(): string {
		return 'migration001conversationidentityandencryption';
	}

	public function getVersion(): string {
		return '001';
	}

	public function getDescription(): string {
		return 'Add explicit conversation owners and per-record encryption metadata.';
	}

	public function up(): void {
		$this->database->connect();

		if (!$this->tableExists(self::CONVERSATION_TABLE)) {
			$this->database->nonQuery(
				'CREATE TABLE `' . self::CONVERSATION_TABLE . '` ('
				. '`conversation_key` CHAR(40) NOT NULL,'
				. ' `owner_key` CHAR(64) NULL,'
				. ' `owner_type` VARCHAR(16) NULL,'
				. ' `owner_id` VARCHAR(191) COLLATE utf8mb4_bin NULL,'
				. ' `channel_id` VARCHAR(191) NOT NULL,'
				. ' `memory_namespace` VARCHAR(100) NOT NULL,'
				. ' `conversation_id` VARCHAR(100) NOT NULL,'
				. ' `chatbot_config_group` VARCHAR(191) NULL,'
				. ' `chatbot_config_name` VARCHAR(191) NULL,'
				. ' `title` LONGTEXT NOT NULL,'
				. ' `title_source` VARCHAR(20) NOT NULL,'
				. ' `opening_message` LONGTEXT NULL,'
				. " `encryption_method` VARCHAR(64) NOT NULL DEFAULT 'none',"
				. ' `encryption_key_id` VARCHAR(191) NULL,'
				. ' `created_at` DATETIME(6) NOT NULL,'
				. ' `updated_at` DATETIME(6) NOT NULL,'
				. ' `last_active_at` DATETIME(6) NOT NULL,'
				. ' PRIMARY KEY (`conversation_key`),'
				. ' UNIQUE KEY `uq_mb_conversation_owner` (`owner_type`, `owner_id`, `channel_id`, `memory_namespace`, `conversation_id`),'
				. ' KEY `idx_mb_conversation_owner_active` (`owner_type`, `owner_id`, `channel_id`, `memory_namespace`, `last_active_at`),'
				. ' KEY `idx_mb_conversation_legacy_owner` (`owner_key`)'
				. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
			);
			$this->assertSuccess('Could not create MissionBay conversations table.');
		}
		else {
			$this->addColumn(self::CONVERSATION_TABLE, 'owner_type', '`owner_type` VARCHAR(16) NULL');
			$this->addColumn(self::CONVERSATION_TABLE, 'owner_id', '`owner_id` VARCHAR(191) COLLATE utf8mb4_bin NULL');
			$this->addColumn(self::CONVERSATION_TABLE, 'chatbot_config_group', '`chatbot_config_group` VARCHAR(191) NULL');
			$this->addColumn(self::CONVERSATION_TABLE, 'chatbot_config_name', '`chatbot_config_name` VARCHAR(191) NULL');
			$this->addColumn(self::CONVERSATION_TABLE, 'encryption_method', "`encryption_method` VARCHAR(64) NOT NULL DEFAULT 'none'");
			$this->addColumn(self::CONVERSATION_TABLE, 'encryption_key_id', '`encryption_key_id` VARCHAR(191) NULL');
			$this->database->nonQuery('ALTER TABLE `' . self::CONVERSATION_TABLE . '` MODIFY COLUMN `owner_key` CHAR(64) NULL');
			$this->assertSuccess('Could not allow migration of legacy owner keys.');
			$this->database->nonQuery('ALTER TABLE `' . self::CONVERSATION_TABLE . '` MODIFY COLUMN `title` LONGTEXT NOT NULL');
			$this->assertSuccess('Could not enlarge encrypted conversation title storage.');
			$this->addIndex(self::CONVERSATION_TABLE, 'uq_mb_conversation_owner',
				'UNIQUE KEY `uq_mb_conversation_owner` (`owner_type`, `owner_id`, `channel_id`, `memory_namespace`, `conversation_id`)');
			$this->addIndex(self::CONVERSATION_TABLE, 'idx_mb_conversation_owner_active',
				'KEY `idx_mb_conversation_owner_active` (`owner_type`, `owner_id`, `channel_id`, `memory_namespace`, `last_active_at`)');
			$this->addIndex(self::CONVERSATION_TABLE, 'idx_mb_conversation_legacy_owner',
				'KEY `idx_mb_conversation_legacy_owner` (`owner_key`)');
		}

		if (!$this->tableExists(self::MESSAGE_TABLE)) {
			$this->database->nonQuery(
				'CREATE TABLE `' . self::MESSAGE_TABLE . '` ('
				. '`message_key` CHAR(40) NOT NULL,'
				. ' `conversation_key` CHAR(40) NOT NULL,'
				. ' `node_id` VARCHAR(100) NOT NULL,'
				. ' `message_id` VARCHAR(100) NOT NULL,'
				. ' `role` VARCHAR(50) NOT NULL,'
				. ' `content` LONGTEXT NOT NULL,'
				. ' `payload` LONGTEXT NULL,'
				. " `encryption_method` VARCHAR(64) NOT NULL DEFAULT 'none',"
				. ' `encryption_key_id` VARCHAR(191) NULL,'
				. ' `created_at` DATETIME(6) NOT NULL,'
				. ' PRIMARY KEY (`message_key`),'
				. ' UNIQUE KEY `uq_mb_conversation_message` (`conversation_key`, `node_id`, `message_id`),'
				. ' KEY `idx_mb_conversation_node` (`conversation_key`, `node_id`, `created_at`, `message_key`),'
				. ' CONSTRAINT `fk_mb_conversation_message` FOREIGN KEY (`conversation_key`)'
				. ' REFERENCES `' . self::CONVERSATION_TABLE . '` (`conversation_key`) ON DELETE CASCADE'
				. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
			);
			$this->assertSuccess('Could not create MissionBay messages table.');
		}
		else {
			$this->addColumn(self::MESSAGE_TABLE, 'encryption_method', "`encryption_method` VARCHAR(64) NOT NULL DEFAULT 'none'");
			$this->addColumn(self::MESSAGE_TABLE, 'encryption_key_id', '`encryption_key_id` VARCHAR(191) NULL');
		}
	}

	private function addColumn(string $table, string $column, string $definition): void {
		if ($this->columnExists($table, $column)) {
			return;
		}
		$this->database->nonQuery('ALTER TABLE `' . $table . '` ADD COLUMN ' . $definition);
		$this->assertSuccess('Could not add MissionBay column ' . $column . '.');
	}

	private function addIndex(string $table, string $index, string $definition): void {
		if ($this->indexExists($table, $index)) {
			return;
		}
		$this->database->nonQuery('ALTER TABLE `' . $table . '` ADD ' . $definition);
		$this->assertSuccess('Could not add MissionBay index ' . $index . '.');
	}

	private function tableExists(string $table): bool {
		return (int)$this->database->scalarQuery(
			"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $table . "'"
		) > 0;
	}

	private function columnExists(string $table, string $column): bool {
		return (int)$this->database->scalarQuery(
			"SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '" . $table
			. "' AND column_name = '" . $column . "'"
		) > 0;
	}

	private function indexExists(string $table, string $index): bool {
		return (int)$this->database->scalarQuery(
			"SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '" . $table
			. "' AND index_name = '" . $index . "'"
		) > 0;
	}

	private function assertSuccess(string $message): void {
		if (!$this->database->isError()) {
			return;
		}
		$detail = trim($this->database->errorMessage());
		throw new \RuntimeException($detail !== '' ? $message . ' ' . $detail : $message);
	}
}

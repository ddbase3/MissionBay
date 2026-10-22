<?php declare(strict_types=1);

namespace MissionBay\Diagnostics;

use Base3\Database\Api\IDatabase;
use RuntimeException;

/** Shared, read-only inventory of persisted MissionBay conversation ownership. */
final class ConversationDiagnosticInventory {

	private const CONVERSATIONS = 'base3_missionbay_conversation';
	private const MESSAGES = 'base3_missionbay_conversation_message';

	public function __construct(private readonly IDatabase $database) {}

	public function available(): bool {
		$this->database->connect();
		if (!$this->database->connected()) {
			throw new RuntimeException('Conversation diagnostic database is unavailable.');
		}
		foreach ([self::CONVERSATIONS, self::MESSAGES] as $table) {
			$count = $this->database->scalarQuery(
				"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $table . "'"
			);
			if (!is_numeric($count)) {
				throw new RuntimeException('Conversation diagnostic cannot inspect table availability.');
			}
			if ((int)$count === 0) {
				return false;
			}
		}
		return true;
	}

	/** @return array<int,array<string,mixed>> Per-owner aggregate, without message content. */
	public function owners(): array {
		return $this->database->multiQuery(
			'SELECT c.owner_id, COUNT(DISTINCT c.conversation_key) AS conversations, '
			. 'COUNT(m.message_key) AS messages'
			. ' FROM ' . self::CONVERSATIONS . ' c'
			. ' LEFT JOIN ' . self::MESSAGES . ' m ON m.conversation_key=c.conversation_key'
			. " WHERE c.owner_type='user' AND c.owner_id IS NOT NULL"
			. ' GROUP BY c.owner_id'
		);
	}

	/** @return array<int,array<string,mixed>> Per-chat-object aggregate, no IDs in public results. */
	public function chatbotObjects(): array {
		return $this->database->multiQuery(
			'SELECT c.chatbot_config_group, c.chatbot_config_name, '
			. 'COUNT(DISTINCT c.conversation_key) AS conversations, COUNT(m.message_key) AS messages'
			. ' FROM ' . self::CONVERSATIONS . ' c'
			. ' LEFT JOIN ' . self::MESSAGES . ' m ON m.conversation_key=c.conversation_key'
			. " WHERE c.channel_id LIKE 'chatbot:%'"
			. ' GROUP BY c.chatbot_config_group, c.chatbot_config_name'
		);
	}

	/**
	 * Count legacy rows that cannot yet be assigned to a native user or session.
	 * @return int Number of unresolved rows, excluding no records
	 */
	public function unresolvedOwners(): int {
		return $this->count(
			'SELECT COUNT(*) FROM ' . self::CONVERSATIONS . ' WHERE owner_type IS NULL OR owner_id IS NULL'
		);
	}

	private function count(string $sql): int {
		$value = $this->database->scalarQuery($sql);
		if (!is_numeric($value) || (float)$value < 0 || (float)$value > PHP_INT_MAX) {
			throw new RuntimeException('Conversation diagnostic returned an invalid count.');
		}
		return (int)$value;
	}
}

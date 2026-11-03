<?php declare(strict_types=1);

namespace MissionBay\Test\Diagnostics;

use Base3\Database\Api\IDatabase;
use Base3\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\User;
use MissionBay\Diagnostics\ConversationMissingChatObjectDiagnosticCheck;
use MissionBay\Diagnostics\ConversationMissingUserDiagnosticCheck;
use PHPUnit\Framework\TestCase;

final class ConversationDiagnosticsDatabaseStub implements IDatabase {

	public bool $installed = true;
	public int $legacy = 0;
	/** @var array<int,array<string,mixed>> */
	public array $owners = [];
	/** @var array<int,array<string,mixed>> */
	public array $chatObjects = [];
	private array $result = [];

	public function connect(): void {}
	public function connected(): bool { return true; }
	public function disconnect(): void {}
	public function beginTransaction(): void {}
	public function commit(): void {}
	public function rollback(): void {}
	public function nonQuery(string $query): void { throw new \RuntimeException('Diagnostics must never mutate database records.'); }
	public function scalarQuery(string $query): mixed {
		if (str_contains($query, 'information_schema.tables')) return $this->installed ? 1 : 0;
		if (str_contains($query, 'owner_type IS NULL')) return $this->legacy;
		throw new \RuntimeException('Unexpected diagnostic SQL.');
	}
	public function singleQuery(string $query): ?array { throw new \RuntimeException('Unexpected diagnostic SQL.'); }
	public function &listQuery(string $query): array { throw new \RuntimeException('Unexpected diagnostic SQL.'); }
	public function &multiQuery(string $query): array {
		if (str_contains($query, 'GROUP BY c.owner_id')) $this->result = $this->owners;
		elseif (str_contains($query, 'GROUP BY c.chatbot_config_group')) $this->result = $this->chatObjects;
		else throw new \RuntimeException('Unexpected diagnostic SQL.');
		return $this->result;
	}
	public function affectedRows(): int { return 0; }
	public function insertId(): int|string { return 0; }
	public function escape(string $str): string { return addslashes($str); }
	public function isError(): bool { return false; }
	public function errorNumber(): int { return 0; }
	public function errorMessage(): string { return ''; }
}

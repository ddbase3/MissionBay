<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Settings\Api\ISettingsStore;

/**
 * McpConfirmationStore
 *
 * Stores pending MCP confirmations in the BASE3 settings store.
 */
class McpConfirmationStore {

	private const GROUP = 'mcp-confirmation';
	private const DEFAULT_TTL_SECONDS = 900;

	public function __construct(private readonly ISettingsStore $settingsStore) {}

	public static function getName(): string {
		return 'mcpconfirmationstore';
	}

	/**
	 * @param array<string,mixed> $confirmation
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	public function create(
		string $profileId,
		string $toolName,
		array $arguments,
		array $confirmation,
		string $callId,
		string $nodeId
	): array {
		$id = $this->generateId();
		$createdAt = time();
		$ttl = (int)($confirmation['ttl_seconds'] ?? self::DEFAULT_TTL_SECONDS);
		$ttl = max(60, min(3600, $ttl));

		$record = [
			'id' => $id,
			'call_id' => $callId,
			'node_id' => $nodeId,
			'profile' => $profileId,
			'tool' => $toolName,
			'arguments' => $arguments,
			'confirmation' => $this->normalizeConfirmation($confirmation),
			'status' => 'pending',
			'created_at' => gmdate('c', $createdAt),
			'expires_at' => gmdate('c', $createdAt + $ttl)
		];

		$this->settingsStore->set(self::GROUP, $id, $record);
		$this->settingsStore->save();

		return $record;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getPending(string $id, string $profileId): array {
		$id = trim($id);

		if($id === '') {
			throw new \InvalidArgumentException('Missing confirmation_id.');
		}

		$record = $this->settingsStore->get(self::GROUP, $id, []);

		if($record === [] || !is_array($record)) {
			throw new \RuntimeException('Confirmation not found: ' . $id);
		}

		if(trim((string)($record['profile'] ?? '')) !== $profileId) {
			throw new \RuntimeException('Confirmation does not belong to this MCP profile.');
		}

		if(trim((string)($record['status'] ?? '')) !== 'pending') {
			throw new \RuntimeException('Confirmation is not pending: ' . $id);
		}

		$expiresAt = strtotime((string)($record['expires_at'] ?? ''));

		if($expiresAt !== false && $expiresAt < time()) {
			$this->mark($id, 'expired');
			throw new \RuntimeException('Confirmation has expired: ' . $id);
		}

		return $record;
	}

	/**
	 * @param array<string,mixed> $metadata
	 */
	public function mark(string $id, string $status, array $metadata = []): void {
		$record = $this->settingsStore->get(self::GROUP, $id, []);

		if($record === [] || !is_array($record)) {
			return;
		}

		$record['status'] = $status;
		$record['updated_at'] = gmdate('c');

		if($metadata !== []) {
			$record['decision'] = array_replace(
				is_array($record['decision'] ?? null) ? $record['decision'] : [],
				$metadata
			);
		}

		$this->settingsStore->set(self::GROUP, $id, $record);
		$this->settingsStore->save();
	}

	private function generateId(): string {
		try {
			return 'mcp-cnf-' . bin2hex(random_bytes(16));
		}
		catch(\Throwable) {
			return 'mcp-cnf-' . bin2hex((string)microtime(true));
		}
	}

	/**
	 * @param array<string,mixed> $confirmation
	 * @return array<string,mixed>
	 */
	private function normalizeConfirmation(array $confirmation): array {
		return [
			'title' => trim((string)($confirmation['title'] ?? 'Confirm tool call')),
			'message' => trim((string)($confirmation['message'] ?? 'Please confirm this tool call before it is executed.')),
			'summary' => $this->normalizeList($confirmation['summary'] ?? []),
			'risk' => trim((string)($confirmation['risk'] ?? 'medium')),
			'ttl_seconds' => (int)($confirmation['ttl_seconds'] ?? self::DEFAULT_TTL_SECONDS)
		];
	}

	/**
	 * @return array<int,string>
	 */
	private function normalizeList(mixed $value): array {
		if(is_string($value) && trim($value) !== '') {
			return [trim($value)];
		}

		if(!is_array($value)) {
			return [];
		}

		$list = [];

		foreach($value as $entry) {
			$entry = trim((string)$entry);

			if($entry !== '') {
				$list[] = $entry;
			}
		}

		return $list;
	}
}

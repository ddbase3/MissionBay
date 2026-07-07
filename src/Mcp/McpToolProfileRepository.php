<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Settings\Api\ISettingsStore;

/**
 * McpToolProfileRepository
 *
 * Loads simple tool profiles from the BASE3 settings store.
 */
class McpToolProfileRepository {

	private const GROUP = 'tool-profile';

	public function __construct(private readonly ISettingsStore $settingsStore) {}

	public static function getName(): string {
		return 'mcptoolprofilerepository';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getProfile(string $id): array {
		$id = trim($id);

		if($id === '') {
			throw new \InvalidArgumentException('Missing MCP tool profile id.');
		}

		$profile = $this->settingsStore->get(self::GROUP, $id, []);

		if($profile === []) {
			throw new \RuntimeException('MCP tool profile not found: ' . $id);
		}

		$profile['id'] = (string)($profile['id'] ?? $id);

		return $profile;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getEnabledMcpProfile(string $id): array {
		$profile = $this->getProfile($id);

		$type = strtolower(trim((string)($profile['type'] ?? '')));

		if($type !== 'mcp') {
			throw new \RuntimeException('Tool profile is not an MCP profile: ' . $id);
		}

		if(!$this->isEnabled($profile)) {
			throw new \RuntimeException('MCP tool profile is disabled: ' . $id);
		}

		if(!isset($profile['tools']) || !is_array($profile['tools'])) {
			throw new \RuntimeException('MCP tool profile has no tools array: ' . $id);
		}

		return $profile;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function isEnabled(array $data): bool {
		if(!array_key_exists('enabled', $data)) {
			return true;
		}

		$value = $data['enabled'];

		if(is_bool($value)) {
			return $value;
		}

		if(is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));

		return !in_array($value, ['0', 'false', 'no', 'off'], true);
	}
}

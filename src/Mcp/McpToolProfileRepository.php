<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Settings\Api\ISettingsStore;

/**
 * Loads MissionBay tool profiles and their MCP exposure settings.
 */
final class McpToolProfileRepository {

	private const GROUP = 'tool-profile';
	private const CREDENTIAL_SERVICE_PREFIX = 'missionbay:mcp:';

	public function __construct(private readonly ISettingsStore $settingsStore) {}

	public static function getName(): string {
		return 'mcptoolprofilerepository';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getProfile(string $id): array {
		$id = $this->normalizeProfileId($id);

		if($id === '') {
			throw new \InvalidArgumentException('Missing MCP tool profile id.');
		}

		$profile = $this->settingsStore->get(self::GROUP, $id, []);

		if($profile === []) {
			throw new \RuntimeException('MCP tool profile not found: ' . $id);
		}

		$profile['id'] = $id;

		return $profile;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function getProfiles(): array {
		$group = $this->settingsStore->getGroup(self::GROUP);
		$profiles = [];

		foreach($group as $id => $profile) {
			if((!is_string($id) && !is_int($id)) || !is_array($profile)) {
				continue;
			}

			$id = $this->normalizeProfileId((string)$id);
			if($id === '') {
				continue;
			}

			$profile['id'] = $id;
			$profiles[$id] = $profile;
		}

		ksort($profiles, SORT_STRING);
		return $profiles;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getEnabledMcpProfile(string $id): array {
		$profile = $this->getProfile($id);

		if(!$this->isMcpEnabled($profile)) {
			throw new \RuntimeException('Tool profile is not enabled for MCP exposure: ' . $id);
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
	 * @return array<string,array<string,mixed>>
	 */
	public function getEnabledMcpProfiles(): array {
		$profiles = [];

		foreach($this->getProfiles() as $id => $profile) {
			if(!$this->isEnabled($profile) || !$this->isMcpEnabled($profile)) {
				continue;
			}

			if(!isset($profile['tools']) || !is_array($profile['tools'])) {
				continue;
			}

			$profiles[$id] = $profile;
		}

		return $profiles;
	}

	/**
	 * @param array<string,mixed> $profile
	 */
	public function isFixedBearerEnabled(array $profile): bool {
		if(array_key_exists('mcp_fixed_bearer_enabled', $profile)) {
			return $this->toBool($profile['mcp_fixed_bearer_enabled']);
		}

		return trim((string)($profile['token'] ?? '')) !== '';
	}

	/**
	 * @param array<string,mixed> $profile
	 */
	public function isCredentialAccessEnabled(array $profile): bool {
		return array_key_exists('mcp_credential_enabled', $profile)
			&& $this->toBool($profile['mcp_credential_enabled']);
	}

	public function getCredentialServiceId(string $profileId): string {
		$profileId = $this->normalizeProfileId($profileId);

		if($profileId === '') {
			throw new \InvalidArgumentException('MCP profile id must not be empty.');
		}

		return self::CREDENTIAL_SERVICE_PREFIX . $profileId;
	}

	/**
	 * @param array<string,mixed> $profile
	 */
	private function isMcpEnabled(array $profile): bool {
		$type = strtolower(trim((string)($profile['type'] ?? '')));

		return array_key_exists('mcp_enabled', $profile)
			? $this->toBool($profile['mcp_enabled'])
			: in_array($type, ['mcp', 'hybrid'], true);
	}

	private function normalizeProfileId(string $id): string {
		$id = strtolower(trim($id));
		return preg_replace('/[^a-z0-9._-]+/', '', $id) ?? '';
	}

	private function toBool(mixed $value): bool {
		if(is_bool($value)) return $value;
		if(is_int($value)) return $value !== 0;
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
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

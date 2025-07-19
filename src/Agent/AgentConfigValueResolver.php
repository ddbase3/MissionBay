<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentConfigValueResolver;
use Base3\Configuration\Api\IConfiguration;

/**
 * Resolves configuration values at runtime from various sources:
 * - fixed: static value
 * - default: fallback if no other value
 * - env: from environment variables
 * - config: from BASE3 application configuration
 * - inherit: returns null (caller decides)
 * - random: picks a random value from a provided list
 * - uuid: generates a new UUIDv4
 */
class AgentConfigValueResolver implements IAgentConfigValueResolver {

	public function __construct(private readonly IConfiguration $configuration) {}

	/**
	 * Resolves a config entry into a final runtime value.
	 *
	 * @param array|string|null $config Configuration fragment (array or scalar)
	 * @return mixed Resolved value (or null)
	 */
	public function resolveValue(array|string|null $config): mixed {
		if (is_string($config)) return $config;
		if (!is_array($config)) return null;

		$mode = $config['mode'] ?? 'inherit';

		switch ($mode) {
			case 'fixed':
			case 'default':
				return $config['value'] ?? null;

			case 'env':
				return getenv((string)($config['value'] ?? '')) ?: null;

			case 'config':
				$section = $config['section'] ?? null;
				$key = $config['key'] ?? null;

				if (!$section || !$key) {
					throw new \RuntimeException("AgentConfigValueResolver: 'config' mode requires both 'section' and 'key'.");
				}

				$sectionData = $this->configuration->get($section);
				if (!is_array($sectionData)) {
					throw new \RuntimeException("AgentConfigValueResolver: Section '$section' not found or invalid.");
				}

				if (!array_key_exists($key, $sectionData)) {
					throw new \RuntimeException("AgentConfigValueResolver: Key '$key' not found in section '$section'.");
				}

				return $sectionData[$key];

			case 'random':
				$value = $config['value'] ?? null;
				if (is_array($value) && !empty($value)) {
					return $value[array_rand($value)];
				}
				return null;

			case 'uuid':
				return $this->generateUuidV4();

			case 'inherit':
			default:
				return null;
		}
	}

	/**
	 * Generates a UUID v4 string.
	 *
	 * @return string
	 */
	protected function generateUuidV4(): string {
		$data = random_bytes(16);
		$data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
		$data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant RFC 4122
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
}


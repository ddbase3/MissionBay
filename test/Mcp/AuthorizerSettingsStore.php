<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use Base3\Api\IRequest;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use CredentialFoundation\Api\ICredentialAccess;
use CredentialFoundation\Dto\CredentialAuthenticationResult;
use CredentialFoundation\Dto\CredentialIdentityResult;
use MissionBay\Mcp\McpProfileAuthorizer;
use MissionBay\Mcp\McpToolProfileRepository;
use PHPUnit\Framework\TestCase;

final class AuthorizerSettingsStore implements ISettingsStore {

	/** @param array<string,array<string,array<string,mixed>>> $data */
	public function __construct(private array $data) {}

	public function get(string $group, string $name, array $default = []): array {
		return $this->data[$group][$name] ?? $default;
	}

	public function set(string $group, string $name, array $settings): void {
		$this->data[$group][$name] = $settings;
	}

	public function has(string $group, string $name): bool {
		return isset($this->data[$group][$name]);
	}

	public function remove(string $group, string $name): void {
		unset($this->data[$group][$name]);
	}

	public function getGroup(string $group): array {
		return $this->data[$group] ?? [];
	}

	public function save(): void {}

	public function reload(): void {}
}

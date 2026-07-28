<?php declare(strict_types=1);

namespace MissionBay\Test\Display;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Display\AgentComponentPresetAdminDisplay;
use PHPUnit\Framework\TestCase;

final class AgentComponentPresetSecretTest extends TestCase {

	private const SECRET_VALUE_MARKER = '__missionbay_secret_configured__';

	public function testSensitiveSchemaFieldsAreRedactedBeforePresetRecordsAreReturned(): void {
		$display = $this->createDisplayWithoutConstructor();
		$result = $this->invokePrivate($display, 'redactSecretConfig', [
			'mcpclientagentresource',
			[
				'endpoint' => 'https://mcp.example.test/mcp',
				'token' => [
					'mode' => 'env',
					'name' => 'MCP_TOKEN'
				]
			]
		]);

		$this->assertSame('https://mcp.example.test/mcp', $result['endpoint']);
		$this->assertSame(self::SECRET_VALUE_MARKER, $result['token']);
	}

	public function testInternalSecretMarkerCannotBeStoredUnderAnUnrelatedField(): void {
		$display = $this->createDisplayWithoutConstructor();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid secret config marker');

		$this->invokePrivate($display, 'restoreSecretConfig', [
			'mcpclientagentresource',
			[
				'endpoint' => self::SECRET_VALUE_MARKER
			],
			'',
			'new-preset'
		]);
	}

	public function testSecretMarkerIsNotRestoredAfterResourceTypeChange(): void {
		$display = $this->createDisplayWithoutConstructor(new AgentComponentPresetSecretSettingsStore([
			'old-preset' => [
				'type' => 'differentresource',
				'config' => [
					'token' => 'old-secret'
				]
			]
		]));
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Secret config field must be entered');

		$this->invokePrivate($display, 'restoreSecretConfig', [
			'mcpclientagentresource',
			[
				'token' => self::SECRET_VALUE_MARKER
			],
			'old-preset',
			'new-preset'
		]);
	}

	public function testSecretMarkerRestoresStoredValueForSameResourceType(): void {
		$display = $this->createDisplayWithoutConstructor(new AgentComponentPresetSecretSettingsStore([
			'old-preset' => [
				'type' => 'mcpclientagentresource',
				'config' => [
					'token' => ['mode' => 'env', 'name' => 'MCP_TOKEN']
				]
			]
		]));

		$result = $this->invokePrivate($display, 'restoreSecretConfig', [
			'mcpclientagentresource',
			[
				'token' => self::SECRET_VALUE_MARKER
			],
			'old-preset',
			'new-preset'
		]);

		$this->assertSame(['mode' => 'env', 'name' => 'MCP_TOKEN'], $result['token']);
	}

	private function createDisplayWithoutConstructor(?ISettingsStore $settingsStore = null): AgentComponentPresetAdminDisplay {
		$reflection = new \ReflectionClass(AgentComponentPresetAdminDisplay::class);
		$display = $reflection->newInstanceWithoutConstructor();

		if($settingsStore instanceof ISettingsStore) {
			$settingsProperty = $reflection->getProperty('settingsStore');
			$settingsProperty->setAccessible(true);
			$settingsProperty->setValue($display, $settingsStore);
		}

		$property = $reflection->getProperty('resourceOptionsCache');
		$property->setAccessible(true);
		$property->setValue($display, [[
			'id' => 'mcpclientagentresource',
			'schema' => [
				'type' => 'object',
				'properties' => [
					'endpoint' => [
						'type' => 'string'
					],
					'token' => [
						'type' => 'string',
						'x-ui' => [
							'control' => 'password',
							'sensitive' => true
						]
					]
				]
			]
		]]);

		return $display;
	}

	private function invokePrivate(object $object, string $method, array $arguments): mixed {
		$reflection = new \ReflectionMethod($object, $method);
		$reflection->setAccessible(true);

		return $reflection->invokeArgs($object, $arguments);
	}
}

final class AgentComponentPresetSecretSettingsStore implements ISettingsStore {

	/** @param array<string,array<string,mixed>> $data */
	public function __construct(private array $data) {}

	public function get(string $group, string $name, array $default = []): array {
		return $this->data[$name] ?? $default;
	}

	public function set(string $group, string $name, array $settings): void {
		$this->data[$name] = $settings;
	}

	public function has(string $group, string $name): bool {
		return array_key_exists($name, $this->data);
	}

	public function remove(string $group, string $name): void {
		unset($this->data[$name]);
	}

	public function getGroup(string $group): array {
		return $this->data;
	}

	public function save(): void {}

	public function reload(): void {}
}

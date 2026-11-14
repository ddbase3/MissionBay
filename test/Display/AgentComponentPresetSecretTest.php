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

namespace Base3\MissionBay\Test\Display;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Display\AgentComponentPresetAdminDisplay;
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

require_once __DIR__ . '/AgentComponentPresetSecretSettingsStore.php';

<?php declare(strict_types=1);

namespace MissionBay\Test\Display;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Display\AgentComponentPresetAdminDisplay;
use PHPUnit\Framework\TestCase;

final class AgentComponentPresetValidationTest extends TestCase {

	public function testValidPresetDefinitionPassesSchemaAndDockValidation(): void {
		$display = $this->createDisplay();

		$this->invokeValidation($display, 'source-main', '', [
			'limit' => 2
		], [
			'target' => ['target-main']
		]);

		$this->addToAssertionCount(1);
	}

	public function testRequiredDockMustBeConfigured(): void {
		$display = $this->createDisplay();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Required dock has no target: target');

		$this->invokeValidation($display, 'source-main', '', ['limit' => 2], []);
	}

	public function testDockTargetMustImplementRequiredInterface(): void {
		$display = $this->createDisplay();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('does not implement Example\\Api\\ITarget');

		$this->invokeValidation($display, 'source-main', '', ['limit' => 2], [
			'target' => ['wrong-main']
		]);
	}

	public function testRenameCannotDockFormerPresetIdAsSelfReference(): void {
		$display = $this->createDisplay();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Preset cannot dock itself: source-renamed');

		$this->invokeValidation($display, 'source-renamed', 'source-main', ['limit' => 2], [
			'target' => ['source-main']
		]);
	}

	public function testSchemaMinimumIsValidatedAtPresetSaveBoundary(): void {
		$display = $this->createDisplay();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid config field "limit"');

		$this->invokeValidation($display, 'source-main', '', ['limit' => 0], [
			'target' => ['target-main']
		]);
	}

	public function testMalformedDockPayloadIsRejected(): void {
		$display = $this->createDisplay();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Dock targets must be a string or array.');

		$this->invokeValidation($display, 'source-main', '', ['limit' => 2], [
			'target' => 123
		]);
	}

	private function createDisplay(): AgentComponentPresetAdminDisplay {
		$reflection = new \ReflectionClass(AgentComponentPresetAdminDisplay::class);
		$display = $reflection->newInstanceWithoutConstructor();

		$settingsProperty = $reflection->getProperty('settingsStore');
		$settingsProperty->setAccessible(true);
		$settingsProperty->setValue($display, new AgentComponentPresetValidationSettingsStore([
			'target-main' => [
				'id' => 'target-main',
				'type' => 'targetresource',
				'enabled' => true
			],
			'wrong-main' => [
				'id' => 'wrong-main',
				'type' => 'wrongresource',
				'enabled' => true
			],
			'source-main' => [
				'id' => 'source-main',
				'type' => 'sourceresource',
				'enabled' => true
			]
		]));

		$optionsProperty = $reflection->getProperty('resourceOptionsCache');
		$optionsProperty->setAccessible(true);
		$optionsProperty->setValue($display, [
			[
				'id' => 'sourceresource',
				'interfaces' => [],
				'schema' => [
					'type' => 'object',
					'properties' => [
						'limit' => [
							'type' => 'integer',
							'minimum' => 1
						]
					],
					'required' => ['limit']
				],
				'docks' => [[
					'name' => 'target',
					'interface' => 'Example\\Api\\ITarget',
					'maxConnections' => 1,
					'required' => true
				]]
			],
			[
				'id' => 'targetresource',
				'interfaces' => ['Example\\Api\\ITarget'],
				'schema' => [],
				'docks' => []
			],
			[
				'id' => 'wrongresource',
				'interfaces' => ['Example\\Api\\IOther'],
				'schema' => [],
				'docks' => []
			]
		]);

		return $display;
	}

	/**
	 * @param array<string,mixed> $config
	 * @param array<string,mixed> $docks
	 */
	private function invokeValidation(
		AgentComponentPresetAdminDisplay $display,
		string $id,
		string $oldId,
		array $config,
		array $docks
	): void {
		$method = new \ReflectionMethod($display, 'validatePresetDefinition');
		$method->setAccessible(true);
		$method->invoke($display, $id, $oldId, 'sourceresource', $config, $docks);
	}
}

final class AgentComponentPresetValidationSettingsStore implements ISettingsStore {

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

<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Service\AgentComponentFlowBuilder;
use PHPUnit\Framework\TestCase;

final class AgentComponentFlowBuilderDeduplicationTest extends TestCase {

	public function testToolAndContextAttachmentsShareOnePresetResource(): void {
		$builder = new AgentComponentFlowBuilder($this->repository());
		$flow = $builder->build($this->baseFlow(), [[
			'preset' => 'user-prefs',
			'attach_as' => ['tool', 'context']
		]]);

		$baseResources = array_values(array_filter(
			$flow['resources'],
			static fn(array $resource): bool => str_starts_with((string)$resource['id'], 'preset_user_prefs')
		));
		$toolWrappers = array_values(array_filter(
			$flow['resources'],
			static fn(array $resource): bool => (string)($resource['type'] ?? '') === 'configuredagenttoolresource'
		));
		$memoryWrappers = array_values(array_filter(
			$flow['resources'],
			static fn(array $resource): bool => (string)($resource['type'] ?? '') === 'configuredagentmemoryresource'
		));

		$this->assertCount(1, $baseResources);
		$this->assertCount(1, $toolWrappers);
		$this->assertCount(0, $memoryWrappers);
		$this->assertSame($baseResources[0]['id'], $toolWrappers[0]['docks']['tool'][0]);
		$this->assertSame([$toolWrappers[0]['id']], $flow['nodes'][0]['docks']['tools']);
		$this->assertSame([$baseResources[0]['id']], $flow['nodes'][0]['docks']['contextcontributors']);
		$this->assertArrayNotHasKey('memory', $flow['nodes'][0]['docks']);
	}

	public function testConversationMemoryUsesDedicatedMemoryWrapperOnly(): void {
		$builder = new AgentComponentFlowBuilder($this->repository());
		$flow = $builder->build($this->baseFlow(), [[
			'preset' => 'session-main',
			'attach_as' => ['memory']
		]]);

		$memoryWrappers = array_values(array_filter(
			$flow['resources'],
			static fn(array $resource): bool => (string)($resource['type'] ?? '') === 'configuredagentmemoryresource'
		));

		$this->assertCount(1, $memoryWrappers);
		$this->assertSame([$memoryWrappers[0]['id']], $flow['nodes'][0]['docks']['memory']);
		$this->assertArrayNotHasKey('contextcontributors', $flow['nodes'][0]['docks']);
	}

	/** @return array<string,mixed> */
	private function baseFlow(): array {
		return [
			'nodes' => [[
				'id' => 'assistant',
				'type' => 'aiassistantnode'
			]],
			'resources' => [],
			'connections' => []
		];
	}

	private function repository(): IAgentComponentPresetRepository {
		return new class implements IAgentComponentPresetRepository {
			private array $presets = [
				'user-prefs' => [
					'id' => 'user-prefs',
					'type' => 'userprefsagentresource',
					'enabled' => true,
					'capabilities' => ['tool', 'context']
				],
				'session-main' => [
					'id' => 'session-main',
					'type' => 'sessionmemoryagentresource',
					'enabled' => true,
					'capabilities' => ['memory']
				]
			];
			public function getPresets(): array { return $this->presets; }
			public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
			public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
			public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
			public function removePreset(string $id): void { unset($this->presets[$id]); }
		};
	}
}

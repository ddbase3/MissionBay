<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Profile\AgentToolProfileResolver;
use PHPUnit\Framework\TestCase;

final class AgentToolProfileResolverTestDouble2 implements IAgentComponentPresetRepository  {
			private array $presets = [
				'tool-only' => [
					'id' => 'tool-only',
					'enabled' => true,
					'capabilities' => ['tool']
				],
				'user-prefs' => [
					'id' => 'user-prefs',
					'enabled' => true,
					'capabilities' => ['tool', 'context']
				],
				'tool-memory' => [
					'id' => 'tool-memory',
					'enabled' => true,
					'capabilities' => ['tool', 'memory']
				],
				'tool-context-memory' => [
					'id' => 'tool-context-memory',
					'enabled' => true,
					'capabilities' => ['tool', 'context', 'memory']
				]
			];
			public function getPresets(): array { return $this->presets; }
			public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
			public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
			public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
			public function removePreset(string $id): void { unset($this->presets[$id]); }
		}

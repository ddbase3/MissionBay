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

namespace Base3\MissionBay\Test\Profile;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Profile\AgentToolProfileResolver;
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

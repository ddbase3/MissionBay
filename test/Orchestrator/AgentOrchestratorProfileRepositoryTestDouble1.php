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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use PHPUnit\Framework\TestCase;

final class AgentOrchestratorProfileRepositoryTestDouble1 implements ISettingsStore {
			public function __construct(private array $groups) {}
			public function get(string $group, string $name, array $default = []): array { return $this->groups[$group][$name] ?? $default; }
			public function set(string $group, string $name, array $settings): void { $this->groups[$group][$name] = $settings; }
			public function has(string $group, string $name): bool { return isset($this->groups[$group][$name]); }
			public function remove(string $group, string $name): void { unset($this->groups[$group][$name]); }
			public function getGroup(string $group): array { return $this->groups[$group] ?? []; }
			public function save(): void {}
			public function reload(): void {}
		}

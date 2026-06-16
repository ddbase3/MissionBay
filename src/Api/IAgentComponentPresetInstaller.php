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

namespace MissionBay\Api;

/**
 * IAgentComponentPresetInstaller
 *
 * Provides initial component presets for installations that want to enable
 * the agent component system without manually creating SettingsStore rows.
 */
interface IAgentComponentPresetInstaller {

	/**
	 * Installs default presets.
	 *
	 * @param bool $overwrite Replace existing presets if true.
	 * @return array<string,mixed> Install report.
	 */
	public function installDefaults(bool $overwrite = false): array;

	/**
	 * Returns the default presets indexed by preset id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function getDefaultPresets(): array;

	/**
	 * Returns a minimal default agent_components selection for testing.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getDefaultAgentComponents(): array;
}

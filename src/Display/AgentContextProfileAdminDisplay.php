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

namespace Base3\MissionBay\Display;

/**
 * Administrates profiles of already configured context-contributor presets.
 */
final class AgentContextProfileAdminDisplay extends AgentMemoryProfileAdminDisplay {

	public static function getName(): string {
		return 'agentcontextprofileadmindisplay';
	}

	protected function settingsTabFeatureName(): string {
		return \Base3\MissionBay\SettingsTab\AgentcontextprofileadminSettingsTab::getName();
	}

	protected function profileKind(): string {
		return 'context';
	}

	protected function profileTitle(): string {
		return 'Context Profiles';
	}

	protected function profileDescription(): string {
		return 'Context profiles select already configured context-contributor component presets. They add system context but do not store conversation history.';
	}

	protected function presetLabel(): string {
		return 'Context-contributor presets';
	}

	protected function emptyPresetText(): string {
		return 'No context-contributor component presets are available.';
	}
}

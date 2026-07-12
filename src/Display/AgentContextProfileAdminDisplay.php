<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Display;

/**
 * Administrates profiles of already configured context-contributor presets.
 */
final class AgentContextProfileAdminDisplay extends AgentMemoryProfileAdminDisplay {

	public static function getName(): string {
		return 'agentcontextprofileadmindisplay';
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

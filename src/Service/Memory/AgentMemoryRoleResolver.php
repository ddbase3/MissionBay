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

namespace Base3\MissionBay\Service\Memory;

use Base3\AssistantFoundation\Api\IAgentContextContributor;
use Base3\AssistantFoundation\Api\IAgentConversationMemory;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\MissionBay\Api\IAgentMemoryRoleResolver;

final class AgentMemoryRoleResolver implements IAgentMemoryRoleResolver {

	public function isConversationMemory(IAgentMemory $memory): bool {
		return $memory instanceof IAgentConversationMemory;
	}

	public function isContextContributor(IAgentMemory $memory): bool {
		return $memory instanceof IAgentContextContributor;
	}

	public function getRoles(IAgentMemory $memory): array {
		$roles = [];
		if ($this->isConversationMemory($memory)) {
			$roles[] = 'conversation-memory';
		}
		if ($this->isContextContributor($memory)) {
			$roles[] = 'context-contributor';
		}

		return $roles;
	}
}

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

namespace MissionBay\Service\Memory;

use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Api\IAgentMemory;
use MissionBay\Api\IAgentMemoryRoleProvider;
use MissionBay\Api\IAgentMemoryRoleResolver;

final class AgentMemoryRoleResolver implements IAgentMemoryRoleResolver {

	public function isConversationMemory(IAgentMemory $memory): bool {
		if ($memory instanceof IAgentMemoryRoleProvider) {
			return $memory->providesConversationMemory();
		}

		if ($memory instanceof IAgentConversationMemory) {
			return true;
		}

		return !($memory instanceof IAgentContextContributor);
	}

	public function isContextContributor(IAgentMemory $memory): bool {
		if ($memory instanceof IAgentMemoryRoleProvider) {
			return $memory->providesContextContributions();
		}

		return $memory instanceof IAgentContextContributor;
	}

	public function isLegacyMemory(IAgentMemory $memory): bool {
		if ($memory instanceof IAgentMemoryRoleProvider) {
			return $memory->usesLegacyMemorySemantics();
		}

		return !($memory instanceof IAgentConversationMemory)
			&& !($memory instanceof IAgentContextContributor);
	}

	public function getRoles(IAgentMemory $memory): array {
		$roles = [];

		if ($this->isConversationMemory($memory)) {
			$roles[] = 'conversation-memory';
		}

		if ($this->isContextContributor($memory)) {
			$roles[] = 'context-contributor';
		}

		if ($this->isLegacyMemory($memory)) {
			$roles[] = 'legacy-memory';
		}

		return $roles;
	}
}

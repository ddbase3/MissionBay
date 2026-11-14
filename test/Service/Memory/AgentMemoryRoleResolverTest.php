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

namespace Base3\MissionBay\Test\Service\Memory;

use Base3\AssistantFoundation\Api\IAgentConversationMemory;
use Base3\AssistantFoundation\Api\IAgentContextContributor;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\MissionBay\Service\Memory\AgentMemoryRoleResolver;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentMemoryRoleTestContributingMemory.php';

final class AgentMemoryRoleResolverTest extends TestCase {

	public function testPlainMemoryHasNoSpecializedRoles(): void {
		$memory = $this->createStub(IAgentMemory::class);
		$resolver = new AgentMemoryRoleResolver();
		$this->assertFalse($resolver->isConversationMemory($memory));
		$this->assertFalse($resolver->isContextContributor($memory));
		$this->assertSame([], $resolver->getRoles($memory));
	}

	public function testConversationMemoryIsDetectedByItsFoundationContract(): void {
		$memory = $this->createStub(IAgentConversationMemory::class);
		$resolver = new AgentMemoryRoleResolver();
		$this->assertTrue($resolver->isConversationMemory($memory));
		$this->assertSame(['conversation-memory'], $resolver->getRoles($memory));
	}

	public function testContextContributorIsDetectedOnlyWhenMemoryImplementsBothContracts(): void {
		$memory = new AgentMemoryRoleTestContributingMemory();
		$resolver = new AgentMemoryRoleResolver();
		$this->assertFalse($resolver->isConversationMemory($memory));
		$this->assertTrue($resolver->isContextContributor($memory));
		$this->assertSame(['context-contributor'], $resolver->getRoles($memory));
	}
}

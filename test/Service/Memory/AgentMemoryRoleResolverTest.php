<?php declare(strict_types=1);

namespace MissionBay\Test\Service\Memory;

use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentMemory;
use MissionBay\Service\Memory\AgentMemoryRoleResolver;
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

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

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Api\IAgentConversationMemory;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\AssistantFoundation\Dto\AgentConversation;
use Base3\AssistantFoundation\Dto\AgentConversationScope;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\ConfiguredAgentMemoryResource;
use PHPUnit\Framework\TestCase;

final class ConfiguredAgentMemoryResourceRoleTest extends TestCase {

	public function testConversationMemoryIsDelegated(): void {
		$underlying = new ConfiguredMemoryTestStub();
		$wrapper = new ConfiguredAgentMemoryResource($this->resolver(), 'configured-conversation');
		$wrapper->init(['memory' => [$underlying]], new AgentContext());

		$scope = new AgentConversationScope('user', '42', 'chatbot-main', 'conversation-1');
		$wrapper->bindConversationScope($scope);

		$this->assertSame($scope, $underlying->scope);
		$this->assertSame([['role' => 'user', 'content' => 'Stored']], $wrapper->loadNodeHistory('assistant'));
		$wrapper->appendNodeHistory('assistant', ['role' => 'assistant', 'content' => 'Reply']);
		$this->assertSame(1, $underlying->writes);
	}

	public function testReadAndWriteSwitchesRestrictConversationAccess(): void {
		$underlying = new ConfiguredMemoryTestStub();
		$wrapper = new ConfiguredAgentMemoryResource($this->resolver(), 'configured-restricted');
		$wrapper->setConfig(['priority' => 4, 'read_enabled' => false, 'write_enabled' => false]);
		$wrapper->init(['memory' => [$underlying]], new AgentContext());

		$this->assertSame(4, $wrapper->getPriority());
		$this->assertFalse($wrapper->isReadEnabled());
		$this->assertFalse($wrapper->isWriteEnabled());
		$this->assertSame([], $wrapper->loadNodeHistory('assistant'));
		$wrapper->appendNodeHistory('assistant', ['role' => 'assistant', 'content' => 'Skipped']);
		$this->assertSame(0, $underlying->writes);
		$this->expectException(\RuntimeException::class);
		$wrapper->createConversation();
	}

	public function testLifecycleDeletionBypassesReadWriteSwitchesWithoutSilentlySkipping(): void {
		$underlying = new ConfiguredMemoryTestStub();
		$wrapper = new ConfiguredAgentMemoryResource($this->resolver(), 'configured-archival');
		$wrapper->setConfig(['read_enabled' => false, 'write_enabled' => false]);
		$wrapper->init(['memory' => [$underlying]], new AgentContext());
		$wrapper->deleteConversationsByOwner('user', '42');
		$wrapper->deleteConversationsByChannel('chatbot-main');
		$this->assertSame([['user', '42']], $underlying->deletedOwners);
		$this->assertSame(['chatbot-main'], $underlying->deletedChannels);
	}

	public function testLifecycleDeletionWithoutMemoryFailsExplicitly(): void {
		$wrapper = new ConfiguredAgentMemoryResource($this->resolver(), 'configured-missing');
		$wrapper->setConfig(['enabled' => false]);
		$wrapper->init([], new AgentContext());
		$this->expectException(\RuntimeException::class);
		$wrapper->deleteConversationsByOwner('user', '42');
	}

	public function testGenericAgentMemoryIsRejected(): void {
		$underlying = new ConfiguredAgentMemoryResourceRoleTestDouble1();

		$wrapper = new ConfiguredAgentMemoryResource($this->resolver(), 'configured-invalid');

		$this->expectException(\RuntimeException::class);
		$wrapper->init(['memory' => [$underlying]], new AgentContext());
	}

	private function resolver(): IAgentConfigValueResolver {
		return new ConfiguredAgentMemoryResourceRoleTestDouble2();
	}
}

require_once __DIR__ . '/ConfiguredMemoryTestStub.php';

require_once __DIR__ . '/ConfiguredAgentMemoryResourceRoleTestDouble1.php';

require_once __DIR__ . '/ConfiguredAgentMemoryResourceRoleTestDouble2.php';

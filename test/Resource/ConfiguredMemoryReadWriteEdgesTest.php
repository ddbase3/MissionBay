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

use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\ConfiguredAgentMemoryResource;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ConfiguredMemoryTestStub.php';

final class ConfiguredMemoryReadWriteEdgesTest extends TestCase {

	public function testUnavailableMemoryDoesNotReadConversationData(): void {
		$wrapper = new ConfiguredAgentMemoryResource($this->resolver());
		$wrapper->setConfig(['enabled' => false]);
		$wrapper->init([], new AgentContext());
		$this->assertSame([], $wrapper->listConversations());
		$this->assertNull($wrapper->getActiveConversation());
		$this->assertNull($wrapper->getConversation('secret'));
		$this->assertSame([], $wrapper->loadNodeHistory('node'));
	}

	public function testUnavailableMemoryNeverClaimsWriteSuccess(): void {
		$wrapper = new ConfiguredAgentMemoryResource($this->resolver());
		$wrapper->setConfig(['enabled' => false]);
		$wrapper->init([], new AgentContext());
		$this->assertFalse($wrapper->setFeedback('node', 'message', 'good'));
		$this->assertFalse($wrapper->updateNodeHistoryMessageMetadata('node', 'message', ['a' => 1]));
		$wrapper->appendNodeHistory('node', ['role' => 'user']);
		$wrapper->resetNodeHistory('node');
		$this->assertFalse($wrapper->isWriteEnabled());
	}

	public function testAbsentMemoryCannotDeleteConversation(): void {
		$wrapper = new ConfiguredAgentMemoryResource($this->resolver());
		$wrapper->setConfig(['enabled' => false]);
		$wrapper->init([], new AgentContext());
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('not writable');
		$wrapper->deleteConversation('c');
	}

	public function testWrappedMemoryProvidesPriorityUnlessOverridden(): void {
		$wrapper = new ConfiguredAgentMemoryResource($this->resolver());
		$wrapper->init(['memory' => [new ConfiguredMemoryTestStub()]], new AgentContext());
		$this->assertSame(15, $wrapper->getPriority());
		$wrapper->setConfig(['priority' => 7]);
		$this->assertSame(7, $wrapper->getPriority());
	}

	public function testReadDisabledMemoryMayStillBeBoundWithoutExposingMessages(): void {
		$wrapper = new ConfiguredAgentMemoryResource($this->resolver());
		$wrapper->setConfig(['read_enabled' => 'off']);
		$wrapper->init(['memory' => [new ConfiguredMemoryTestStub()]], new AgentContext());
		$this->assertFalse($wrapper->isReadEnabled());
		$this->assertSame([], $wrapper->loadNodeHistory('node'));
		$this->assertSame([], $wrapper->listConversations());
		$this->assertTrue($wrapper->isWriteEnabled());
	}

	private function resolver(): IAgentConfigValueResolver {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(static fn(mixed $value): mixed => $value);
		return $resolver;
	}
}

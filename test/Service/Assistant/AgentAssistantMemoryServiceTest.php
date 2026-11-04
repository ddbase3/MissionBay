<?php declare(strict_types=1);

namespace MissionBay\Test\Service\Assistant;

use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Api\IAgentMemory;
use MissionBay\Api\IAgentAssistantMessageFactory;
use MissionBay\Api\IAgentMemoryRoleResolver;
use MissionBay\Service\Assistant\AgentAssistantMemoryService;
use PHPUnit\Framework\TestCase;

final class AgentAssistantMemoryServiceTest extends TestCase {

	public function testSortsConversationMemoriesByPriorityAndDeduplicatesInstances(): void {
		$first = $this->createStub(IAgentMemory::class);
		$first->method('getPriority')->willReturn(20);
		$second = $this->createStub(IAgentMemory::class);
		$second->method('getPriority')->willReturn(1);
		$result = $this->service()->sortMemories([$first, new \stdClass(), $second, $first]);
		$this->assertSame([$second, $first], $result);
	}

	public function testInitialMessagesKeepOnlyTheLastTwentyVisibleEntries(): void {
		$history = [];
		for ($i = 0; $i < 30; $i++) {
			$history[] = ['role' => 'user', 'content' => 'message-' . $i];
		}
		$memory = $this->createStub(IAgentMemory::class);
		$memory->method('loadNodeHistory')->willReturn($history);
		$messages = $this->service()->buildInitialMessages('system', [$memory], 'node');
		$this->assertCount(21, $messages);
		$this->assertSame(['role' => 'system', 'content' => 'system'], $messages[0]);
		$this->assertSame('message-10', $messages[1]['content']);
		$this->assertSame('message-29', $messages[20]['content']);
	}

	public function testInvisibleEntriesAreExcludedBeforeTruncation(): void {
		$memory = $this->createStub(IAgentMemory::class);
		$memory->method('loadNodeHistory')->willReturn([
			['role' => 'tool', 'content' => 'private'],
			['role' => 'user', 'content' => 'visible']
		]);
		$factory = $this->createStub(IAgentAssistantMessageFactory::class);
		$factory->method('isVisibleHistoryEntry')->willReturnCallback(static fn(mixed $entry): bool => is_array($entry) && ($entry['role'] ?? '') === 'user');
		$messages = $this->service(messageFactory: $factory)->buildInitialMessages('instructions', [$memory], 'node');
		$this->assertSame([
			['role' => 'system', 'content' => 'instructions'],
			['role' => 'user', 'content' => 'visible']
		], $messages);
	}

	public function testFailingMemoryReadDoesNotHideSystemInstructions(): void {
		$memory = $this->createStub(IAgentMemory::class);
		$memory->method('loadNodeHistory')->willThrowException(new \RuntimeException('not available'));
		$this->assertSame([['role' => 'system', 'content' => 'instruction']], $this->service()->buildInitialMessages('instruction', [$memory], 'node'));
	}

	public function testAppendVisibleMessageIsBestEffortAndUsesCorrectNode(): void {
		$memory = $this->createMock(IAgentMemory::class);
		$entry = ['role' => 'user', 'content' => 'hello'];
		$memory->expects($this->once())->method('appendNodeHistory')->with('node-5', $entry);
		$this->service()->appendVisibleMessage([$memory], 'node-5', $entry);
		$this->addToAssertionCount(1);
	}

	public function testFailedAppendDoesNotThrowOrPreventSubsequentMemory(): void {
		$failing = $this->createStub(IAgentMemory::class);
		$failing->method('appendNodeHistory')->willThrowException(new \RuntimeException('storage failure'));
		$working = $this->createMock(IAgentMemory::class);
		$working->expects($this->once())->method('appendNodeHistory')->with('node', ['content' => 'text']);
		$this->service()->appendVisibleMessage([$failing, $working], 'node', ['content' => 'text']);
	}

	public function testMetadataUpdatesAggregateSuccessfulConversationMemories(): void {
		$one = $this->createStub(IAgentConversationMemory::class);
		$one->method('updateNodeHistoryMessageMetadata')->willReturn(false);
		$two = $this->createStub(IAgentConversationMemory::class);
		$two->method('updateNodeHistoryMessageMetadata')->willReturn(true);
		$result = $this->service()->updateVisibleMessageMetadata([$one, $two], 'node', 'id', ['rating' => 2]);
		$this->assertTrue($result);
	}

	public function testNonConversationMemoryDoesNotAcceptMetadataUpdates(): void {
		$memory = $this->createStub(IAgentMemory::class);
		$this->assertFalse($this->service()->updateVisibleMessageMetadata([$memory], 'node', 'id', []));
	}

	public function testRoleDeniedMemoryIsNotReadOrWritten(): void {
		$memory = $this->createMock(IAgentMemory::class);
		$memory->expects($this->never())->method('loadNodeHistory');
		$memory->expects($this->never())->method('appendNodeHistory');
		$roleResolver = $this->createStub(IAgentMemoryRoleResolver::class);
		$roleResolver->method('isConversationMemory')->willReturn(false);
		$service = $this->service(roleResolver: $roleResolver);
		$this->assertSame([['role' => 'system', 'content' => 'system']], $service->buildInitialMessages('system', [$memory], 'node'));
		$service->appendVisibleMessage([$memory], 'node', ['role' => 'user']);
	}

	private function service(
		?IAgentAssistantMessageFactory $messageFactory = null,
		?IAgentMemoryRoleResolver $roleResolver = null
	): AgentAssistantMemoryService {
		if ($messageFactory === null) {
			$messageFactory = $this->createStub(IAgentAssistantMessageFactory::class);
			$messageFactory->method('isVisibleHistoryEntry')->willReturn(true);
		}
		if ($roleResolver === null) {
			$roleResolver = $this->createStub(IAgentMemoryRoleResolver::class);
			$roleResolver->method('isConversationMemory')->willReturn(true);
		}
		return new AgentAssistantMemoryService($messageFactory, $roleResolver);
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Api\IAgentMemory;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\ConfiguredAgentMemoryResource;
use PHPUnit\Framework\TestCase;

final class ConfiguredAgentMemoryResourceRoleTest extends TestCase {

	public function testConversationMemoryIsDelegated(): void {
		$underlying = new class implements IAgentConversationMemory {
			public int $writes = 0;
			public static function getName(): string { return 'wrappedconversation'; }
			public function loadNodeHistory(string $nodeId): array { return [['role' => 'user', 'content' => 'Stored']]; }
			public function appendNodeHistory(string $nodeId, array $message): void { $this->writes++; }
			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return true; }
			public function resetNodeHistory(string $nodeId): void {}
			public function getPriority(): int { return 15; }
		};

		$wrapper = new ConfiguredAgentMemoryResource($this->resolver(), 'configured-conversation');
		$wrapper->init(['memory' => [$underlying]], new AgentContext());

		$this->assertTrue($wrapper->providesConversationMemory());
		$this->assertFalse($wrapper->providesContextContributions());
		$this->assertSame('conversation-memory', $wrapper->getConfiguredRole());
		$this->assertSame([['role' => 'user', 'content' => 'Stored']], $wrapper->loadNodeHistory('assistant'));
		$wrapper->appendNodeHistory('assistant', ['role' => 'assistant', 'content' => 'Reply']);
		$this->assertSame(1, $underlying->writes);
	}

	public function testReadAndWriteSwitchesRestrictConversationAccess(): void {
		$underlying = new class implements IAgentConversationMemory {
			public int $writes = 0;
			public static function getName(): string { return 'wrappedrestricted'; }
			public function loadNodeHistory(string $nodeId): array { return [['role' => 'user', 'content' => 'Stored']]; }
			public function appendNodeHistory(string $nodeId, array $message): void { $this->writes++; }
			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return true; }
			public function resetNodeHistory(string $nodeId): void {}
			public function getPriority(): int { return 12; }
		};

		$wrapper = new ConfiguredAgentMemoryResource($this->resolver(), 'configured-restricted');
		$wrapper->setConfig(['priority' => 4, 'read_enabled' => false, 'write_enabled' => false]);
		$wrapper->init(['memory' => [$underlying]], new AgentContext());

		$this->assertSame(4, $wrapper->getPriority());
		$this->assertFalse($wrapper->isReadEnabled());
		$this->assertFalse($wrapper->isWriteEnabled());
		$this->assertSame([], $wrapper->loadNodeHistory('assistant'));
		$wrapper->appendNodeHistory('assistant', ['role' => 'assistant', 'content' => 'Skipped']);
		$this->assertSame(0, $underlying->writes);
	}

	public function testLegacyMemoryRemainsConversationCompatible(): void {
		$underlying = new class implements IAgentMemory {
			public static function getName(): string { return 'wrappedlegacy'; }
			public function loadNodeHistory(string $nodeId): array { return []; }
			public function appendNodeHistory(string $nodeId, array $message): void {}
			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
			public function resetNodeHistory(string $nodeId): void {}
			public function getPriority(): int { return 100; }
		};

		$wrapper = new ConfiguredAgentMemoryResource($this->resolver(), 'configured-legacy');
		$wrapper->init(['memory' => [$underlying]], new AgentContext());

		$this->assertTrue($wrapper->providesConversationMemory());
		$this->assertFalse($wrapper->providesContextContributions());
		$this->assertTrue($wrapper->usesLegacyMemorySemantics());
	}

	private function resolver(): IAgentConfigValueResolver {
		return new class implements IAgentConfigValueResolver {
			public function resolveValue(array|string|int|float|bool|null $config): mixed { return $config; }
		};
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Test\Service\Memory;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentMemory;

final class AgentMemoryRoleTestContributingMemory implements IAgentMemory, IAgentContextContributor {

	public static function getName(): string {
		return 'agentmemoryroletestcontributingmemory';
	}

	public function id(): string {
		return 'test-memory';
	}

	public function getPriority(): int {
		return 0;
	}

	public function loadNodeHistory(string $nodeId): array {
		return [];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		return false;
	}

	public function resetNodeHistory(string $nodeId): void {}

	public function contribute(IAgentContext $context): iterable {
		return [];
	}
}

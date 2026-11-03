<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\CurrentTimeAgentTool;
use MissionBay\Api\IAgentTool;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;

class CurrentTimeAgentMemoryStub implements IAgentMemory {

	public static function getName(): string {
		return 'currenttimeagentmemorystub';
	}

	public function loadNodeHistory(string $nodeId): array { return []; }
	public function appendNodeHistory(string $nodeId, array $message): void {}
	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
	public function resetNodeHistory(string $nodeId): void {}
	public function getPriority(): int { return 0; }

}

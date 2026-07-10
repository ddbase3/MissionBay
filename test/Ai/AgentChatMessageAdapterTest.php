<?php declare(strict_types=1);

namespace MissionBay\Test\Ai;

use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Ai\AgentChatMessageAdapter;
use PHPUnit\Framework\TestCase;

final class AgentChatMessageAdapterTest extends TestCase {

	public function testBuildsCurrentMissionBayToolMessageWithoutLeakingItIntoFoundation(): void {
		$result = new AiChatResult(
			'',
			[new AiToolCall('call-1', 'lookup', ['query' => 'BASE3'])],
			new AiResultMetadata('chat')
		);

		$message = AgentChatMessageAdapter::assistantMessage($result);

		$this->assertSame('assistant', $message['role']);
		$this->assertSame('call-1', $message['tool_calls'][0]['id']);
		$this->assertSame('lookup', $message['tool_calls'][0]['function']['name']);
		$this->assertSame(
			['query' => 'BASE3'],
			json_decode($message['tool_calls'][0]['function']['arguments'], true)
		);
	}
}

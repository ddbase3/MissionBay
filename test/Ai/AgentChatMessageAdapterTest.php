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

namespace Base3\MissionBay\Test\Ai;

use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Ai\AgentChatMessageAdapter;
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

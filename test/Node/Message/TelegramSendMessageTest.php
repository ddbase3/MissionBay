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

namespace Base3\MissionBay\Test\Node\Message;

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Message\TelegramSendMessage;
use PHPUnit\Framework\TestCase;

final class TelegramSendMessageTest extends TestCase {

	public function testIncompleteRequestReturnsErrorWithoutNetwork(): void {
		$node = new TelegramSendMessage('send');
		$context = new AgentContext();
		foreach ([[], ['bot_token' => 'x', 'chat_id' => 'y'], ['bot_token' => 'x', 'message' => 'text'], ['chat_id' => 'y', 'message' => 'text']] as $input) {
			$this->assertStringContainsString('Missing required input', $node->execute($input, [], $context)['error']);
		}
	}

	public function testMessagePortContractDoesNotRequireLogger(): void {
		$node = new TelegramSendMessage();
		$this->assertSame(['bot_token', 'chat_id', 'message'], array_map(static fn($port) => $port->name, $node->getInputDefinitions()));
		$this->assertSame(['message_id', 'error'], array_map(static fn($port) => $port->name, $node->getOutputDefinitions()));
		$this->assertSame('logger', $node->getDockDefinitions()[0]->name);
	}
}

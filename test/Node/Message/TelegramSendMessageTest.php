<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Message;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Message\TelegramSendMessage;
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

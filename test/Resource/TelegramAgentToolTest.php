<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\TelegramAgentTool;
use PHPUnit\Framework\TestCase;

final class TelegramAgentToolTest extends TestCase {

	public function testWrongFunctionNameIsRejectedBeforeAnyExternalCall(): void {
		$tool = new TelegramAgentTool($this->createStub(IAgentConfigValueResolver::class));
		$this->expectException(\InvalidArgumentException::class);
		$tool->callTool('unrelated', ['message' => 'test'], new AgentContext());
	}

	public function testMissingMessageDoesNotContactTelegram(): void {
		$tool = new TelegramAgentTool($this->createStub(IAgentConfigValueResolver::class));
		$this->assertSame(['error' => 'Missing required parameter: message'], $tool->callTool('send_telegram_message', [], new AgentContext()));
		$this->assertSame(['error' => 'Missing required parameter: message'], $tool->callTool('send_telegram_message', ['message' => ''], new AgentContext()));
	}

	public function testUnresolvedCredentialsReturnErrorWithoutNetwork(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturn(null);
		$tool = new TelegramAgentTool($resolver);
		$tool->setConfig(['bottoken' => 'ref', 'chatid' => 'ref']);
		$this->assertSame(['error' => 'Telegram configuration (bottoken/chatid) not resolved'], $tool->callTool('send_telegram_message', ['message' => 'safe'], new AgentContext()));
	}

	public function testToolDefinitionRequiresMessageOnly(): void {
		$tool = new TelegramAgentTool($this->createStub(IAgentConfigValueResolver::class));
		$definition = $tool->getToolDefinitions()[0]['function'];
		$this->assertSame('send_telegram_message', $definition['name']);
		$this->assertSame(['message'], $definition['parameters']['required']);
	}
}

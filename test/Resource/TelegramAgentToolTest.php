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
use Base3\MissionBay\Resource\TelegramAgentTool;
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

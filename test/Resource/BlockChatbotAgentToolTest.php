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

use PHPUnit\Framework\TestCase;
use Base3\MissionBay\Resource\BlockChatbotAgentTool;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Api\IAgentTool;

class BlockChatbotAgentToolTest extends TestCase {

	private bool $handlerInstalled = false;

	protected function setUp(): void {
		@ini_set('session.use_cookies', '0');
		@ini_set('session.use_only_cookies', '0');
		@ini_set('session.cache_limiter', '');

		set_error_handler(function (int $errno, string $errstr): bool {
			if ($errno === E_WARNING && str_contains($errstr, 'session_start(): Session cannot be started after headers have already been sent')) {
				return true;
			}
			return false;
		});

		$this->handlerInstalled = true;
	}

	protected function tearDown(): void {
		if ($this->handlerInstalled) {
			restore_error_handler();
			$this->handlerInstalled = false;
		}

		if (session_status() === PHP_SESSION_ACTIVE) {
			unset($_SESSION['chatblocker']);
			session_write_close();
		}
		$_SESSION = [];

		parent::tearDown();
	}

	public function testImplementsAgentToolInterface(): void {
		$tool = new BlockChatbotAgentTool('id1');
		$this->assertInstanceOf(IAgentTool::class, $tool);
	}

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('blockchatbotagenttool', BlockChatbotAgentTool::getName());
	}

	public function testGetDescriptionReturnsExpectedText(): void {
		$tool = new BlockChatbotAgentTool('id1');
		$desc = $tool->getDescription();

		$this->assertIsString($desc);
		$this->assertStringContainsString('$_SESSION["chatblocker"]', $desc);
	}

	public function testGetToolDefinitionsReturnsOpenAiCompatibleDefinition(): void {
		$tool = new BlockChatbotAgentTool('id1');

		$defs = $tool->getToolDefinitions();
		$this->assertIsArray($defs);
		$this->assertCount(1, $defs);

		$def = $defs[0];
		$this->assertSame('function', $def['type']);
		$this->assertSame('Chatbot Blocking', $def['label']);
		$this->assertSame('block_chatbot', $def['function']['name']);
	}

	public function testCallToolThrowsForUnsupportedToolName(): void {
		$tool = new BlockChatbotAgentTool('id1');
		$context = $this->createStub(IAgentContext::class);

		$this->expectException(\InvalidArgumentException::class);

		$tool->callTool('nope', [], $context);
	}

	public function testCallToolStartsSessionIfNeededAndSetsChatblocker(): void {
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
		$_SESSION = [];

		$tool = new BlockChatbotAgentTool('id1');
		$context = $this->createStub(IAgentContext::class);

		$result = $tool->callTool('block_chatbot', [], $context);

		$this->assertSame('block', $_SESSION['chatblocker'] ?? null);
		$this->assertSame('ok', $result['status']);
	}

	public function testCallToolDoesNotRestartSessionIfAlreadyActive(): void {
		if (session_status() !== PHP_SESSION_ACTIVE) {
			@session_start();
		}
		unset($_SESSION['chatblocker']);

		$tool = new BlockChatbotAgentTool('id1');
		$context = $this->createStub(IAgentContext::class);

		$result = $tool->callTool('block_chatbot', [], $context);

		$this->assertSame('block', $_SESSION['chatblocker'] ?? null);
		$this->assertSame('ok', $result['status']);
	}

}

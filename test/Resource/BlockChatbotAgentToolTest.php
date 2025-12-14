<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\BlockChatbotAgentTool;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentTool;

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

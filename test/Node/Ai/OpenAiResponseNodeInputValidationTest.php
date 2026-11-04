<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Ai;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Node\Ai\OpenAiResponseNode;
use PHPUnit\Framework\TestCase;

final class OpenAiResponseNodeInputValidationTest extends TestCase {

	public function testMissingMessagesReturnStructuredError(): void {
		$result = $this->node()->execute([], [], $this->context());
		$this->assertStringContainsString('Input "messages" must be an array', $result['error']);
	}

	public function testStringMessagesAreRejectedBeforeMemoryAccess(): void {
		$context = $this->createMock(IAgentContext::class);
		$context->expects($this->never())->method('getMemory');
		$result = $this->node()->execute(['messages' => 'invalid', 'apikey' => 'fake'], [], $context);
		$this->assertStringContainsString('Input "messages" must be an array', $result['error']);
	}

	public function testMissingKeyIsRejectedBeforeMemoryAccess(): void {
		$context = $this->createMock(IAgentContext::class);
		$context->expects($this->never())->method('getMemory');
		$result = $this->node()->execute(['messages' => [['body' => 'hello']]], [], $context);
		$this->assertStringContainsString('Missing OpenAI API key input', $result['error']);
	}

	public function testEmptyApiKeyIsRejected(): void {
		$result = $this->node()->execute(['messages' => [], 'apikey' => ''], [], $this->context());
		$this->assertArrayHasKey('error', $result);
	}

	public function testNonArrayMessagesTakePrecedenceOverMissingKey(): void {
		$result = $this->node()->execute(['messages' => false], [], $this->context());
		$this->assertStringContainsString('must be an array', $result['error']);
	}

	public function testInputPortsRequireMessagesAndApiKey(): void {
		$ports = $this->node()->getInputDefinitions();
		$this->assertSame(['messages', 'model', 'apikey'], array_map(static fn($port): string => $port->name, $ports));
		$this->assertTrue($ports[0]->required);
		$this->assertFalse($ports[1]->required);
		$this->assertTrue($ports[2]->required);
	}

	private function node(): OpenAiResponseNode {
		return new OpenAiResponseNode('openai-response-unit');
	}

	private function context(): IAgentContext {
		return $this->createStub(IAgentContext::class);
	}
}

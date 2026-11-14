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

namespace Base3\MissionBay\Test\Node\Ai;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Node\Ai\OpenAiResponseNode;
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

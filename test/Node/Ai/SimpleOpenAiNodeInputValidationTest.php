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
use Base3\MissionBay\Node\Ai\SimpleOpenAiNode;
use PHPUnit\Framework\TestCase;

final class SimpleOpenAiNodeInputValidationTest extends TestCase {

	public function testMissingPromptAndKeyReturnsError(): void {
		$result = $this->node()->execute([], [], $this->context());
		$this->assertStringContainsString('Missing OpenAI API key or prompt', $result['error']);
	}

	public function testMissingKeyDoesNotAccessAgentMemory(): void {
		$context = $this->createMock(IAgentContext::class);
		$context->expects($this->never())->method('getMemory');
		$result = $this->node()->execute(['prompt' => 'question'], [], $context);
		$this->assertStringContainsString('Missing OpenAI API key or prompt', $result['error']);
	}

	public function testMissingPromptDoesNotAccessAgentMemory(): void {
		$context = $this->createMock(IAgentContext::class);
		$context->expects($this->never())->method('getMemory');
		$result = $this->node()->execute(['apikey' => 'fake-token'], [], $context);
		$this->assertStringContainsString('Missing OpenAI API key or prompt', $result['error']);
	}

	public function testEmptyPromptRejectsBeforeRequest(): void {
		$result = $this->node()->execute(['prompt' => '', 'apikey' => 'fake-token'], [], $this->context());
		$this->assertArrayHasKey('error', $result);
	}

	public function testInputPortDefinitionsRequirePromptAndKey(): void {
		$ports = $this->node()->getInputDefinitions();
		$this->assertSame(['prompt', 'system', 'model', 'temperature', 'apikey'], array_map(static fn($port): string => $port->name, $ports));
		$this->assertTrue($ports[0]->required);
		$this->assertFalse($ports[1]->required);
		$this->assertTrue($ports[4]->required);
	}

	private function node(): SimpleOpenAiNode {
		return new SimpleOpenAiNode('simple-openai-unit');
	}

	private function context(): IAgentContext {
		return $this->createStub(IAgentContext::class);
	}
}

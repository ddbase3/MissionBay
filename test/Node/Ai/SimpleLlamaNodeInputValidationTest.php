<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Ai;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Node\Ai\SimpleLlamaNode;
use PHPUnit\Framework\TestCase;

final class SimpleLlamaNodeInputValidationTest extends TestCase {

	public function testMissingAllRequiredInputsReturnsError(): void {
		$this->assertStringContainsString('Missing required input: endpoint, model or prompt',
			$this->node()->execute([], [], $this->context())['error']);
	}

	public function testEndpointIsRequiredBeforeHttpRequest(): void {
		$this->assertArrayHasKey('error', $this->node()->execute([
			'model' => 'llama3', 'prompt' => 'hello'
		], [], $this->context()));
	}

	public function testModelIsRequiredBeforeHttpRequest(): void {
		$this->assertArrayHasKey('error', $this->node()->execute([
			'endpoint' => 'http://127.0.0.1:9/', 'prompt' => 'hello'
		], [], $this->context()));
	}

	public function testPromptIsRequiredBeforeHttpRequest(): void {
		$this->assertArrayHasKey('error', $this->node()->execute([
			'endpoint' => 'http://127.0.0.1:9/', 'model' => 'llama3'
		], [], $this->context()));
	}

	public function testEmptyPromptRejectsWithoutUsingContext(): void {
		$context = $this->createMock(IAgentContext::class);
		$context->expects($this->never())->method('getMemory');
		$this->assertArrayHasKey('error', $this->node()->execute([
			'endpoint' => 'http://127.0.0.1:9/', 'model' => 'llama3', 'prompt' => ''
		], [], $context));
	}

	public function testPortDefinitionsDocumentRequiredValues(): void {
		$ports = $this->node()->getInputDefinitions();
		$this->assertSame(['endpoint', 'model', 'prompt', 'system', 'temperature'],
			array_map(static fn($port): string => $port->name, $ports));
		$this->assertTrue($ports[0]->required);
		$this->assertTrue($ports[1]->required);
		$this->assertTrue($ports[2]->required);
		$this->assertFalse($ports[3]->required);
		$this->assertFalse($ports[4]->required);
	}

	public function testOutputPortsExposeResponseAndError(): void {
		$this->assertSame(['response', 'error'],
			array_map(static fn($port): string => $port->name, $this->node()->getOutputDefinitions()));
	}

	private function node(): SimpleLlamaNode {
		return new SimpleLlamaNode('llama-unit');
	}

	private function context(): IAgentContext {
		return $this->createStub(IAgentContext::class);
	}
}

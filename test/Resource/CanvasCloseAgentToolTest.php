<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\CanvasCloseAgentTool;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;
use MissionBay\Api\IAgentTool;
use AssistantFoundation\Api\IAgentMemory;

class CanvasCloseAgentToolTest extends TestCase {

	public function testImplementsAgentToolInterface(): void {
		$tool = new CanvasCloseAgentTool('id1');
		$this->assertInstanceOf(IAgentTool::class, $tool);
	}

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('canvascloseagenttool', CanvasCloseAgentTool::getName());
	}

	public function testGetDescriptionReturnsExpectedValue(): void {
		$tool = new CanvasCloseAgentTool('id1');
		$this->assertSame('Closes the chatbot canvas.', $tool->getDescription());
	}

	public function testGetToolDefinitionsReturnsExpectedSchema(): void {
		$tool = new CanvasCloseAgentTool('id1');

		$defs = $tool->getToolDefinitions();

		$this->assertIsArray($defs);
		$this->assertCount(1, $defs);

		$def = $defs[0];

		$this->assertSame('function', $def['type']);
		$this->assertSame('Canvas Close', $def['label']);

		$this->assertIsArray($def['function']);
		$this->assertSame('close_canvas', $def['function']['name']);
		$this->assertIsString($def['function']['description']);

		$params = $def['function']['parameters'] ?? null;
		$this->assertIsArray($params);
		$this->assertSame('object', $params['type']);

		$this->assertIsArray($params['properties']);
		$this->assertArrayHasKey('canvas_id', $params['properties']);
		$this->assertSame('string', $params['properties']['canvas_id']['type']);
		$this->assertIsString($params['properties']['canvas_id']['description']);
	}

	public function testCallToolThrowsForUnsupportedName(): void {
		$tool = new CanvasCloseAgentTool('id1');
		$context = new AgentContextStub(null);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported tool: no');

		$tool->callTool('no', [], $context);
	}

	public function testCallToolReturnsErrorIfEventSinkMissing(): void {
		$tool = new CanvasCloseAgentTool('id1');
		$context = new AgentContextStub(null);

		$result = $tool->callTool('close_canvas', [], $context);

		$this->assertSame([
			'ok' => false,
			'error' => 'Missing agent event sink in context.'
		], $result);
	}

	public function testCallToolUsesDefaultCanvasIdMainIfNotProvided(): void {
		$stream = new AgentEventSinkStub();
		$context = new AgentContextStub($stream);

		$tool = new CanvasCloseAgentTool('id1');

		$result = $tool->callTool('close_canvas', [], $context);

		$this->assertSame(['canvas.close', ['id' => 'main']], $stream->pushed[0]);
		$this->assertSame(['ok' => true, 'canvas_id' => 'main'], $result);
	}

	public function testCallToolTrimsCanvasIdAndFallsBackToMainOnEmptyString(): void {
		$stream = new AgentEventSinkStub();
		$context = new AgentContextStub($stream);

		$tool = new CanvasCloseAgentTool('id1');

		$result = $tool->callTool('close_canvas', ['canvas_id' => '   '], $context);

		$this->assertSame(['canvas.close', ['id' => 'main']], $stream->pushed[0]);
		$this->assertSame(['ok' => true, 'canvas_id' => 'main'], $result);
	}

	public function testCallToolPushesCanvasCloseIfConnected(): void {
		$stream = new AgentEventSinkStub();
		$context = new AgentContextStub($stream);

		$tool = new CanvasCloseAgentTool('id1');

		$result = $tool->callTool('close_canvas', ['canvas_id' => 'abc'], $context);

		$this->assertCount(1, $stream->pushed);
		$this->assertSame(['canvas.close', ['id' => 'abc']], $stream->pushed[0]);
		$this->assertSame(['ok' => true, 'canvas_id' => 'abc'], $result);
	}

	public function testCallToolDoesNotPushIfDisconnectedButStillReturnsOk(): void {
		$stream = new AgentEventSinkStub();
		$stream->disconnected = true;

		$context = new AgentContextStub($stream);

		$tool = new CanvasCloseAgentTool('id1');

		$result = $tool->callTool('close_canvas', ['canvas_id' => 'abc'], $context);

		$this->assertSame([], $stream->pushed);
		$this->assertSame(['ok' => true, 'canvas_id' => 'abc'], $result);
	}

	public function testCallToolReturnsErrorIfPushThrows(): void {
		$stream = new AgentEventSinkStub();
		$stream->throwOnPush = true;

		$context = new AgentContextStub($stream);

		$tool = new CanvasCloseAgentTool('id1');

		$result = $tool->callTool('close_canvas', ['canvas_id' => 'abc'], $context);

		$this->assertSame([
			'ok' => false,
			'error' => 'Failed to push canvas.close: push failed'
		], $result);
	}

}

/**
 * Full IAgentContext stub (matching the provided interface) including IBase::getName().
 */


/**
 * Full IAgentMemory stub including IBase::getName().
 */


/**
 * A recording event sink used by CanvasCloseAgentTool tests.
 */

require_once __DIR__ . '/AgentContextStub.php';
require_once __DIR__ . '/AgentMemoryStub.php';
require_once __DIR__ . '/AgentEventSinkStub.php';

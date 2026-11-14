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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Base3\MissionBay\Resource\HelloWorldCanvasAgentTool;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Dto\AgentExecutionEvent;
use Base3\AssistantFoundation\Api\IAgentMemory;

#[CoversClass(\Base3\MissionBay\Resource\HelloWorldCanvasAgentTool::class)]
class HelloWorldCanvasAgentToolTest extends TestCase {

	private function makeContext(array $vars = []): IAgentContext {
		$memory = new HelloWorldCanvasAgentToolTestDouble1();

		return new HelloWorldCanvasAgentToolTestDouble2($vars, $memory);
	}

	private function makeFakeSink(bool $cancelled = false, bool $throwOnEmit = false): object {
		return new HelloWorldCanvasAgentToolTestDouble3($cancelled, $throwOnEmit);
	}

	public function testGetName(): void {
		$this->assertSame('helloworldcanvasagenttool', HelloWorldCanvasAgentTool::getName());
	}

	public function testGetToolDefinitionsHasExpectedShape(): void {
		$t = new HelloWorldCanvasAgentTool('t1');

		$defs = $t->getToolDefinitions();
		$this->assertIsArray($defs);
		$this->assertCount(1, $defs);

		$def = $defs[0];
		$this->assertSame('function', $def['type'] ?? null);
		$this->assertSame('Hello World Canvas', $def['label'] ?? null);

		$fn = $def['function'] ?? null;
		$this->assertIsArray($fn);
		$this->assertSame('hello_world_canvas', $fn['name'] ?? null);

		$params = $fn['parameters'] ?? null;
		$this->assertIsArray($params);
		$this->assertSame('object', $params['type'] ?? null);

		$props = $params['properties'] ?? null;
		$this->assertIsArray($props);
		$this->assertArrayHasKey('canvas_id', $props);
		$this->assertArrayHasKey('title', $props);
		$this->assertArrayHasKey('open', $props);
	}

	public function testCallToolThrowsOnUnsupportedTool(): void {
		$t = new HelloWorldCanvasAgentTool('t2');
		$ctx = $this->makeContext([]);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported tool: no_such_tool');

		$t->callTool('no_such_tool', [], $ctx);
	}

	public function testCallToolReturnsErrorWhenEventSinkMissing(): void {
		$t = new HelloWorldCanvasAgentTool('t3');
		$ctx = $this->makeContext([]);

		$res = $t->callTool('hello_world_canvas', [], $ctx);

		$this->assertIsArray($res);
		$this->assertSame(false, $res['ok'] ?? null);
		$this->assertSame('Missing agent event sink in context.', $res['error'] ?? null);
	}

	public function testCallToolNormalizesArgumentsAndPushesOpenAndRender(): void {
		$t = new HelloWorldCanvasAgentTool('t4');

		$sink = $this->makeFakeSink(false, false);
		$ctx = $this->makeContext([IAgentEventSink::CONTEXT_KEY => $sink]);

		$res = $t->callTool('hello_world_canvas', [
			'canvas_id' => '  myCanvas  ',
			'title' => '  My Title  ',
			'open' => 'true',
		], $ctx);

		$this->assertSame(true, $res['ok'] ?? null);
		$this->assertSame('myCanvas', $res['canvas_id'] ?? null);

		$this->assertCount(2, $sink->events);

		$this->assertSame('canvas.open', $sink->events[0]['event']);
		$this->assertSame('myCanvas', $sink->events[0]['data']['id'] ?? null);
		$this->assertSame('My Title', $sink->events[0]['data']['title'] ?? null);
		$this->assertSame(true, $sink->events[0]['data']['focus'] ?? null);

		$this->assertSame('canvas.render', $sink->events[1]['event']);
		$render = $sink->events[1]['data'];
		$this->assertSame('myCanvas', $render['id'] ?? null);
		$this->assertSame('replace', $render['mode'] ?? null);
		$this->assertSame('My Title', $render['title'] ?? null);

		$blocks = $render['blocks'] ?? null;
		$this->assertIsArray($blocks);
		$this->assertCount(2, $blocks);
		$this->assertSame('html', $blocks[0]['type'] ?? null);
		$this->assertSame(true, $blocks[0]['sanitize'] ?? null);
		$this->assertIsString($blocks[0]['html'] ?? null);
		$this->assertSame('html', $blocks[1]['type'] ?? null);
		$this->assertSame(true, $blocks[1]['sanitize'] ?? null);
		$this->assertIsString($blocks[1]['html'] ?? null);
	}

	public function testCallToolDefaultsCanvasIdAndTitleWhenEmptyAndOpenParsesToFalse(): void {
		$t = new HelloWorldCanvasAgentTool('t5');

		$sink = $this->makeFakeSink(false, false);
		$ctx = $this->makeContext([IAgentEventSink::CONTEXT_KEY => $sink]);

		$res = $t->callTool('hello_world_canvas', [
			'canvas_id' => '   ',
			'title' => '',
			'open' => '0',
		], $ctx);

		$this->assertSame(true, $res['ok'] ?? null);
		$this->assertSame('main', $res['canvas_id'] ?? null);

		$this->assertCount(1, $sink->events);
		$this->assertSame('canvas.render', $sink->events[0]['event']);
		$this->assertSame('main', $sink->events[0]['data']['id'] ?? null);
		$this->assertSame('Hello Canvas', $sink->events[0]['data']['title'] ?? null);
	}

	public function testCallToolDoesNotPushWhenDisconnected(): void {
		$t = new HelloWorldCanvasAgentTool('t6');

		$sink = $this->makeFakeSink(true, false);
		$ctx = $this->makeContext([IAgentEventSink::CONTEXT_KEY => $sink]);

		$res = $t->callTool('hello_world_canvas', [], $ctx);

		$this->assertSame(true, $res['ok'] ?? null);
		$this->assertSame('main', $res['canvas_id'] ?? null);
		$this->assertCount(0, $sink->events);
	}

	public function testCallToolReturnsErrorWhenPushThrows(): void {
		$t = new HelloWorldCanvasAgentTool('t7');

		$sink = $this->makeFakeSink(false, true);
		$ctx = $this->makeContext([IAgentEventSink::CONTEXT_KEY => $sink]);

		$res = $t->callTool('hello_world_canvas', [], $ctx);

		$this->assertIsArray($res);
		$this->assertSame(false, $res['ok'] ?? null);
		$this->assertIsString($res['error'] ?? null);
		$this->assertStringContainsString('Failed to push canvas events: push failed', (string)($res['error'] ?? ''));
	}
}

require_once __DIR__ . '/HelloWorldCanvasAgentToolTestDouble1.php';

require_once __DIR__ . '/HelloWorldCanvasAgentToolTestDouble2.php';

require_once __DIR__ . '/HelloWorldCanvasAgentToolTestDouble3.php';

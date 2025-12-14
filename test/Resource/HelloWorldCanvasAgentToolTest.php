<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\HelloWorldCanvasAgentTool;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;

/**
 * @covers \MissionBay\Resource\HelloWorldCanvasAgentTool
 */
class HelloWorldCanvasAgentToolTest extends TestCase {

	private function makeContext(array $vars = []): IAgentContext {
		$memory = new class implements IAgentMemory {
			private array $history = [];

			public static function getName(): string {
				return 'testagentmemory';
			}

			public function loadNodeHistory(string $nodeId): array {
				return $this->history[$nodeId] ?? [];
			}

			public function appendNodeHistory(string $nodeId, array $message): void {
				$this->history[$nodeId] ??= [];
				$this->history[$nodeId][] = $message;
			}

			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
				if (empty($this->history[$nodeId])) {
					return false;
				}

				foreach ($this->history[$nodeId] as &$msg) {
					if (($msg['id'] ?? null) === $messageId) {
						$msg['feedback'] = $feedback;
						return true;
					}
				}

				return false;
			}

			public function resetNodeHistory(string $nodeId): void {
				$this->history[$nodeId] = [];
			}

			public function getPriority(): int {
				return 0;
			}
		};

		return new class($vars, $memory) implements IAgentContext {
			private array $vars;
			private IAgentMemory $memory;

			public function __construct(array $vars, IAgentMemory $memory) {
				$this->vars = $vars;
				$this->memory = $memory;
			}

			public static function getName(): string {
				return 'testagentcontext';
			}

			public function getMemory(): IAgentMemory {
				return $this->memory;
			}

			public function setMemory(IAgentMemory $memory): void {
				$this->memory = $memory;
			}

			public function setVar(string $key, mixed $value): void {
				$this->vars[$key] = $value;
			}

			public function getVar(string $key): mixed {
				return $this->vars[$key] ?? null;
			}

			public function forgetVar(string $key): void {
				unset($this->vars[$key]);
			}

			public function listVars(): array {
				return array_keys($this->vars);
			}
		};
	}

	private function makeFakeStream(bool $disconnected = false, bool $throwOnPush = false): object {
		return new class($disconnected, $throwOnPush) {
			public array $events = [];
			private bool $disconnected;
			private bool $throwOnPush;

			public function __construct(bool $disconnected, bool $throwOnPush) {
				$this->disconnected = $disconnected;
				$this->throwOnPush = $throwOnPush;
			}

			public function isDisconnected(): bool {
				return $this->disconnected;
			}

			public function push(string $event, array $data): void {
				if ($this->throwOnPush) {
					throw new \RuntimeException('push failed');
				}
				$this->events[] = ['event' => $event, 'data' => $data];
			}
		};
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

	public function testCallToolReturnsErrorWhenEventstreamMissing(): void {
		$t = new HelloWorldCanvasAgentTool('t3');
		$ctx = $this->makeContext([]); // no eventstream

		$res = $t->callTool('hello_world_canvas', [], $ctx);

		$this->assertIsArray($res);
		$this->assertSame(false, $res['ok'] ?? null);
		$this->assertSame('Missing eventstream in context.', $res['error'] ?? null);
	}

	public function testCallToolNormalizesArgumentsAndPushesOpenAndRender(): void {
		$t = new HelloWorldCanvasAgentTool('t4');

		$stream = $this->makeFakeStream(false, false);
		$ctx = $this->makeContext(['eventstream' => $stream]);

		$res = $t->callTool('hello_world_canvas', [
			'canvas_id' => '  myCanvas  ',
			'title' => '  My Title  ',
			'open' => 'true',
		], $ctx);

		$this->assertSame(true, $res['ok'] ?? null);
		$this->assertSame('myCanvas', $res['canvas_id'] ?? null);

		$this->assertCount(2, $stream->events);

		$this->assertSame('canvas.open', $stream->events[0]['event']);
		$this->assertSame('myCanvas', $stream->events[0]['data']['id'] ?? null);
		$this->assertSame('My Title', $stream->events[0]['data']['title'] ?? null);
		$this->assertSame(true, $stream->events[0]['data']['focus'] ?? null);

		$this->assertSame('canvas.render', $stream->events[1]['event']);
		$render = $stream->events[1]['data'];
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

		$stream = $this->makeFakeStream(false, false);
		$ctx = $this->makeContext(['eventstream' => $stream]);

		$res = $t->callTool('hello_world_canvas', [
			'canvas_id' => '   ',
			'title' => '',
			'open' => '0',
		], $ctx);

		$this->assertSame(true, $res['ok'] ?? null);
		$this->assertSame('main', $res['canvas_id'] ?? null);

		$this->assertCount(1, $stream->events);
		$this->assertSame('canvas.render', $stream->events[0]['event']);
		$this->assertSame('main', $stream->events[0]['data']['id'] ?? null);
		$this->assertSame('Hello Canvas', $stream->events[0]['data']['title'] ?? null);
	}

	public function testCallToolDoesNotPushWhenDisconnected(): void {
		$t = new HelloWorldCanvasAgentTool('t6');

		$stream = $this->makeFakeStream(true, false);
		$ctx = $this->makeContext(['eventstream' => $stream]);

		$res = $t->callTool('hello_world_canvas', [], $ctx);

		$this->assertSame(true, $res['ok'] ?? null);
		$this->assertSame('main', $res['canvas_id'] ?? null);
		$this->assertCount(0, $stream->events);
	}

	public function testCallToolReturnsErrorWhenPushThrows(): void {
		$t = new HelloWorldCanvasAgentTool('t7');

		$stream = $this->makeFakeStream(false, true);
		$ctx = $this->makeContext(['eventstream' => $stream]);

		$res = $t->callTool('hello_world_canvas', [], $ctx);

		$this->assertIsArray($res);
		$this->assertSame(false, $res['ok'] ?? null);
		$this->assertIsString($res['error'] ?? null);
		$this->assertStringContainsString('Failed to push canvas events: push failed', (string)($res['error'] ?? ''));
	}
}

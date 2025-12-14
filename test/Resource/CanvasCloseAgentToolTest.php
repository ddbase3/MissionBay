<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\CanvasCloseAgentTool;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentMemory;

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

	public function testCallToolReturnsErrorIfEventStreamMissing(): void {
		$tool = new CanvasCloseAgentTool('id1');
		$context = new AgentContextStub(null);

		$result = $tool->callTool('close_canvas', [], $context);

		$this->assertSame([
			'ok' => false,
			'error' => 'Missing eventstream in context.'
		], $result);
	}

	public function testCallToolUsesDefaultCanvasIdMainIfNotProvided(): void {
		$stream = new EventStreamStub();
		$context = new AgentContextStub($stream);

		$tool = new CanvasCloseAgentTool('id1');

		$result = $tool->callTool('close_canvas', [], $context);

		$this->assertSame(['canvas.close', ['id' => 'main']], $stream->pushed[0]);
		$this->assertSame(['ok' => true, 'canvas_id' => 'main'], $result);
	}

	public function testCallToolTrimsCanvasIdAndFallsBackToMainOnEmptyString(): void {
		$stream = new EventStreamStub();
		$context = new AgentContextStub($stream);

		$tool = new CanvasCloseAgentTool('id1');

		$result = $tool->callTool('close_canvas', ['canvas_id' => '   '], $context);

		$this->assertSame(['canvas.close', ['id' => 'main']], $stream->pushed[0]);
		$this->assertSame(['ok' => true, 'canvas_id' => 'main'], $result);
	}

	public function testCallToolPushesCanvasCloseIfConnected(): void {
		$stream = new EventStreamStub();
		$context = new AgentContextStub($stream);

		$tool = new CanvasCloseAgentTool('id1');

		$result = $tool->callTool('close_canvas', ['canvas_id' => 'abc'], $context);

		$this->assertCount(1, $stream->pushed);
		$this->assertSame(['canvas.close', ['id' => 'abc']], $stream->pushed[0]);
		$this->assertSame(['ok' => true, 'canvas_id' => 'abc'], $result);
	}

	public function testCallToolDoesNotPushIfDisconnectedButStillReturnsOk(): void {
		$stream = new EventStreamStub();
		$stream->disconnected = true;

		$context = new AgentContextStub($stream);

		$tool = new CanvasCloseAgentTool('id1');

		$result = $tool->callTool('close_canvas', ['canvas_id' => 'abc'], $context);

		$this->assertSame([], $stream->pushed);
		$this->assertSame(['ok' => true, 'canvas_id' => 'abc'], $result);
	}

	public function testCallToolReturnsErrorIfPushThrows(): void {
		$stream = new EventStreamStub();
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
class AgentContextStub implements IAgentContext {

	private array $vars = [];
	private IAgentMemory $memory;

	public function __construct(
		private mixed $eventStream
	) {
		$this->vars['eventstream'] = $eventStream;
		$this->memory = new AgentMemoryStub();
	}

	public static function getName(): string {
		return 'agentcontextstub';
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

}

/**
 * Full IAgentMemory stub including IBase::getName().
 */
class AgentMemoryStub implements IAgentMemory {

	public static function getName(): string {
		return 'agentmemorystub';
	}

	public function loadNodeHistory(string $nodeId): array {
		return [];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		return;
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		return;
	}

	public function getPriority(): int {
		return 0;
	}

}

/**
 * A tiny eventstream stub matching the methods used by CanvasCloseAgentTool.
 */
class EventStreamStub {

	public bool $disconnected = false;
	public bool $throwOnPush = false;

	/** @var array<int, array{0:string,1:array<string,mixed>}> */
	public array $pushed = [];

	public function isDisconnected(): bool {
		return $this->disconnected;
	}

	public function push(string $event, array $payload): void {
		if ($this->throwOnPush) {
			throw new \RuntimeException('push failed');
		}
		$this->pushed[] = [$event, $payload];
	}

}

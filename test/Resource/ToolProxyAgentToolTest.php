<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\ToolProxyAgentTool;

/**
 * @covers \MissionBay\Resource\ToolProxyAgentTool
 */
class ToolProxyAgentToolTest extends TestCase {

	private function makeContextWithEventStream(array &$events): IAgentContext {
		$stream = new class($events) {
			private array $events;

			public function __construct(array &$events) {
				$this->events = &$events;
			}

			public function push(string $event, array $payload): void {
				$this->events[] = ['event' => $event, 'payload' => $payload];
			}
		};

		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(function (string $key) use ($stream): mixed {
			if ($key === 'eventstream') {
				return $stream;
			}
			return null;
		});

		return $context;
	}

	private function makeTool(string $fnName, string $category, array $tags, int $priority, mixed $result): IAgentTool {
		return new class($fnName, $category, $tags, $priority, $result) implements IAgentTool {
			private string $fnName;
			private string $category;
			private array $tags;
			private int $priority;
			private mixed $result;

			public function __construct(string $fnName, string $category, array $tags, int $priority, mixed $result) {
				$this->fnName = $fnName;
				$this->category = $category;
				$this->tags = $tags;
				$this->priority = $priority;
				$this->result = $result;
			}

			public static function getName(): string {
				return 'dummytool';
			}

			public function getDescription(): string {
				return 'Dummy tool for ToolProxyAgentTool tests.';
			}

			public function getToolDefinitions(): array {
				return [[
					'type' => 'function',
					'label' => 'Dummy',
					'category' => $this->category,
					'tags' => $this->tags,
					'priority' => $this->priority,
					'function' => [
						'name' => $this->fnName,
						'description' => 'Does something.',
						'parameters' => [
							'type' => 'object',
							'properties' => [
								'x' => ['type' => 'string']
							],
							'required' => []
						]
					]
				]];
			}

			public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
				if ($name !== $this->fnName) {
					throw new \InvalidArgumentException('Unexpected tool call: ' . $name);
				}
				return $this->result;
			}
		};
	}

	public function testGetName(): void {
		$this->assertSame('toolproxyagenttool', ToolProxyAgentTool::getName());
	}

	public function testGetDescription(): void {
		$r = new ToolProxyAgentTool('x1');
		$this->assertSame(
			'Searches and invokes tools behind a proxy using categories, tags (boost), and priority (ranking).',
			$r->getDescription()
		);
	}

	public function testGetToolDefinitionsIncludesProxyToolsAndCategoryEnumWhenToolsDocked(): void {
		$r = new ToolProxyAgentTool('x2');

		$t1 = $this->makeTool('alpha', 'Memory', ['User', 'Prefs'], 10, ['ok' => 1]);
		$t2 = $this->makeTool('beta', 'Memory', ['prefs', 'session'], 20, ['ok' => 2]);
		$t3 = $this->makeTool('gamma', 'Files', ['upload'], 5, ['ok' => 3]);

		$r->init(['tools' => [$t1, $t2, $t3]], $this->createStub(IAgentContext::class));

		$defs = $r->getToolDefinitions();

		$this->assertCount(4, $defs);

		$this->assertSame('toolproxy_list_categories', $defs[0]['function']['name']);
		$this->assertSame('toolproxy_search', $defs[1]['function']['name']);
		$this->assertSame('toolproxy_describe', $defs[2]['function']['name']);
		$this->assertSame('toolproxy_call', $defs[3]['function']['name']);

		$categorySchema = $defs[1]['function']['parameters']['properties']['category'] ?? null;
		$this->assertIsArray($categorySchema);
		$this->assertSame(['files', 'memory'], $categorySchema['enum']);
	}

	public function testListCategoriesReturnsSortedCategoriesWithCountsAndTopTags(): void {
		$r = new ToolProxyAgentTool('x3');

		$t1 = $this->makeTool('alpha', 'Memory', ['prefs', 'user'], 10, ['ok' => 1]);
		$t2 = $this->makeTool('beta', 'Memory', ['prefs', 'session'], 20, ['ok' => 2]);
		$t3 = $this->makeTool('gamma', 'Files', ['upload', 'prefs'], 5, ['ok' => 3]);

		$r->init(['tools' => [$t1, $t2, $t3]], $this->createStub(IAgentContext::class));

		$out = $r->callTool('toolproxy_list_categories', ['max_categories' => 50, 'max_tags_per_category' => 10], $this->createStub(IAgentContext::class));

		$this->assertIsArray($out);
		$this->assertIsArray($out['categories'] ?? null);

		$this->assertSame('files', $out['categories'][0]['category']);
		$this->assertSame(1, $out['categories'][0]['tool_count']);

		$this->assertSame('memory', $out['categories'][1]['category']);
		$this->assertSame(2, $out['categories'][1]['tool_count']);

		$memoryTags = $out['categories'][1]['top_tags'];
		$this->assertGreaterThanOrEqual(1, count($memoryTags));
		$this->assertSame('prefs', $memoryTags[0]['tag']);
		$this->assertSame(2, $memoryTags[0]['count']);
	}

	public function testSearchRanksByTagMatchesThenPriorityThenName(): void {
		$r = new ToolProxyAgentTool('x4');

		// Same category, different tag sets + priorities.
		$t1 = $this->makeTool('alpha', 'memory', ['prefs'], 10, ['ok' => 1]);              // 1 match
		$t2 = $this->makeTool('beta', 'memory', ['prefs', 'user'], 5, ['ok' => 2]);        // 2 matches
		$t3 = $this->makeTool('gamma', 'memory', ['prefs'], 99, ['ok' => 3]);              // 1 match, high priority

		$r->init(['tools' => [$t1, $t2, $t3]], $this->createStub(IAgentContext::class));

		$out = $r->callTool('toolproxy_search', [
			'category' => 'memory',
			'tags' => ['prefs', 'user'],
			'limit' => 10
		], $this->createStub(IAgentContext::class));

		$this->assertIsArray($out);
		$this->assertIsArray($out['results'] ?? null);
		$this->assertCount(3, $out['results']);

		// beta has 2 tag matches => first.
		$this->assertSame('beta', $out['results'][0]['name']);
		$this->assertSame(2, $out['results'][0]['matching_tags']);

		// alpha and gamma have 1 match; gamma has higher priority => second.
		$this->assertSame('gamma', $out['results'][1]['name']);
		$this->assertSame('alpha', $out['results'][2]['name']);
	}

	public function testDescribeReturnsFullSchemaAndErrorsOnMissingOrUnknownOrDuplicate(): void {
		$r = new ToolProxyAgentTool('x5');

		$t1 = $this->makeTool('alpha', 'memory', ['prefs'], 10, ['ok' => 1]);
		$t2 = $this->makeTool('beta', 'memory', ['prefs'], 20, ['ok' => 2]);

		$r->init(['tools' => [$t1, $t2]], $this->createStub(IAgentContext::class));

		$missing = $r->callTool('toolproxy_describe', [], $this->createStub(IAgentContext::class));
		$this->assertSame(['error' => 'Missing required parameter: name'], $missing);

		$unknown = $r->callTool('toolproxy_describe', ['name' => 'nope'], $this->createStub(IAgentContext::class));
		$this->assertSame(['error' => 'Tool not found: nope'], $unknown);

		$desc = $r->callTool('toolproxy_describe', ['name' => 'alpha'], $this->createStub(IAgentContext::class));
		$this->assertSame('alpha', $desc['name']);
		$this->assertSame('memory', $desc['category']);
		$this->assertIsArray($desc['parameters']);

		// Duplicate function names should be marked ambiguous (removed from catalog).
		$tDup1 = $this->makeTool('dup', 'memory', ['a'], 1, ['ok' => 1]);
		$tDup2 = $this->makeTool('dup', 'memory', ['b'], 2, ['ok' => 2]);
		$r->init(['tools' => [$tDup1, $tDup2]], $this->createStub(IAgentContext::class));

		$dup = $r->callTool('toolproxy_describe', ['name' => 'dup'], $this->createStub(IAgentContext::class));
		$this->assertSame(['error' => 'Ambiguous tool name (duplicate). Ensure unique function names behind the proxy.'], $dup);
	}

	public function testCallInvokesUnderlyingToolAndEmitsEventsAndHandlesErrors(): void {
		$r = new ToolProxyAgentTool('x6');

		$events = [];
		$context = $this->makeContextWithEventStream($events);

		$okTool = $this->makeTool('alpha', 'memory', ['prefs'], 10, ['worked' => true]);

		$failTool = new class implements IAgentTool {
			public static function getName(): string { return 'failtool'; }
			public function getDescription(): string { return 'Fails.'; }
			public function getToolDefinitions(): array {
				return [[
					'type' => 'function',
					'label' => 'Fail',
					'category' => 'memory',
					'tags' => ['prefs'],
					'priority' => 10,
					'function' => [
						'name' => 'fail',
						'description' => 'Fails.',
						'parameters' => ['type' => 'object', 'properties' => [], 'required' => []]
					]
				]];
			}
			public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
				throw new \RuntimeException('boom');
			}
		};

		$r->init(['tools' => [$okTool, $failTool]], $this->createStub(IAgentContext::class));

		$missingName = $r->callTool('toolproxy_call', ['arguments' => []], $context);
		$this->assertSame(['error' => 'Missing required parameter: name'], $missingName);

		$badArgs = $r->callTool('toolproxy_call', ['name' => 'alpha', 'arguments' => 'nope'], $context);
		$this->assertSame(['error' => 'Parameter "arguments" must be an object'], $badArgs);

		$notFound = $r->callTool('toolproxy_call', ['name' => 'nope', 'arguments' => []], $context);
		$this->assertSame(['error' => 'Tool not found: nope'], $notFound);

		$ok = $r->callTool('toolproxy_call', ['name' => 'alpha', 'arguments' => ['x' => 'y']], $context);
		$this->assertTrue($ok['ok']);
		$this->assertSame('alpha', $ok['tool']);
		$this->assertSame(['worked' => true], $ok['result']);

		$fail = $r->callTool('toolproxy_call', ['name' => 'fail', 'arguments' => []], $context);
		$this->assertFalse($fail['ok']);
		$this->assertSame('fail', $fail['tool']);
		$this->assertSame('boom', $fail['error']);

		// Expect started+finished for alpha and fail (2*2 = 4 events).
		$this->assertCount(4, $events);
		$this->assertSame('tool.started', $events[0]['event']);
		$this->assertSame('tool.finished', $events[1]['event']);
		$this->assertSame('tool.started', $events[2]['event']);
		$this->assertSame('tool.finished', $events[3]['event']);
	}

	public function testSearchReturnsHelpfulErrorsForMissingCategoryOrNoToolsOrUnknownCategory(): void {
		$r = new ToolProxyAgentTool('x7');

		// No tools docked => specific error.
		$r->init([], $this->createStub(IAgentContext::class));

		$missing = $r->callTool('toolproxy_search', [], $this->createStub(IAgentContext::class));
		$this->assertSame(['error' => 'Missing required parameter: category'], $missing);

		$noTools = $r->callTool('toolproxy_search', ['category' => 'memory'], $this->createStub(IAgentContext::class));
		$this->assertSame(['error' => 'No tools available behind proxy'], $noTools);

		// Dock a tool and search unknown category.
		$t1 = $this->makeTool('alpha', 'memory', ['prefs'], 10, ['ok' => 1]);
		$r->init(['tools' => [$t1]], $this->createStub(IAgentContext::class));

		$unknown = $r->callTool('toolproxy_search', ['category' => 'nope'], $this->createStub(IAgentContext::class));
		$this->assertSame('Unknown category: nope', $unknown['error']);
		$this->assertSame(['memory'], $unknown['available_categories']);
	}

	public function testDuplicateFunctionNamesAreRemovedFromCatalogAndReturnAmbiguousErrors(): void {
		$r = new ToolProxyAgentTool('x8');

		$tDup1 = $this->makeTool('dup', 'memory', ['a'], 1, ['ok' => 1]);
		$tDup2 = $this->makeTool('dup', 'files', ['b'], 2, ['ok' => 2]);

		$r->init(['tools' => [$tDup1, $tDup2]], $this->createStub(IAgentContext::class));

		$desc = $r->callTool('toolproxy_describe', ['name' => 'dup'], $this->createStub(IAgentContext::class));
		$this->assertSame(['error' => 'Ambiguous tool name (duplicate). Ensure unique function names behind the proxy.'], $desc);

		$call = $r->callTool('toolproxy_call', ['name' => 'dup', 'arguments' => []], $this->createStub(IAgentContext::class));
		$this->assertSame(['error' => 'Ambiguous tool name (duplicate). Ensure unique function names behind the proxy.'], $call);
	}
}

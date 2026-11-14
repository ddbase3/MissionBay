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
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\Resource\GenericChatModelAgentResource;
use Base3\MissionBay\Api\IAgentConfigValueResolver;

#[CoversClass(\Base3\MissionBay\Resource\GenericChatModelAgentResource::class)]
class GenericChatModelAgentResourceTest extends TestCase {

	private function makeResolver(array $map = []): IAgentConfigValueResolver {
		return new GenericChatModelAgentResourceTestDouble1($map);
	}

	private function callNormalize(GenericChatModelAgentResource $r, array $messages): array {
		$ref = new \ReflectionClass(GenericChatModelAgentResource::class);
		$m = $ref->getMethod('normalizeMessages');
		$m->setAccessible(true);
		/** @var array $out */
		$out = $m->invoke($r, $messages);
		return $out;
	}

	private function providerRequestEvents(): AiProviderRequestEventDispatcher {
		return new AiProviderRequestEventDispatcher(new EventManager());
	}

	public function testGetName(): void {
		$this->assertSame('genericchatmodelagentresource', GenericChatModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaultsWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertSame('gpt-4o-mini', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.openai.com/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.7, $opts['temperature']);
		$this->assertSame(512, $opts['maxtokens']);
	}

	public function testSetConfigResolvesAndCastsValues(): void {
		$map = [
			'model_key' => 'my-model',
			'apikey_key' => 'sk-test',
			'endpoint_key' => 'https://example.com/v1/chat/completions',
			'temp_key' => '0.25',
			'max_key' => '999',
		];

		$resolver = $this->makeResolver($map);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
			'temperature' => 'temp_key',
			'maxtokens' => 'max_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('my-model', $opts['model']);
		$this->assertSame('sk-test', $opts['apikey']);
		$this->assertSame('https://example.com/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.25, $opts['temperature']);
		$this->assertSame(999, $opts['maxtokens']);
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g3');
		$r->setConfig([]);

		$r->setOptions([
			'temperature' => 0.11,
			'maxtokens' => 42,
			'model' => 'abc',
			'extra' => 'x',
		]);

		$opts = $r->getOptions();
		$this->assertSame(0.11, $opts['temperature']);
		$this->assertSame(42, $opts['maxtokens']);
		$this->assertSame('abc', $opts['model']);
		$this->assertSame('x', $opts['extra']);
	}

	public function testChatThrowsOnMalformedResponse(): void {
		$resolver = $this->makeResolver([]);

		$r = new GenericChatModelAgentResourceTestDouble2($resolver, $this->providerRequestEvents(), 'g4');

		$r->setConfig(['apikey' => 'dummy']);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unable to normalize chat model response.');

		$r->chat([['role' => 'user', 'content' => 'Hi']]);
	}

	public function testChatReturnsAssistantContentFromRaw(): void {
		$resolver = $this->makeResolver([]);

		$r = new GenericChatModelAgentResourceTestDouble3($resolver, $this->providerRequestEvents(), 'g5');

		$r->setConfig(['apikey' => 'dummy']);

		$this->assertSame('Hello', $r->chat([['role' => 'user', 'content' => 'Hi']]));
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g6');
		$r->setConfig([]); // apikey null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for chat model.');

		$r->raw([['role' => 'user', 'content' => 'Hi']]);
	}

	public function testNormalizeMergesSystemMessagesAndTrimsAndPrepends(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g7');

		$out = $this->callNormalize($r, [
			['role' => 'system', 'content' => '  A  '],
			['role' => 'system', 'content' => "B\n"],
			['role' => 'user', 'content' => 'Hi'],
			['role' => 'system', 'content' => '   '], // ignored
			['role' => 'assistant', 'content' => 'Yo'],
		]);

		$this->assertSame('system', $out[0]['role']);
		$this->assertSame("A\n\nB", $out[0]['content']);
		$this->assertSame(['role' => 'user', 'content' => 'Hi'], $out[1]);
		$this->assertSame(['role' => 'assistant', 'content' => 'Yo'], $out[2]);
	}

	public function testNormalizeSkipsInvalidEntries(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g8');

		$out = $this->callNormalize($r, [
			'nope',
			['content' => 'missing role'],
			['role' => 'user', 'content' => 'ok'],
		]);

		$this->assertSame([['role' => 'user', 'content' => 'ok']], $out);
	}

	public function testNormalizeEncodesNonStringContent(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g9');

		$out = $this->callNormalize($r, [
			['role' => 'user', 'content' => ['a' => 1, 'b' => true]],
		]);

		$this->assertSame('user', $out[0]['role']);
		$this->assertSame('{"a":1,"b":true}', $out[0]['content']);
	}

	public function testNormalizeInjectsFeedbackAsAdditionalUserMessage(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g10');

		$out = $this->callNormalize($r, [
			['role' => 'user', 'content' => 'Hi', 'feedback' => '  be concise  '],
			['role' => 'assistant', 'content' => 'Ok'],
		]);

		$this->assertCount(3, $out);
		$this->assertSame(['role' => 'user', 'content' => 'Hi'], $out[0]);
		$this->assertSame(['role' => 'user', 'content' => 'be concise'], $out[1]);
		$this->assertSame(['role' => 'assistant', 'content' => 'Ok'], $out[2]);
	}

	public function testNormalizeDoesNotInjectEmptyFeedback(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g11');

		$out = $this->callNormalize($r, [
			['role' => 'user', 'content' => 'Hi', 'feedback' => '   '],
		]);

		$this->assertSame([['role' => 'user', 'content' => 'Hi']], $out);
	}

	public function testNormalizeToolMessageRequiresToolCallId(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g12');

		// IMPORTANT:
		// Tool messages are only allowed if there was a preceding assistant tool_calls message
		// declaring the same tool_call_id in THIS outgoing payload.
		$out = $this->callNormalize($r, [
			[
				'role' => 'assistant',
				'content' => '',
				'tool_calls' => [
					[
						'id' => '123',
						'function' => [
							'name' => 'my_tool',
							'arguments' => ['x' => 1],
						]
					]
				]
			],
			['role' => 'tool', 'content' => 'x'], // skipped (missing tool_call_id)
			['role' => 'tool', 'tool_call_id' => 123, 'content' => ['ok' => true]], // kept
		]);

		$this->assertCount(2, $out);

		$this->assertSame('assistant', $out[0]['role']);
		$this->assertArrayHasKey('tool_calls', $out[0]);
		$this->assertSame('123', $out[0]['tool_calls'][0]['id']);

		$this->assertSame('tool', $out[1]['role']);
		$this->assertSame('123', $out[1]['tool_call_id']);
		$this->assertSame('{"ok":true}', $out[1]['content']);
	}

	public function testNormalizeAssistantToolCallsAreNormalizedAndArgumentsJsonEncoded(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g13');

		$out = $this->callNormalize($r, [[
			'role' => 'assistant',
			'content' => '',
			'tool_calls' => [
				[
					'id' => 'call_1',
					'function' => [
						'name' => 'sum',
						'arguments' => ['a' => 1, 'b' => 2],
					],
				],
				[
					// invalid -> skipped
					'function' => ['name' => 'noop'],
				],
			],
		]]);

		$this->assertCount(1, $out);
		$this->assertSame('assistant', $out[0]['role']);
		$this->assertArrayHasKey('tool_calls', $out[0]);
		$this->assertCount(1, $out[0]['tool_calls']);

		$call = $out[0]['tool_calls'][0];
		$this->assertSame('call_1', $call['id']);
		$this->assertSame('function', $call['type']);
		$this->assertSame('sum', $call['function']['name']);
		$this->assertSame('{"a":1,"b":2}', $call['function']['arguments']);
	}

	public function testStreamParsesSseDataCallsOnDataOnMetaAndToolcalls(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, $this->providerRequestEvents(), 'g14');

		$r2 = new GenericChatModelAgentResourceTestDouble4($resolver, $this->providerRequestEvents(), 'g14x');

		$deltas = [];
		$meta = [];

		$chunk =
			"data: {\"choices\":[{\"delta\":{\"content\":\"Hel\"}}]}\n" .
			"data: {\"choices\":[{\"delta\":{\"content\":\"lo\"}}]}\n" .
			"data: {\"choices\":[{\"delta\":{\"tool_calls\":[{\"id\":\"tc1\"}]}}]}\n" .
			"data: {\"choices\":[{\"delta\":{},\"finish_reason\":\"stop\"}]}\n" .
			"data: [DONE]\n";

		$len = $r2->runWriteFunction($chunk,
			function (string $d) use (&$deltas): void { $deltas[] = $d; },
			function (array $m) use (&$meta): void { $meta[] = $m; }
		);

		$this->assertSame(strlen($chunk), $len);
		$this->assertSame(['Hel', 'lo'], $deltas);

		$this->assertCount(3, $meta);
		$this->assertSame('toolcall', $meta[0]['event']);
		$this->assertSame([['id' => 'tc1']], $meta[0]['tool_calls']);

		$this->assertSame('meta', $meta[1]['event']);
		$this->assertSame('stop', $meta[1]['finish_reason']);
		$this->assertIsArray($meta[1]['full']);

		$this->assertSame('done', $meta[2]['event']);
	}
}

require_once __DIR__ . '/GenericChatModelAgentResourceTestDouble1.php';

require_once __DIR__ . '/GenericChatModelAgentResourceTestDouble2.php';

require_once __DIR__ . '/GenericChatModelAgentResourceTestDouble3.php';

require_once __DIR__ . '/GenericChatModelAgentResourceTestDouble4.php';

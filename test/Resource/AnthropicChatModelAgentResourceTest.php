<?php declare(strict_types=1);

/**
 * Filename: plugin/MissionBay/test/Resource/AnthropicChatModelAgentResourceTest.php
 */

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\AnthropicChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;
use AssistantFoundation\Api\IAiChatModel;

class AnthropicChatModelAgentResourceTest extends TestCase {

	private function makeResolver(bool $returnNull = false): IAgentConfigValueResolver {
		return new AnthropicChatModelAgentResourceTestDouble1($returnNull);
	}

	private function providerRequestEvents(): AiProviderRequestEventDispatcher {
		return new AiProviderRequestEventDispatcher(new EventManager());
	}

	public function testImplementsAiChatModelInterface(): void {
		$res = new AnthropicChatModelAgentResource($this->makeResolver(), $this->providerRequestEvents(), 'id');
		$this->assertInstanceOf(IAiChatModel::class, $res);
	}

	public function testGetNameAndDescription(): void {
		$res = new AnthropicChatModelAgentResource($this->makeResolver(), $this->providerRequestEvents(), 'id');

		$this->assertSame(
			'anthropicchatmodelagentresource',
			AnthropicChatModelAgentResource::getName()
		);

		$this->assertSame(
			'Connects to Anthropic Claude 3.x message API.',
			$res->getDescription()
		);
	}

	public function testSetConfigAppliesDefaults(): void {
		$res = new AnthropicChatModelAgentResource($this->makeResolver(true), $this->providerRequestEvents(), 'id');
		$res->setConfig([]);

		$opts = $res->getOptions();

		$this->assertSame('claude-3-haiku-20240307', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.anthropic.com/v1/messages', $opts['endpoint']);
		$this->assertSame(0.3, $opts['temperature']);
		$this->assertSame(1024, $opts['maxtokens']);
	}

	public function testSetOptionsMerges(): void {
		$res = new AnthropicChatModelAgentResource($this->makeResolver(), $this->providerRequestEvents(), 'id');

		$res->setConfig(['apikey' => 'key']);
		$res->setOptions(['temperature' => 0.9]);

		$opts = $res->getOptions();

		$this->assertSame('key', $opts['apikey']);
		$this->assertSame(0.9, $opts['temperature']);
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$res = new AnthropicChatModelAgentResource($this->makeResolver(), $this->providerRequestEvents(), 'id');
		$res->setConfig([]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key');

		$res->raw([['role' => 'user', 'content' => 'hi']]);
	}

	public function testStreamEmitsDataAndMeta(): void {
		$res = new AnthropicChatModelAgentResourceTestDouble2($this->makeResolver(), $this->providerRequestEvents(), 'id');

		// NOTE: we don't need apikey/curl at all for this parser unit test.
		$text = '';
		$meta = [];

		$chunks = [
			"data: " . json_encode([
				'type' => 'content_block_delta',
				'delta' => ['text' => 'Hel']
			]) . "\n",
			"data: " . json_encode([
				'type' => 'content_block_delta',
				'delta' => ['text' => 'lo']
			]) . "\n",
			"data: " . json_encode([
				'type' => 'message_delta',
				'delta' => ['stop_reason' => 'end']
			]) . "\n",
			"data: [DONE]\n",
		];

		$res->runParser(
			$chunks,
			function (string $chunk) use (&$text): void { $text .= $chunk; },
			function (array $m) use (&$meta): void { $meta[] = $m; }
		);

		$this->assertSame('Hello', $text);
		$this->assertSame('meta', $meta[0]['event']);
		$this->assertSame('end', $meta[0]['stop_reason']);
		$this->assertSame(['event' => 'done'], $meta[1]);
	}
}

require_once __DIR__ . '/AnthropicChatModelAgentResourceTestDouble1.php';

require_once __DIR__ . '/AnthropicChatModelAgentResourceTestDouble2.php';

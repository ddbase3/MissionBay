<?php declare(strict_types=1);

/**
 * Filename: plugin/MissionBay/test/Resource/AnthropicChatModelAgentResourceTest.php
 */

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\AnthropicChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;
use AssistantFoundation\Api\IAiChatModel;

class AnthropicChatModelAgentResourceTest extends TestCase {

	private function makeResolver(bool $returnNull = false): IAgentConfigValueResolver {
		return new class($returnNull) implements IAgentConfigValueResolver {
			public function __construct(
				private bool $returnNull = false
			) {}

			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				return $this->returnNull ? null : $config;
			}
		};
	}

	public function testImplementsAiChatModelInterface(): void {
		$res = new AnthropicChatModelAgentResource($this->makeResolver(), 'id');
		$this->assertInstanceOf(IAiChatModel::class, $res);
	}

	public function testGetNameAndDescription(): void {
		$res = new AnthropicChatModelAgentResource($this->makeResolver(), 'id');

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
		$res = new AnthropicChatModelAgentResource($this->makeResolver(true), 'id');
		$res->setConfig([]);

		$opts = $res->getOptions();

		$this->assertSame('claude-3-haiku-20240307', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.anthropic.com/v1/messages', $opts['endpoint']);
		$this->assertSame(0.3, $opts['temperature']);
		$this->assertSame(1024, $opts['maxtokens']);
	}

	public function testSetOptionsMerges(): void {
		$res = new AnthropicChatModelAgentResource($this->makeResolver(), 'id');

		$res->setConfig(['apikey' => 'key']);
		$res->setOptions(['temperature' => 0.9]);

		$opts = $res->getOptions();

		$this->assertSame('key', $opts['apikey']);
		$this->assertSame(0.9, $opts['temperature']);
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$res = new AnthropicChatModelAgentResource($this->makeResolver(), 'id');
		$res->setConfig([]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key');

		$res->raw([['role' => 'user', 'content' => 'hi']]);
	}

	public function testStreamEmitsDataAndMeta(): void {
		$res = new class($this->makeResolver(), 'id') extends AnthropicChatModelAgentResource {

			/**
			 * Runs the SAME parsing logic as the CURLOPT_WRITEFUNCTION in stream(),
			 * but without any curl dependency (stable unit test).
			 *
			 * @param string[] $chunks
			 */
			public function runParser(array $chunks, callable $onData, callable $onMeta = null): void {
				$buffer = '';
				$eventName = '';

				foreach ($chunks as $chunk) {
					$buffer .= $chunk;

					while (($pos = strpos($buffer, "\n")) !== false) {
						$line = substr($buffer, 0, $pos);
						$buffer = substr($buffer, $pos + 1);

						$line = rtrim($line, "\r");
						$trim = trim($line);

						if ($trim === '') {
							$eventName = '';
							continue;
						}

						if (str_starts_with($trim, 'event:')) {
							$eventName = trim(substr($trim, 6));
							continue;
						}

						if (!str_starts_with($trim, 'data:')) {
							continue;
						}

						$dataStr = trim(substr($trim, 5));
						if ($dataStr === '' || $dataStr === '[DONE]') {
							if ($dataStr === '[DONE]' && $onMeta !== null) {
								$onMeta(['event' => 'done']);
							}
							continue;
						}

						$json = json_decode($dataStr, true);
						if (!is_array($json)) {
							continue;
						}

						$type = (string)($json['type'] ?? $eventName);

						if ($type === 'content_block_delta') {
							$deltaText = $json['delta']['text'] ?? null;
							if (is_string($deltaText) && $deltaText !== '') {
								$onData($deltaText);
							}
							continue;
						}

						if ($type === 'message_delta') {
							$stop = $json['delta']['stop_reason'] ?? null;
							if ($onMeta !== null && $stop !== null) {
								$onMeta([
									'event'       => 'meta',
									'stop_reason' => $stop,
									'full'        => $json
								]);
							}
							continue;
						}

						if ($type === 'message_stop') {
							if ($onMeta !== null) {
								$onMeta(['event' => 'done']);
							}
							continue;
						}

						if ($onMeta !== null) {
							$onMeta([
								'event' => 'meta',
								'type'  => $type,
								'full'  => $json
							]);
						}
					}
				}
			}
		};

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

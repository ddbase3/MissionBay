<?php declare(strict_types=1);

namespace MissionBay\Resource;

/**
 * Namespaced cURL function overrides to fully unit test without real HTTP calls.
 * PHP resolves curl_* inside MissionBay\Resource\* to these functions first.
 */
final class FakeCurlState {

	public static int $errno = 0;
	public static string $error = '';
	public static int $httpCode = 200;
	public static mixed $execResult = null;

	/** @var string[] */
	public static array $streamChunks = [];

	private static array $options = [];

	public static function reset(): void {
		self::$errno = 0;
		self::$error = '';
		self::$httpCode = 200;
		self::$execResult = null;
		self::$streamChunks = [];
		self::$options = [];
	}

	public static function setOpt(int $opt, mixed $value): void {
		self::$options[$opt] = $value;
	}

	public static function getOpt(int $opt): mixed {
		return self::$options[$opt] ?? null;
	}
}

function curl_init($url) {
	return (object)['endpoint' => $url];
}

function curl_setopt($ch, $option, $value) {
	FakeCurlState::setOpt((int)$option, $value);
	return true;
}

function curl_exec($ch) {
	$writeFn = FakeCurlState::getOpt(CURLOPT_WRITEFUNCTION);

	if (is_callable($writeFn)) {
		foreach (FakeCurlState::$streamChunks as $chunk) {
			$writeFn($ch, $chunk);
		}
		return true;
	}

	return FakeCurlState::$execResult;
}

function curl_errno($ch) {
	return FakeCurlState::$errno;
}

function curl_error($ch) {
	return FakeCurlState::$error;
}

function curl_getinfo($ch, $opt) {
	if ($opt === CURLINFO_HTTP_CODE) {
		return FakeCurlState::$httpCode;
	}
	return null;
}

function curl_close($ch) {
	return;
}


namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\AnthropicChatModelAgentResource;
use MissionBay\Resource\FakeCurlState;
use MissionBay\Api\IAgentConfigValueResolver;
use AssistantFoundation\Api\IAiChatModel;

class AnthropicChatModelAgentResourceTest extends TestCase {

	protected function setUp(): void {
		FakeCurlState::reset();
	}

	public function testImplementsAiChatModelInterface(): void {
		$res = new AnthropicChatModelAgentResource(new ConfigResolverStub(), 'id');
		$this->assertInstanceOf(IAiChatModel::class, $res);
	}

	public function testGetNameAndDescription(): void {
		$res = new AnthropicChatModelAgentResource(new ConfigResolverStub(), 'id');

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
		$resolver = new ConfigResolverStub(true);
		$res = new AnthropicChatModelAgentResource($resolver, 'id');

		$res->setConfig([]);

		$opts = $res->getOptions();

		$this->assertSame('claude-3-haiku-20240307', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.anthropic.com/v1/messages', $opts['endpoint']);
		$this->assertSame(0.3, $opts['temperature']);
		$this->assertSame(1024, $opts['maxtokens']);
	}

	public function testSetOptionsMerges(): void {
		$res = new AnthropicChatModelAgentResource(new ConfigResolverStub(), 'id');

		$res->setConfig(['apikey' => 'key']);
		$res->setOptions(['temperature' => 0.9]);

		$opts = $res->getOptions();

		$this->assertSame('key', $opts['apikey']);
		$this->assertSame(0.9, $opts['temperature']);
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$res = new AnthropicChatModelAgentResource(new ConfigResolverStub(), 'id');
		$res->setConfig([]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key');

		$res->raw([['role' => 'user', 'content' => 'hi']]);
	}

	public function testRawSuccess(): void {
		$res = new AnthropicChatModelAgentResource(new ConfigResolverStub(), 'id');
		$res->setConfig(['apikey' => 'key']);

		FakeCurlState::$execResult = json_encode([
			'content' => [['text' => 'Hello']]
		]);

		$data = $res->raw([['role' => 'user', 'content' => 'hi']]);

		$this->assertSame('Hello', $data['content'][0]['text']);
	}

	public function testChatReturnsText(): void {
		$res = new AnthropicChatModelAgentResource(new ConfigResolverStub(), 'id');
		$res->setConfig(['apikey' => 'key']);

		FakeCurlState::$execResult = json_encode([
			'content' => [['text' => 'Answer']]
		]);

		$this->assertSame('Answer', $res->chat([
			['role' => 'user', 'content' => 'hi']
		]));
	}

	public function testStreamEmitsDataAndMeta(): void {
		$res = new AnthropicChatModelAgentResource(new ConfigResolverStub(), 'id');
		$res->setConfig(['apikey' => 'key']);

		FakeCurlState::$streamChunks = [
			"data: " . json_encode(['delta' => ['text' => 'Hel']]) . "\n",
			"data: " . json_encode(['delta' => ['text' => 'lo'], 'stop_reason' => 'end']) . "\n",
			"data: [DONE]\n",
		];

		$text = '';
		$meta = [];

		$res->stream(
			[['role' => 'user', 'content' => 'hi']],
			[],
			function (string $chunk) use (&$text): void {
				$text .= $chunk;
			},
			function (array $m) use (&$meta): void {
				$meta[] = $m;
			}
		);

		$this->assertSame('Hello', $text);
		$this->assertSame('meta', $meta[0]['event']);
		$this->assertSame('end', $meta[0]['stop_reason']);
		$this->assertSame(['event' => 'done'], $meta[1]);
	}
}

class ConfigResolverStub implements IAgentConfigValueResolver {

	public function __construct(
		private bool $returnNull = false
	) {}

	public function resolveValue(array|string|null $config): mixed {
		return $this->returnNull ? null : $config;
	}
}

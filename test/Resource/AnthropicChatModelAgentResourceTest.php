<?php declare(strict_types=1);

namespace MissionBay\Resource;

/**
 * Global namespaced cURL function overrides for ALL MissionBay\Resource tests.
 * Guarded to avoid redeclare errors when multiple test files are loaded.
 */
if (!class_exists(__NAMESPACE__ . '\\CurlStub')) {
	final class CurlStub {

		public static int $errno = 0;
		public static string $error = '';
		public static int $httpCode = 200;

		public static mixed $execResult = null;

		/** @var string[] */
		public static array $streamChunks = [];

		/** @var array<int,mixed> */
		private static array $options = [];

		/** @var array<int,array{endpoint:string,options:array<int,mixed>}> */
		private static array $handles = [];

		private static int $nextHandle = 1;

		/** @var array<int,array{http:int,response:mixed,errno:int,error:string}> */
		private static array $queue = [];

		/** @var array<string,mixed> */
		public static array $lastRequest = [];

		public static function reset(): void {
			self::$errno = 0;
			self::$error = '';
			self::$httpCode = 200;
			self::$execResult = null;
			self::$streamChunks = [];
			self::$options = [];
			self::$handles = [];
			self::$nextHandle = 1;
			self::$queue = [];
			self::$lastRequest = [];
		}

		public static function setOpt(int $opt, mixed $value): void {
			self::$options[$opt] = $value;
		}

		public static function getOpt(int $opt): mixed {
			return self::$options[$opt] ?? null;
		}

		public static function queueResponse(int $http, mixed $response, int $errno = 0, string $error = ''): void {
			self::$queue[] = [
				'http' => $http,
				'response' => $response,
				'errno' => $errno,
				'error' => $error,
			];
		}

		public static function consumeResponse(): array {
			if (!empty(self::$queue)) {
				return array_shift(self::$queue);
			}
			return [
				'http' => self::$httpCode,
				'response' => self::$execResult,
				'errno' => self::$errno,
				'error' => self::$error,
			];
		}

		public static function registerHandle(string $endpoint): object {
			$id = self::$nextHandle++;
			$h = (object)['__curl_id' => $id, 'endpoint' => $endpoint];
			self::$handles[$id] = ['endpoint' => $endpoint, 'options' => []];
			return $h;
		}

		public static function setHandleOpt(object $ch, int $opt, mixed $value): void {
			$id = (int)($ch->__curl_id ?? 0);
			if ($id > 0 && isset(self::$handles[$id])) {
				self::$handles[$id]['options'][$opt] = $value;
			}
			self::setOpt($opt, $value);
		}

		public static function getHandleOptions(object $ch): array {
			$id = (int)($ch->__curl_id ?? 0);
			return ($id > 0 && isset(self::$handles[$id])) ? self::$handles[$id]['options'] : [];
		}

		public static function inferMethod(array $opts): string {
			$custom = $opts[CURLOPT_CUSTOMREQUEST] ?? null;
			if (is_string($custom) && $custom !== '') {
				return strtoupper($custom);
			}
			$post = $opts[CURLOPT_POST] ?? null;
			if ($post) return 'POST';
			return 'GET';
		}

		public static function rememberLastRequest(object $ch, array $opts, int $http, int $errno, string $error): void {
			self::$lastRequest = [
				'url' => (string)($ch->endpoint ?? ''),
				'method' => self::inferMethod($opts),
				'headers' => $opts[CURLOPT_HTTPHEADER] ?? [],
				'body' => $opts[CURLOPT_POSTFIELDS] ?? null,
				'options' => $opts,
				'http' => $http,
				'errno' => $errno,
				'error' => $error,
			];
		}
	}
}

/**
 * Backward-compatible wrapper for existing Anthropic tests.
 * Explicit static forwarding – NO magic methods.
 */
if (!class_exists(__NAMESPACE__ . '\\FakeCurlState')) {
	final class FakeCurlState {

		public static int $errno = 0;
		public static string $error = '';
		public static int $httpCode = 200;
		public static mixed $execResult = null;

		/** @var string[] */
		public static array $streamChunks = [];

		public static function reset(): void {
			CurlStub::reset();

			// keep mirror vars in sync for legacy direct property writes
			self::$errno = CurlStub::$errno;
			self::$error = CurlStub::$error;
			self::$httpCode = CurlStub::$httpCode;
			self::$execResult = CurlStub::$execResult;
			self::$streamChunks = CurlStub::$streamChunks;
		}

		/**
		 * Legacy tests might write FakeCurlState::$execResult etc.
		 * We sync those values into CurlStub right before a request via curl_exec().
		 */
		public static function syncToStub(): void {
			CurlStub::$errno = self::$errno;
			CurlStub::$error = self::$error;
			CurlStub::$httpCode = self::$httpCode;
			CurlStub::$execResult = self::$execResult;
			CurlStub::$streamChunks = self::$streamChunks;
		}

		public static function setOpt(int $opt, mixed $value): void {
			CurlStub::setOpt($opt, $value);
		}

		public static function getOpt(int $opt): mixed {
			return CurlStub::getOpt($opt);
		}

		public static function queueResponse(int $http, mixed $response, int $errno = 0, string $error = ''): void {
			CurlStub::queueResponse($http, $response, $errno, $error);
		}
	}
}

// ---- Guarded function overrides (avoid "Cannot redeclare") ----

if (!function_exists(__NAMESPACE__ . '\\curl_init')) {
	function curl_init($url) {
		return CurlStub::registerHandle((string)$url);
	}
}

if (!function_exists(__NAMESPACE__ . '\\curl_setopt')) {
	function curl_setopt($ch, $option, $value) {
		CurlStub::setHandleOpt($ch, (int)$option, $value);
		return true;
	}
}

if (!function_exists(__NAMESPACE__ . '\\curl_exec')) {
	function curl_exec($ch) {
		// sync legacy FakeCurlState -> CurlStub for tests that write FakeCurlState::$...
		if (class_exists(__NAMESPACE__ . '\\FakeCurlState')) {
			/** @var class-string $cls */
			$cls = __NAMESPACE__ . '\\FakeCurlState';
			if (method_exists($cls, 'syncToStub')) {
				$cls::syncToStub();
			}
		}

		$opts = CurlStub::getHandleOptions($ch);
		$writeFn = $opts[CURLOPT_WRITEFUNCTION] ?? CurlStub::getOpt(CURLOPT_WRITEFUNCTION);

		$resp = CurlStub::consumeResponse();

		// store for curl_getinfo / curl_errno / curl_error and assertions
		CurlStub::$httpCode = (int)$resp['http'];
		CurlStub::$errno = (int)$resp['errno'];
		CurlStub::$error = (string)$resp['error'];

		CurlStub::rememberLastRequest($ch, $opts, CurlStub::$httpCode, CurlStub::$errno, CurlStub::$error);

		// Streaming path: simulate SSE by feeding chunks into WRITEFUNCTION
		if (is_callable($writeFn)) {
			foreach (CurlStub::$streamChunks as $chunk) {
				$writeFn($ch, $chunk);
			}
			if (CurlStub::$errno !== 0) {
				return false;
			}
			return true;
		}

		// Non-streaming: return response, or false on error
		if (CurlStub::$errno !== 0) {
			return false;
		}
		return $resp['response'];
	}
}

if (!function_exists(__NAMESPACE__ . '\\curl_errno')) {
	function curl_errno($ch) {
		return CurlStub::$errno;
	}
}

if (!function_exists(__NAMESPACE__ . '\\curl_error')) {
	function curl_error($ch) {
		return CurlStub::$error;
	}
}

if (!function_exists(__NAMESPACE__ . '\\curl_getinfo')) {
	function curl_getinfo($ch, $opt) {
		if ($opt === CURLINFO_HTTP_CODE) {
			return CurlStub::$httpCode;
		}
		return null;
	}
}

if (!function_exists(__NAMESPACE__ . '\\curl_close')) {
	function curl_close($ch) {
		return;
	}
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

	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		return $this->returnNull ? null : $config;
	}
}

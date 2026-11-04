<?php declare(strict_types=1);

namespace MissionBay\Test\Speech;

use AssistantFoundation\Dto\RealtimeSpeechToTextSessionRequest;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Service\ServiceConfig;
use MissionBay\Speech\MistralSpeechToTextDriver;
use PHPUnit\Framework\TestCase;

final class MistralSpeechToTextDriverBehaviorTest extends TestCase {

	private function call(string $method, mixed ...$arguments): mixed {
		return (new \ReflectionMethod(MistralSpeechToTextDriver::class, $method))->invoke(new MistralSpeechToTextDriver(), ...$arguments);
	}

	public function testDriverIdentityMatchesConfiguredServiceDriver(): void {
		$driver = new MistralSpeechToTextDriver();
		$this->assertSame('mistralspeechtotextdriver', $driver::getName());
		$this->assertSame('mistral-stt', $driver->getDriver());
	}

	public function testSecureHttpEndpointBecomesSecureWebSocket(): void {
		$this->assertSame('wss://api.example.test/v1/audio/transcriptions/realtime?model=model%2Fone',
			$this->call('buildWebSocketEndpoint', 'https://api.example.test/', 'model/one'));
	}

	public function testHttpEndpointBecomesWebSocket(): void {
		$this->assertSame('ws://api.example.test/v1/audio/transcriptions/realtime?model=model-a',
			$this->call('buildWebSocketEndpoint', 'http://api.example.test', 'model-a'));
	}

	public function testVocabularyListDropsInvalidTokensAndDuplicates(): void {
		$this->assertSame(['ILIAS', 'BASE3'], $this->call('normalizeStringList', ' ILIAS,BASE3,ILIAS,<unsafe> '));
	}

	public function testUnsupportedVocabularyShapeIsEmpty(): void {
		$this->assertSame([], $this->call('normalizeStringList', new \stdClass()));
	}

	public function testEmptyAuthorizationHeaderUsesBearerDefault(): void {
		$this->assertSame('Authorization', $this->call('normalizeAuthHeaderName', ' '));
	}

	public function testUnsafeAuthenticationHeaderIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('normalizeAuthHeaderName', "Authorization\nEvil");
	}

	public function testPositiveStreamingDelayIsParsed(): void {
		$this->assertSame(2400, $this->call('positiveInt', '2400', 'streaming delay'));
	}

	public function testZeroStreamingDelayIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('positiveInt', 0, 'streaming delay');
	}

	public function testEpochExpiryIsNormalizedToIso8601(): void {
		$this->assertSame(gmdate('c', 1700000000), $this->call('normalizeExpiration', '1700000000'));
	}

	public function testInvalidExpiryIsEmpty(): void {
		$this->assertSame('', $this->call('normalizeExpiration', 'not a date'));
	}

	public function testEarliestExpiryWins(): void {
		$this->assertSame('2025-01-01T00:00:00+00:00', $this->call('earliestExpiration', '2026-01-01T00:00:00+00:00', '2025-01-01T00:00:00+00:00'));
	}

	public function testValidJsonResultIsAccepted(): void {
		$this->assertSame(['token' => 'one'], $this->call('decodeJsonResponse', '{"token":"one"}', 200, 'Mistral'));
	}

	public function testProviderErrorsAreRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('decodeJsonResponse', '{"message":"unauthorized"}', 401, 'Mistral');
	}

	public function testInvalidJsonIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('decodeJsonResponse', 'invalid', 200, 'Mistral');
	}

	public function testMissingRealtimeBaseUrlFailsBeforeNetwork(): void {
		$service = new ServiceConfig('svc', 'Service', 'stt', 'connection', 'mistral-stt', 'model-one', true);
		$connection = new ConnectionConfig('connection', 'Connection', 'http', 'http', '', 'bearer', 'fixed', 'secret', 30, 'global', true);
		$this->expectException(\RuntimeException::class);
		(new MistralSpeechToTextDriver())->createSession($service, $connection, 'secret', new RealtimeSpeechToTextSessionRequest('svc'));
	}
}

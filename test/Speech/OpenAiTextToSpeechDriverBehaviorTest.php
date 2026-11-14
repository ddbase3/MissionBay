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

namespace Base3\MissionBay\Test\Speech;

use Base3\AssistantFoundation\Dto\TextToSpeechRequest;
use Base3\MissionBay\Connection\ConnectionConfig;
use Base3\MissionBay\Service\ServiceConfig;
use Base3\MissionBay\Speech\OpenAiTextToSpeechDriver;
use PHPUnit\Framework\TestCase;

final class OpenAiTextToSpeechDriverBehaviorTest extends TestCase {

	private function call(string $method, mixed ...$arguments): mixed {
		return (new \ReflectionMethod(OpenAiTextToSpeechDriver::class, $method))->invoke(new OpenAiTextToSpeechDriver(), ...$arguments);
	}

	private function service(string $model = 'tts-model', array $options = []): ServiceConfig {
		return new ServiceConfig('svc', 'Service', 'tts', 'conn', 'openai-tts', $model, true, $options);
	}

	private function connection(string $endpoint = 'https://api.example.test', string $header = ''): ConnectionConfig {
		return new ConnectionConfig('conn', 'Connection', 'http', 'http', $endpoint, 'bearer', 'fixed', 'secret', 45, 'global', true, [], $header);
	}

	public function testDriverIdentity(): void {
		$driver = new OpenAiTextToSpeechDriver();
		$this->assertSame('openaitexttospeechdriver', $driver::getName());
		$this->assertSame('openai-tts', $driver->getDriver());
	}

	public function testRequestPayloadUsesConfiguredVoiceAndRequestOverrides(): void {
		$prepared = $this->call('prepareRequest', $this->service('speech-v1', ['voice' => 'alloy']), $this->connection(),
			new TextToSpeechRequest('svc', ' Hello ', 'en', ['voice' => 'nova', 'instructions' => 'Speak softly']));
		$this->assertSame('https://api.example.test/v1/audio/speech', $prepared['url']);
		$this->assertSame('speech-v1', $prepared['payload']['model']);
		$this->assertSame('Hello', $prepared['payload']['input']);
		$this->assertSame('nova', $prepared['payload']['voice']);
		$this->assertSame('Speak softly', $prepared['payload']['instructions']);
		$this->assertSame(45, $prepared['timeoutSeconds']);
	}

	public function testUnsupportedFormatFallsBackToMp3(): void {
		$this->assertSame('mp3', $this->call('normalizeResponseFormat', 'raw-nonstandard'));
	}

	public function testSupportedFormatIsCaseInsensitive(): void {
		$this->assertSame('wav', $this->call('normalizeResponseFormat', ' WAV '));
	}

	public function testPreparedPayloadUsesMatchingAudioMimeType(): void {
		$prepared = $this->call('prepareRequest', $this->service(), $this->connection(),
			new TextToSpeechRequest('svc', 'Hello', '', ['responseFormat' => 'flac']));
		$this->assertSame('flac', $prepared['payload']['response_format']);
		$this->assertSame('audio/flac', $prepared['mimeType']);
	}

	public function testInvalidSpeechSpeedUsesDefault(): void {
		$this->assertSame(1.0, $this->call('normalizeSpeed', 'unknown'));
	}

	public function testSpeechSpeedIsClampedToProviderBounds(): void {
		$this->assertSame(0.25, $this->call('normalizeSpeed', 0.01));
		$this->assertSame(4.0, $this->call('normalizeSpeed', 10));
		$this->assertSame(1.5, $this->call('normalizeSpeed', '1.5'));
	}

	public function testStructuredErrorMessageIsExtracted(): void {
		$this->assertSame('rate limited', $this->call('extractErrorMessage', '{"error":{"message":"rate limited"}}'));
	}

	public function testEmptyProviderErrorUsesFallback(): void {
		$this->assertSame('Unknown provider error.', $this->call('extractErrorMessage', '  '));
	}

	public function testEmptyInputIsRejectedBeforeRequest(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('prepareRequest', $this->service(), $this->connection(), new TextToSpeechRequest('svc', '  '));
	}

	public function testMissingModelIsRejectedBeforeRequest(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('prepareRequest', $this->service(''), $this->connection(), new TextToSpeechRequest('svc', 'Hello'));
	}

	public function testMissingVoiceIsRejectedBeforeRequest(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('prepareRequest', $this->service(), $this->connection(), new TextToSpeechRequest('svc', 'Hello', '', ['voice' => '']));
	}

	public function testMissingEndpointIsRejectedBeforeRequest(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('prepareRequest', $this->service(), $this->connection(''), new TextToSpeechRequest('svc', 'Hello'));
	}

	public function testAuthenticationHeaderInjectionIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('prepareRequest', $this->service(), $this->connection('https://api.example.test', "Auth\nInjected"), new TextToSpeechRequest('svc', 'Hello'));
	}
}

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
use Base3\MissionBay\Speech\MistralTextToSpeechDriver;
use PHPUnit\Framework\TestCase;

final class MistralTextToSpeechDriverBehaviorTest extends TestCase {

	private function call(string $method, mixed ...$arguments): mixed {
		return (new \ReflectionMethod(MistralTextToSpeechDriver::class, $method))->invoke(new MistralTextToSpeechDriver(), ...$arguments);
	}

	private function service(string $model = 'speech-model', array $options = []): ServiceConfig {
		return new ServiceConfig('svc', 'Service', 'tts', 'conn', 'mistral-tts', $model, true, $options);
	}

	private function connection(string $baseUrl = 'https://api.example.test', string $header = ''): ConnectionConfig {
		return new ConnectionConfig('conn', 'Connection', 'http', 'http', $baseUrl, 'bearer', 'fixed', 'secret', 30, 'global', true, [], $header);
	}

	public function testDriverIdentity(): void {
		$driver = new MistralTextToSpeechDriver();
		$this->assertSame('mistraltexttospeechdriver', $driver::getName());
		$this->assertSame('mistral-tts', $driver->getDriver());
	}

	public function testRequestPayloadIncludesVoiceIdAndModel(): void {
		$prepared = $this->call('prepareRequest', $this->service('voxtral-model', ['voiceId' => 'default-voice']), $this->connection(),
			new TextToSpeechRequest('svc', ' Read this ', 'en', ['voiceId' => 'new-voice']));
		$this->assertSame('voxtral-model', $prepared['payload']['model']);
		$this->assertSame('Read this', $prepared['payload']['input']);
		$this->assertSame('new-voice', $prepared['payload']['voice_id']);
		$this->assertSame('https://api.example.test/v1/audio/speech', $prepared['url']);
	}

	public function testValidAudioFormatMapsToCorrectMimeType(): void {
		$prepared = $this->call('prepareRequest', $this->service('model', ['voice' => 'speaker']), $this->connection(),
			new TextToSpeechRequest('svc', 'Text', '', ['responseFormat' => 'opus']));
		$this->assertSame('opus', $prepared['payload']['response_format']);
		$this->assertSame('audio/ogg', $prepared['mimeType']);
	}

	public function testUnknownAudioFormatFallsBackToMp3(): void {
		$this->assertSame('mp3', $this->call('normalizeResponseFormat', 'other-format'));
	}

	public function testAudioFormatIgnoresCaseAndWhitespace(): void {
		$this->assertSame('flac', $this->call('normalizeResponseFormat', ' FLAC '));
	}

	public function testNestedProviderErrorMessageIsExtracted(): void {
		$this->assertSame('rate limited', $this->call('extractErrorMessage', '{"error":{"message":"rate limited"}}', 429));
	}

	public function testValidationDetailsAreIncludedInProviderError(): void {
		$error = $this->call('extractErrorMessage', '{"detail":[{"loc":["body","voice_id"],"msg":"missing"}]}', 422);
		$this->assertStringContainsString('voice_id', $error);
		$this->assertStringContainsString('missing', $error);
	}

	public function testEmptyProviderErrorIncludesHttpStatus(): void {
		$this->assertSame('HTTP 503', $this->call('extractErrorMessage', '', 503));
	}

	public function testEmptySpeechTextIsRejectedBeforeRequest(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('prepareRequest', $this->service(), $this->connection(), new TextToSpeechRequest('svc', ' '));
	}

	public function testMissingModelIsRejectedBeforeRequest(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('prepareRequest', $this->service(''), $this->connection(), new TextToSpeechRequest('svc', 'Text'));
	}

	public function testMissingVoiceIdIsRejectedBeforeRequest(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('prepareRequest', $this->service(), $this->connection(), new TextToSpeechRequest('svc', 'Text'));
	}

	public function testMissingEndpointIsRejectedBeforeRequest(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('prepareRequest', $this->service('model', ['voice' => 'voice-one']), $this->connection(''), new TextToSpeechRequest('svc', 'Text'));
	}

	public function testInvalidAuthenticationHeaderIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('prepareRequest', $this->service('model', ['voice' => 'voice-one']), $this->connection('https://api.example.test', 'Bad:Header'), new TextToSpeechRequest('svc', 'Text'));
	}
}

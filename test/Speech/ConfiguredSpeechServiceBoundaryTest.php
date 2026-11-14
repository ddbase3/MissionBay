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

use Base3\AssistantFoundation\Api\ITextToSpeechStream;
use Base3\AssistantFoundation\Dto\RealtimeSpeechToTextSessionRequest;
use Base3\AssistantFoundation\Dto\TextToSpeechRequest;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use Base3\MissionBay\Speech\ConfiguredRealtimeSpeechToTextSessionService;
use Base3\MissionBay\Speech\ConfiguredTextToSpeechService;
use PHPUnit\Framework\TestCase;

final class ConfiguredSpeechServiceBoundaryTest extends TestCase {

	private function resolver(array $services = [], array $connection = []): ConfiguredServiceRuntimeResolver {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturnCallback(static function (string $group, string $id, mixed $default = null) use ($services, $connection): array {
			return match($group) {
				'service-stt', 'service-tts' => $services,
				'connection' => $connection,
				default => []
			};
		});
		return new ConfiguredServiceRuntimeResolver($settings, $this->createStub(IClassMap::class), $this->createStub(IAgentConfigValueResolver::class));
	}

	private function serviceSettings(string $type, string $model = 'speech-v1', bool $enabled = true): array {
		return ['id' => 'speech-one', 'serviceType' => $type, 'connection' => 'connection-one', 'driver' => $type === 'stt' ? 'openai-stt' : 'openai-tts', 'model' => $model, 'enabled' => $enabled];
	}

	public function testRealtimeServiceRejectsAnEmptyServiceId(): void {
		$this->expectException(\RuntimeException::class);
		(new ConfiguredRealtimeSpeechToTextSessionService($this->resolver()))->createSession(new RealtimeSpeechToTextSessionRequest(' !!! '));
	}

	public function testRealtimeServiceRejectsUnknownServiceBeforeProviderCall(): void {
		$this->expectException(\RuntimeException::class);
		(new ConfiguredRealtimeSpeechToTextSessionService($this->resolver()))->createSession(new RealtimeSpeechToTextSessionRequest('not-found'));
	}

	public function testRealtimeServiceRejectsDisabledSettings(): void {
		$this->expectException(\RuntimeException::class);
		(new ConfiguredRealtimeSpeechToTextSessionService($this->resolver($this->serviceSettings('stt', 'speech-v1', false))))
			->createSession(new RealtimeSpeechToTextSessionRequest('speech-one'));
	}

	public function testRealtimeServiceRejectsWrongServiceType(): void {
		$this->expectException(\RuntimeException::class);
		(new ConfiguredRealtimeSpeechToTextSessionService($this->resolver($this->serviceSettings('tts'))))
			->createSession(new RealtimeSpeechToTextSessionRequest('speech-one'));
	}

	public function testRealtimeServiceRequiresModelBeforeResolvingConnection(): void {
		$this->expectException(\RuntimeException::class);
		(new ConfiguredRealtimeSpeechToTextSessionService($this->resolver($this->serviceSettings('stt', '  '))))
			->createSession(new RealtimeSpeechToTextSessionRequest('speech-one'));
	}

	public function testRealtimeServiceRejectsNonBearerConnection(): void {
		$connection = ['type' => 'http', 'baseUrl' => 'https://example.test', 'enabled' => true, 'auth' => ['type' => 'none']];
		$this->expectException(\RuntimeException::class);
		(new ConfiguredRealtimeSpeechToTextSessionService($this->resolver($this->serviceSettings('stt'), $connection)))
			->createSession(new RealtimeSpeechToTextSessionRequest('speech-one'));
	}

	public function testTextToSpeechRejectsEmptyServiceId(): void {
		$this->expectException(\RuntimeException::class);
		(new ConfiguredTextToSpeechService($this->resolver()))->synthesize(new TextToSpeechRequest('???', 'Hello'));
	}

	public function testTextToSpeechRejectsUnknownServiceBeforeProviderCall(): void {
		$this->expectException(\RuntimeException::class);
		(new ConfiguredTextToSpeechService($this->resolver()))->synthesize(new TextToSpeechRequest('missing', 'Hello'));
	}

	public function testTextToSpeechRejectsDisabledSettings(): void {
		$this->expectException(\RuntimeException::class);
		(new ConfiguredTextToSpeechService($this->resolver($this->serviceSettings('tts', 'speech-v1', false))))
			->synthesize(new TextToSpeechRequest('speech-one', 'Hello'));
	}

	public function testTextToSpeechRequiresModelBeforeProviderCall(): void {
		$this->expectException(\RuntimeException::class);
		(new ConfiguredTextToSpeechService($this->resolver($this->serviceSettings('tts', ' '))))
			->synthesize(new TextToSpeechRequest('speech-one', 'Hello'));
	}

	public function testTextToSpeechRejectsNonBearerConnection(): void {
		$connection = ['type' => 'http', 'baseUrl' => 'https://example.test', 'enabled' => true, 'auth' => ['type' => 'none']];
		$this->expectException(\RuntimeException::class);
		(new ConfiguredTextToSpeechService($this->resolver($this->serviceSettings('tts'), $connection)))
			->synthesize(new TextToSpeechRequest('speech-one', 'Hello'));
	}

	public function testTextToSpeechStreamEnforcesSameServiceConfigurationBoundary(): void {
		$stream = $this->createStub(ITextToSpeechStream::class);
		$this->expectException(\RuntimeException::class);
		(new ConfiguredTextToSpeechService($this->resolver()))->stream(new TextToSpeechRequest('missing', 'Hello'), $stream);
	}
}

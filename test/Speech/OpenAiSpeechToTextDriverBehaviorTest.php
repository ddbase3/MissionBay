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

use Base3\AssistantFoundation\Dto\RealtimeSpeechToTextSessionRequest;
use Base3\MissionBay\Connection\ConnectionConfig;
use Base3\MissionBay\Service\ServiceConfig;
use Base3\MissionBay\Speech\OpenAiSpeechToTextDriver;
use PHPUnit\Framework\TestCase;

final class OpenAiSpeechToTextDriverBehaviorTest extends TestCase {

	private function call(string $method, mixed ...$arguments): mixed {
		return (new \ReflectionMethod(OpenAiSpeechToTextDriver::class, $method))->invoke(new OpenAiSpeechToTextDriver(), ...$arguments);
	}

	public function testDriverIdentityMatchesConfiguredServiceDriver(): void {
		$driver = new OpenAiSpeechToTextDriver();
		$this->assertSame('openaispeechtotextdriver', $driver::getName());
		$this->assertSame('openai-stt', $driver->getDriver());
	}

	public function testLanguagesAreNormalizedDeduplicatedAndInvalidEntriesDiscarded(): void {
		$this->assertSame(['de', 'en', 'fr'], $this->call('normalizeLanguages', ' DE-de, en-US, de, fr-FR, auto, INVALID '));
	}

	public function testEmptyLanguagesAreNotSilentlyReplacedInNormalization(): void {
		$this->assertSame([], $this->call('normalizeLanguages', ['auto', ' ', '<de>', null]));
	}

	public function testContextRemovesControlCharacters(): void {
		$this->assertSame('alpha beta', $this->call('normalizeContext', " alpha\x00beta "));
	}

	public function testContextRetainsOnlyLastFourThousandBytes(): void {
		$context = $this->call('normalizeContext', str_repeat('A', 200) . str_repeat('B', 4000));
		$this->assertSame(4000, strlen($context));
		$this->assertSame(str_repeat('B', 4000), $context);
	}

	public function testEmptyContextDoesNotChangePrompt(): void {
		$this->assertSame('Base prompt', $this->call('buildPrompt', 'Base prompt', ''));
	}

	public function testNonEmptyContextIsAppendedToPrompt(): void {
		$prompt = $this->call('buildPrompt', 'Base prompt', 'Prior transcript');
		$this->assertStringContainsString('Base prompt', $prompt);
		$this->assertStringContainsString('Prior transcript', $prompt);
	}

	public function testTranscriptionDelayIsNormalized(): void {
		$this->assertSame('medium', $this->call('normalizeDelay', ' MEDIUM '));
	}

	public function testInvalidTranscriptionDelayIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('normalizeDelay', 'instant');
	}

	public function testNearFieldNoiseReductionIsAccepted(): void {
		$this->assertSame('near_field', $this->call('normalizeNoiseReduction', ' NEAR_FIELD '));
	}

	public function testInvalidNoiseReductionIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('normalizeNoiseReduction', 'none');
	}

	public function testEmptyAuthenticationHeaderUsesStandardName(): void {
		$this->assertSame('Authorization', $this->call('normalizeAuthHeaderName', '  '));
	}

	public function testHeaderInjectionIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('normalizeAuthHeaderName', "Authorization\r\nX-Injected");
	}

	public function testSuccessfulJsonResponseIsReturned(): void {
		$this->assertSame(['value' => 'short-lived'], $this->call('decodeJsonResponse', '{"value":"short-lived"}', 201, 'OpenAI'));
	}

	public function testInvalidJsonResponseIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('decodeJsonResponse', 'not json', 200, 'OpenAI');
	}

	public function testProviderErrorIsNotMisinterpretedAsSuccessfulSession(): void {
		$this->expectException(\RuntimeException::class);
		$this->call('decodeJsonResponse', '{"error":{"message":"rejected"}}', 401, 'OpenAI');
	}

	public function testMissingRealtimeBaseUrlFailsBeforeNetwork(): void {
		$service = new ServiceConfig('svc', 'Service', 'stt', 'connection', 'openai-stt', 'model-one', true);
		$connection = new ConnectionConfig('connection', 'Connection', 'http', 'http', '', 'bearer', 'fixed', 'secret', 30, 'global', true);
		$this->expectException(\RuntimeException::class);
		(new OpenAiSpeechToTextDriver())->createSession($service, $connection, 'secret', new RealtimeSpeechToTextSessionRequest('svc'));
	}
}

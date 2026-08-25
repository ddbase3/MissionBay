<?php declare(strict_types=1);

namespace MissionBay\Test\ServiceDriver;

use MissionBay\Api\ISpeechToTextDriver;
use MissionBay\ServiceDriver\MistralSpeechToTextDriverDefinition;
use PHPUnit\Framework\TestCase;

final class MistralSpeechToTextDriverDefinitionTest extends TestCase {

	public function testDefinitionExposesDualStreamRealtimeDefaults(): void {
		$definition = new MistralSpeechToTextDriverDefinition();
		$defaults = $definition->getDefaultConfig();

		$this->assertSame('mistral-stt', $definition->getDriver());
		$this->assertSame('stt', $definition->getServiceType());
		$this->assertSame(ISpeechToTextDriver::class, $definition->getImplementationInterface());
		$this->assertSame('voxtral-mini-transcribe-realtime-2602', $defaults['model'] ?? null);
		$this->assertSame(['ILIAS'], $defaults['options']['vocabulary'] ?? null);
		$this->assertSame(240, $defaults['options']['fastStreamingDelayMs'] ?? null);
		$this->assertSame(2400, $defaults['options']['slowStreamingDelayMs'] ?? null);
		$this->assertSame(20, $defaults['options']['chunkDurationMs'] ?? null);
		$this->assertSame(12000, $defaults['options']['sessionTimeoutMs'] ?? null);
		$this->assertSame(25000, $defaults['options']['finalizationTimeoutMs'] ?? null);
		$this->assertArrayNotHasKey('realtimeModel', $defaults['options'] ?? []);
		$this->assertArrayNotHasKey('sampleRate', $defaults['options'] ?? []);
	}
}

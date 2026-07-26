<?php declare(strict_types=1);

namespace MissionBay\Test\ServiceDriver;

use MissionBay\ServiceDriver\MistralRealtimeSpeechToTextDriverDefinition;
use PHPUnit\Framework\TestCase;

final class MistralRealtimeSpeechToTextDriverDefinitionTest extends TestCase {

	public function testDefinitionExposesRealtimeDefaults(): void {
		$definition = new MistralRealtimeSpeechToTextDriverDefinition();
		$defaults = $definition->getDefaultConfig();

		$this->assertSame('mistral-realtime-stt', $definition->getDriver());
		$this->assertSame('stt', $definition->getServiceType());
		$this->assertSame('voxtral-mini-transcribe-realtime-2602', $defaults['model'] ?? null);
		$this->assertSame(16000, $defaults['options']['sampleRate'] ?? null);
		$this->assertSame(10000, $defaults['options']['noSpeechTimeoutMs'] ?? null);
	}
}

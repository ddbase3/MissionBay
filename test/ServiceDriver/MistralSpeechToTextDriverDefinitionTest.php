<?php declare(strict_types=1);

namespace MissionBay\Test\ServiceDriver;

use MissionBay\Api\ISpeechToTextDriver;
use MissionBay\ServiceDriver\MistralSpeechToTextDriverDefinition;
use PHPUnit\Framework\TestCase;

final class MistralSpeechToTextDriverDefinitionTest extends TestCase {

	public function testDefinitionExposesCompleteAndRealtimeDefaults(): void {
		$definition = new MistralSpeechToTextDriverDefinition();
		$defaults = $definition->getDefaultConfig();

		$this->assertSame('mistral-stt', $definition->getDriver());
		$this->assertSame('stt', $definition->getServiceType());
		$this->assertSame(ISpeechToTextDriver::class, $definition->getImplementationInterface());
		$this->assertSame('voxtral-mini-latest', $defaults['model'] ?? null);
		$this->assertSame('voxtral-mini-transcribe-realtime-2602', $defaults['options']['realtimeModel'] ?? null);
		$this->assertSame(16000, $defaults['options']['sampleRate'] ?? null);
		$this->assertSame(10000, $defaults['options']['noSpeechTimeoutMs'] ?? null);
	}
}

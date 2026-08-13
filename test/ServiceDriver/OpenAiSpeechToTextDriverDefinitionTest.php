<?php declare(strict_types=1);

namespace MissionBay\Test\ServiceDriver;

use MissionBay\Api\ISpeechToTextDriver;
use MissionBay\ServiceDriver\OpenAiSpeechToTextDriverDefinition;
use PHPUnit\Framework\TestCase;

final class OpenAiSpeechToTextDriverDefinitionTest extends TestCase {

	public function testDefinitionExposesCompleteAndRealtimeDefaults(): void {
		$definition = new OpenAiSpeechToTextDriverDefinition();
		$defaults = $definition->getDefaultConfig();

		$this->assertSame('openai-stt', $definition->getDriver());
		$this->assertSame('stt', $definition->getServiceType());
		$this->assertSame(ISpeechToTextDriver::class, $definition->getImplementationInterface());
		$this->assertSame('gpt-4o-mini-transcribe', $defaults['model'] ?? null);
		$this->assertSame('gpt-4o-mini-transcribe', $defaults['options']['realtimeModel'] ?? null);
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Test\ServiceDriver;

use MissionBay\Api\ISpeechToTextDriver;
use MissionBay\ServiceDriver\OpenAiSpeechToTextDriverDefinition;
use PHPUnit\Framework\TestCase;

final class OpenAiSpeechToTextDriverDefinitionTest extends TestCase {

	public function testDefinitionExposesDirectRealtimeDefaults(): void {
		$definition = new OpenAiSpeechToTextDriverDefinition();
		$defaults = $definition->getDefaultConfig();

		$this->assertSame('openai-stt', $definition->getDriver());
		$this->assertSame('stt', $definition->getServiceType());
		$this->assertSame(ISpeechToTextDriver::class, $definition->getImplementationInterface());
		$this->assertSame('gpt-live-transcribe', $defaults['model'] ?? null);
		$this->assertSame(['de'], $defaults['options']['languages'] ?? null);
		$this->assertSame(['ILIAS'], $defaults['options']['keywords'] ?? null);
		$this->assertSame('low', $defaults['options']['delay'] ?? null);
		$this->assertSame('far_field', $defaults['options']['noiseReduction'] ?? null);
		$this->assertSame(120, $defaults['options']['clientSecretTtlSeconds'] ?? null);
		$this->assertSame(10000, $defaults['options']['finalizationTimeoutMs'] ?? null);
		$this->assertArrayNotHasKey('realtimeModel', $defaults['options'] ?? []);
	}
}

<?php declare(strict_types=1);

use MissionBay\ServiceDriver\MistralTextToSpeechDriverDefinition;
use PHPUnit\Framework\TestCase;

final class MistralTextToSpeechDriverDefinitionTest extends TestCase {

	public function testDefinitionProvidesTtsDefaults(): void {
		$definition = new MistralTextToSpeechDriverDefinition();

		$this->assertSame('mistral-tts', $definition->getDriver());
		$this->assertSame('tts', $definition->getServiceType());
		$this->assertSame('voxtral-mini-tts-2603', $definition->getDefaultConfig()['model']);
		$this->assertArrayHasKey('voice', $definition->getConfigSchema()['properties']);
		$this->assertArrayNotHasKey('voiceId', $definition->getConfigSchema()['properties']);
		$this->assertArrayHasKey('voice', $definition->getDefaultConfig()['options']);
	}
}

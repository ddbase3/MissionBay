<?php declare(strict_types=1);

namespace MissionBay\Test\ServiceDriver;

use MissionBay\ServiceDriver\OpenAiTextToSpeechDriverDefinition;
use PHPUnit\Framework\TestCase;

final class OpenAiTextToSpeechDriverDefinitionTest extends TestCase {

	public function testDefinitionExposesOpenAiDefaults(): void {
		$definition = new OpenAiTextToSpeechDriverDefinition();
		$defaults = $definition->getDefaultConfig();

		$this->assertSame('openai-tts', $definition->getDriver());
		$this->assertSame('tts', $definition->getServiceType());
		$this->assertSame('gpt-4o-mini-tts', $defaults['model'] ?? null);
		$this->assertSame('alloy', $defaults['options']['voice'] ?? null);
		$this->assertSame('mp3', $defaults['options']['responseFormat'] ?? null);
		$this->assertSame(1.0, $defaults['options']['speed'] ?? null);
	}
}

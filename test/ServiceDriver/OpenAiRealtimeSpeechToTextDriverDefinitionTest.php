<?php declare(strict_types=1);

use MissionBay\ServiceDriver\OpenAiRealtimeSpeechToTextDriverDefinition;
use PHPUnit\Framework\TestCase;

final class OpenAiRealtimeSpeechToTextDriverDefinitionTest extends TestCase {

	public function testDefinitionProvidesRealtimeSttDefaults(): void {
		$definition = new OpenAiRealtimeSpeechToTextDriverDefinition();

		$this->assertSame('openai-realtime-stt', $definition->getDriver());
		$this->assertSame('stt', $definition->getServiceType());
		$this->assertSame('gpt-4o-mini-transcribe', $definition->getDefaultConfig()['model']);
	}
}

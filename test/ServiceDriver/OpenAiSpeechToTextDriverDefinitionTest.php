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

namespace Base3\MissionBay\Test\ServiceDriver;

use Base3\MissionBay\Api\ISpeechToTextDriver;
use Base3\MissionBay\ServiceDriver\OpenAiSpeechToTextDriverDefinition;
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

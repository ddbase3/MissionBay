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
use Base3\MissionBay\ServiceDriver\MistralSpeechToTextDriverDefinition;
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

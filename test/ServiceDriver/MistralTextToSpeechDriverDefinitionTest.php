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

use Base3\MissionBay\ServiceDriver\MistralTextToSpeechDriverDefinition;
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

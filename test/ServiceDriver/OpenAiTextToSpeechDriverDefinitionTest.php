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

use Base3\MissionBay\ServiceDriver\OpenAiTextToSpeechDriverDefinition;
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

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

use Base3\MissionBay\ServiceDriver\MistralChatServiceDriverDefinition;
use PHPUnit\Framework\TestCase;

final class MistralChatServiceDriverDefinitionTest extends TestCase {

	public function testDefaultModelIsMistralMedium35(): void {
		$definition = new MistralChatServiceDriverDefinition();
		$schema = $definition->getConfigSchema();
		$defaults = $definition->getDefaultConfig();

		$this->assertSame('mistral-medium-3-5', $schema['properties']['model']['default'] ?? null);
		$this->assertSame('mistral-medium-3-5', $defaults['model'] ?? null);
	}
}

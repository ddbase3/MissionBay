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

namespace Base3\MissionBay\Test\Display;

use PHPUnit\Framework\TestCase;

final class ImageConfigDisplayConnectionBoundaryTest extends TestCase {

	public function testImageFormReferencesAnExistingConnectionWithoutEditingIt(): void {
		$template = file_get_contents(dirname(__DIR__, 2) . '/tpl/Display/ImageConfigDisplay.php');

		$this->assertIsString($template);
		$this->assertStringContainsString('name="connection"', $template);
		$this->assertStringContainsString('name="timeoutSeconds"', $template);
		$this->assertStringContainsString('name="connectTimeoutSeconds"', $template);
		$this->assertStringNotContainsString('name="baseUrl"', $template);
		$this->assertStringNotContainsString('name="authType"', $template);
		$this->assertStringNotContainsString('name="apiKey"', $template);
		$this->assertStringNotContainsString('name="apikey"', $template);
		$this->assertStringNotContainsString('name="secret"', $template);
		$this->assertStringNotContainsString('data-role="connectionhint"', $template);
	}
}

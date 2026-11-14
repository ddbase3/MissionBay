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

namespace Base3\MissionBay\Test\Content;

use PHPUnit\Framework\TestCase;

final class AgentConfigFormSectionFileManagerUrlTest extends TestCase {

	private function renderResourceForm(array $endpoints): string {
		$runtimeAgentConfigForm = [
			'form_id' => 'chatbot_config',
			'chatbot_resources' => [
				'enabled' => true,
				'module_url' => '/assets/filemanager/index.js',
				'css_url' => '/assets/filemanager/filemanager.css',
				'endpoints' => $endpoints
			]
		];

		ob_start();
		try {
			require dirname(__DIR__, 2) . '/tpl/Content/AgentConfigFormSection.php';
			return (string)ob_get_clean();
		} catch (\Throwable $error) {
			ob_end_clean();
			throw $error;
		}
	}

	public function testRelativeStandaloneEndpointUsesDocumentBaseDirectory(): void {
		$url = 'chatbotfilemanageroutput.html?fm_group=chatbot&fm_name=default&fm_action=list';
		$html = $this->renderResourceForm(['list' => $url]);

		$this->assertStringContainsString("baseUrl: new URL('.', document.baseURI).href,", $html);
		$this->assertStringContainsString('"list":"' . $url . '"', $html);
		$this->assertStringNotContainsString('globalThis.location.href', $html);
	}

	public function testAbsoluteIliasEndpointIsPassedThroughUnchanged(): void {
		$url = 'https://ilias.example.test/ilias.php?baseClass=ilAdministrationGUI&fm_action=list';
		$html = $this->renderResourceForm(['list' => $url]);

		$this->assertStringContainsString("baseUrl: new URL('.', document.baseURI).href,", $html);
		$this->assertStringContainsString('"list":"' . $url . '"', $html);
	}
}

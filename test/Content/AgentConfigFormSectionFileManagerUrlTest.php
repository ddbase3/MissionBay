<?php declare(strict_types=1);

namespace MissionBay\Test\Content;

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

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

namespace Base3\MissionBay\Test\Resource\AgentTool\WebFetchText;

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\AgentTool\WebFetchText\WebFetchTextAgentTool;
use PHPUnit\Framework\TestCase;

final class WebFetchTextAgentToolTest extends TestCase {

	public function testDefinitionDeclaresReadOnlyWebFetch(): void {
		$definitions = (new WebFetchTextAgentTool())->getToolDefinitions();
		$this->assertCount(1, $definitions);
		$this->assertSame('web_fetch_text', $definitions[0]['function']['name']);
		$this->assertSame(['url'], $definitions[0]['function']['parameters']['required']);
		$this->assertTrue($definitions[0]['readOnlyHint']);
		$this->assertFalse($definitions[0]['mutation']);
	}

	public function testUnexpectedToolIsRejectedBeforeInstallationOfHandlers(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new WebFetchTextAgentTool())->callTool('http_post', [], new AgentContext());
	}

	public function testMissingUrlIsRejectedWithoutNetworkRequest(): void {
		$tool = new WebFetchTextAgentTool();
		$context = new AgentContext();
		$this->assertSame(['error' => 'Missing parameter: url'], $tool->callTool('web_fetch_text', [], $context));
		$this->assertSame(['error' => 'Missing parameter: url'], $tool->callTool('web_fetch_text', ['url' => '  '], $context));
	}

	public function testNonHttpSchemesAndCredentialsAreInvalid(): void {
		$tool = new WebFetchTextAgentTool();
		foreach (['file:///etc/passwd', 'javascript:alert(1)', 'https://alice:secret@example.org/file'] as $url) {
			$result = $tool->callTool('web_fetch_text', ['url' => $url], new AgentContext());
			$this->assertSame(['url' => $url, 'error' => 'Invalid URL'], $result);
		}
	}

	public function testLocalhostAndItsSubdomainsAreBlocked(): void {
		$tool = new WebFetchTextAgentTool();
		foreach (['http://localhost/', 'https://internal.localhost/admin'] as $url) {
			$result = $tool->callTool('web_fetch_text', ['url' => $url], new AgentContext());
			$this->assertSame(['url' => $url, 'error' => 'Blocked internal or unsafe URL'], $result);
		}
	}

	public function testReservedAndPrivateIpAddressesAreBlocked(): void {
		$tool = new WebFetchTextAgentTool();
		foreach (['http://127.0.0.1/', 'http://10.0.0.1/', 'http://192.168.4.4/health', 'http://169.254.1.2/'] as $url) {
			$result = $tool->callTool('web_fetch_text', ['url' => $url], new AgentContext());
			$this->assertSame(['url' => $url, 'error' => 'Blocked internal or unsafe URL'], $result);
		}
	}
}

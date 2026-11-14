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

namespace Base3\MissionBay\Test\Node\Http;

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Http\HttpRequestNode;
use PHPUnit\Framework\TestCase;

final class HttpRequestNodeTest extends TestCase {

	public function testInvalidAndAbsentUrlsAreRejected(): void {
		$node = new HttpRequestNode('request');
		foreach ([[], ['url' => ''], ['url' => 'a path']] as $input) {
			$this->assertSame(['error' => '[ httprequestnode | request ] Invalid or missing URL'], $node->execute($input, [], new AgentContext()));
		}
	}

	public function testLocalFileResponseUsesDefaultStatusAndContent(): void {
		$file = tempnam(sys_get_temp_dir(), 'missionbay_httpreq_');
		$this->assertTrue(is_string($file));
		try {
			file_put_contents($file, 'test-payload');
			$node = new HttpRequestNode();
			$this->assertSame(['status' => 200, 'body' => 'test-payload'], $node->execute([
				'url' => 'file://' . $file,
				'method' => 'GET',
				'headers' => ['X-Test' => 'safe']
			], [], new AgentContext()));
		} finally {
			unlink($file);
		}
	}

	public function testFailedLocalRequestHasZeroStatus(): void {
		$missing = 'file://' . sys_get_temp_dir() . '/missionbay-absent-' . bin2hex(random_bytes(8));
		$result = (new HttpRequestNode())->execute(['url' => $missing], [], new AgentContext());
		$this->assertSame(0, $result['status']);
		$this->assertStringContainsString('Request failed', $result['error']);
	}
}

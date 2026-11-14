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
use Base3\MissionBay\Node\Http\HttpGetNode;
use PHPUnit\Framework\TestCase;

final class HttpGetNodeTest extends TestCase {

	public function testMissingAndMalformedUrlsAreRejectedBeforeAnyRequest(): void {
		$node = new HttpGetNode('http');
		foreach ([[], ['url' => ''], ['url' => '/relative'], ['url' => 'not a url']] as $input) {
			$this->assertSame(['error' => '[ httpgetnode | http ] Invalid or missing URL'], $node->execute($input, [], new AgentContext()));
		}
	}

	public function testLocalReadableUrlReturnsBytesWithoutNetwork(): void {
		$file = tempnam(sys_get_temp_dir(), 'missionbay_httpget_');
		$this->assertTrue(is_string($file));
		try {
			file_put_contents($file, "local\ncontent");
			$result = (new HttpGetNode())->execute(['url' => 'file://' . $file], [], new AgentContext());
			$this->assertSame(['body' => "local\ncontent"], $result);
		} finally {
			unlink($file);
		}
	}

	public function testUnresolvableLocalUrlReturnsFetchError(): void {
		$missing = 'file://' . sys_get_temp_dir() . '/missionbay-missing-' . bin2hex(random_bytes(8));
		$result = (new HttpGetNode())->execute(['url' => $missing], [], new AgentContext());
		$this->assertStringContainsString('Failed to fetch URL', $result['error']);
	}
}

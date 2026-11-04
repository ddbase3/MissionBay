<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Http;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Http\HttpGetNode;
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

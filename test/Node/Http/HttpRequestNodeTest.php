<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Http;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Http\HttpRequestNode;
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

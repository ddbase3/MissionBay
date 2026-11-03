<?php declare(strict_types=1);

namespace MissionBay\Test\Dto;

use MissionBay\Dto\AgentInfoRequest;
use PHPUnit\Framework\TestCase;

final class AgentInfoRequestTest extends TestCase {

	public function testDefaultAndTopicDiscovery(): void {
		$request = new AgentInfoRequest();
		$this->assertSame('summary', $request->scope);
		$this->assertSame(5, $request->limit);
		$this->assertSame(0, $request->offset);
		$this->assertFalse($request->isTopicDiscovery());
		$discovery = new AgentInfoRequest('topics', 'anything', 'find', 2, 1);
		$this->assertTrue($discovery->isTopicDiscovery());
		$this->assertSame('anything', $discovery->query);
	}
}

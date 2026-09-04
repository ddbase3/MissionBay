<?php declare(strict_types=1);

namespace MissionBay\Test\Service\Assistant;

use MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use PHPUnit\Framework\TestCase;

final class AgentAssistantMessageFactoryTest extends TestCase {

	public function testCancelledMessagesAreNotReusedAsAgentHistory(): void {
		$factory = new AgentAssistantMessageFactory();

		$this->assertFalse($factory->isVisibleHistoryEntry([
			'id' => 'user-one',
			'role' => 'user',
			'content' => 'Old cancelled request',
			'status' => 'cancelled'
		]));
		$this->assertTrue($factory->isVisibleHistoryEntry([
			'id' => 'user-two',
			'role' => 'user',
			'content' => 'Current request'
		]));
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Test\Event;

use AssistantFoundation\Dto\AgentAction;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use PHPUnit\Framework\TestCase;

final class MissionBayAgentActionAuditEventTest extends TestCase {

	public function testAllDeclaredAuditTransitionsAreRecognized(): void {
		$this->assertCount(8, MissionBayAgentActionAuditEvent::getAllowedTypes());
		$this->assertSame(8, count(array_unique(MissionBayAgentActionAuditEvent::getAllowedTypes())));
		foreach (MissionBayAgentActionAuditEvent::getAllowedTypes() as $type) {
			$this->assertSame($type, (new MissionBayAgentActionAuditEvent($type, $this->action()))->getType());
		}
	}

	public function testAuditRetainsReviewedActionAndStructuredContext(): void {
		$action = $this->action();
		$event = new MissionBayAgentActionAuditEvent(MissionBayAgentActionAuditEvent::TYPE_COMMIT_BLOCKED, $action, 'rejected', ['iteration' => 1], ['code' => 'permission'], ' 2025-02-03T00:00:00Z ');
		$this->assertSame($action, $event->getAction());
		$this->assertSame('rejected', $event->getReason());
		$this->assertSame(['iteration' => 1], $event->getTrace());
		$this->assertSame(['code' => 'permission'], $event->getMetadata());
		$this->assertSame('2025-02-03T00:00:00Z', $event->getTimestamp());
	}

	public function testUnrecognizedAuditTransitionIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		new MissionBayAgentActionAuditEvent('commit_skipped', $this->action());
	}

	public function testEmptyTimestampUsesCurrentTime(): void {
		$this->assertNotEmpty((new MissionBayAgentActionAuditEvent(MissionBayAgentActionAuditEvent::TYPE_APPROVAL_REQUESTED, $this->action()))->getTimestamp());
	}

	private function action(): AgentAction {
		return new AgentAction('id-1', AgentAction::TYPE_TOOL_CALL, 'update', ['value' => 4]);
	}
}

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

namespace Base3\MissionBay\Test\Event;

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\MissionBay\Event\MissionBayAgentActionAuditEvent;
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

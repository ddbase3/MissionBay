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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use PHPUnit\Framework\TestCase;

final class AgentActionFingerprintTest extends TestCase {

	public function testIdenticalPayloadsWithDifferentActionIdsHaveSameApprovalFingerprint(): void {
		$fingerprint = new AgentActionFingerprint();
		$one = new AgentAction('first', AgentAction::TYPE_TOOL_CALL, 'delete', ['content' => ['b' => 2, 'a' => 1]]);
		$two = new AgentAction('second', AgentAction::TYPE_TOOL_CALL, 'delete', ['content' => ['a' => 1, 'b' => 2]]);
		$this->assertSame($fingerprint->create($one), $fingerprint->create($two));
		$this->assertSame(64, strlen($fingerprint->create($one)));
	}

	public function testDifferentArgumentValuesOrOrderOfListItemsChangeFingerprint(): void {
		$fingerprint = new AgentActionFingerprint();
		$base = new AgentAction('id', AgentAction::TYPE_TOOL_CALL, 'write', ['ids' => [1, 2]]);
		$changedOrder = new AgentAction('id', AgentAction::TYPE_TOOL_CALL, 'write', ['ids' => [2, 1]]);
		$changedTarget = new AgentAction('id', AgentAction::TYPE_TOOL_CALL, 'delete', ['ids' => [1, 2]]);
		$this->assertNotSame($fingerprint->create($base), $fingerprint->create($changedOrder));
		$this->assertNotSame($fingerprint->create($base), $fingerprint->create($changedTarget));
	}

	public function testNestedMapKeyOrderDoesNotChangeApprovalFingerprint(): void {
		$fingerprint = new AgentActionFingerprint();
		$one = new AgentAction('id', AgentAction::TYPE_TOOL_CALL, 'write', [
			'filters' => ['z' => [5, 6], 'a' => ['c' => 3, 'b' => 2]]
		]);
		$two = new AgentAction('id', AgentAction::TYPE_TOOL_CALL, 'write', [
			'filters' => ['a' => ['b' => 2, 'c' => 3], 'z' => [5, 6]]
		]);
		$this->assertSame($fingerprint->create($one), $fingerprint->create($two));
	}
}

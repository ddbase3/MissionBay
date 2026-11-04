<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentAction;
use MissionBay\Orchestrator\AgentActionFingerprint;
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

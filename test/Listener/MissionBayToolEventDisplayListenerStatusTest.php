<?php declare(strict_types=1);

namespace MissionBay\Test\Listener;

use AssistantFoundation\Dto\AgentAction;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use MissionBay\Listener\MissionBayToolEventDisplayListener;
use PHPUnit\Framework\TestCase;

final class MissionBayToolEventDisplayListenerStatusTest extends TestCase {

	public function testToolExecutionUsesOneOverallStatusAxis(): void {
		$listener = $this->createListener();
		$method = $listener['reflection']->getMethod('resolveToolStatus');

		$this->assertSame('running', $method->invoke($listener['instance'], 'started'));
		$this->assertSame('finished', $method->invoke($listener['instance'], 'finished'));
		$this->assertSame('failed', $method->invoke($listener['instance'], 'failed'));
	}

	public function testApprovalDecisionIsSeparatedFromOverallStatus(): void {
		$listener = $this->createListener();
		$method = $listener['reflection']->getMethod('resolveActionStatus');
		$action = new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'example', []);

		$this->assertSame(
			'waiting_approval',
			$method->invoke(
				$listener['instance'],
				new MissionBayAgentActionAuditEvent(MissionBayAgentActionAuditEvent::TYPE_APPROVAL_REQUESTED, $action),
				''
			)
		);
		$this->assertSame(
			'ready',
			$method->invoke(
				$listener['instance'],
				new MissionBayAgentActionAuditEvent(MissionBayAgentActionAuditEvent::TYPE_APPROVAL_GRANTED, $action),
				'waiting_approval'
			)
		);
		$this->assertSame(
			'denied',
			$method->invoke(
				$listener['instance'],
				new MissionBayAgentActionAuditEvent(MissionBayAgentActionAuditEvent::TYPE_APPROVAL_DENIED, $action),
				'waiting_approval'
			)
		);
	}

	public function testApprovalMetadataKeepsOnlyTheDecisionStatus(): void {
		$listener = $this->createListener();
		$method = $listener['reflection']->getMethod('buildActionMeta');
		$action = new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'example', []);
		$meta = $method->invoke(
			$listener['instance'],
			new MissionBayAgentActionAuditEvent(MissionBayAgentActionAuditEvent::TYPE_APPROVAL_GRANTED, $action),
			[]
		);

		$this->assertSame('granted', $meta['approval']['status']);
		$this->assertSame('approval_granted', $meta['action_audit']['type']);
	}

	public function testCommitOutcomeUsesOverallTerminalStatus(): void {
		$listener = $this->createListener();
		$method = $listener['reflection']->getMethod('resolveActionStatus');
		$action = new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'example', []);

		$this->assertSame(
			'failed',
			$method->invoke(
				$listener['instance'],
				new MissionBayAgentActionAuditEvent(MissionBayAgentActionAuditEvent::TYPE_COMMIT_BLOCKED, $action),
				'ready'
			)
		);
		$this->assertSame(
			'finished',
			$method->invoke(
				$listener['instance'],
				new MissionBayAgentActionAuditEvent(MissionBayAgentActionAuditEvent::TYPE_COMMIT_SUCCEEDED, $action),
				'running'
			)
		);
	}

	public function testPreflightRejectionIsStoredAsFailedActionWithErrorDetails(): void {
		$listener = $this->createListener();
		$statusMethod = $listener['reflection']->getMethod('resolveActionStatus');
		$recordMethod = $listener['reflection']->getMethod('buildActionRecord');
		$action = new AgentAction('call-preflight', AgentAction::TYPE_TOOL_CALL, 'update_course', ['ref_id' => 404]);
		$event = new MissionBayAgentActionAuditEvent(
			MissionBayAgentActionAuditEvent::TYPE_PREFLIGHT_REJECTED,
			$action,
			'No active course exists for ref_id 404.',
			[],
			[
				'error_code' => 'mutation_preflight_rejected',
				'exception_type' => \InvalidArgumentException::class
			]
		);

		$status = $statusMethod->invoke($listener['instance'], $event, '');
		$record = $recordMethod->invoke(
			$listener['instance'],
			$event,
			'agent_action',
			'call-preflight',
			$status,
			'2026-08-31 12:00:00',
			[],
			[]
		);

		$this->assertSame('failed', $record['status']);
		$this->assertSame('No active course exists for ref_id 404.', $record['error_message']);
		$this->assertSame(\InvalidArgumentException::class, $record['error_type']);
		$this->assertSame('mutation_preflight_rejected', $record['error_code']);
		$meta = json_decode((string)$record['meta_json'], true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame('rejected', $meta['preflight']['status']);
	}

	/** @return array{reflection:\ReflectionClass,instance:MissionBayToolEventDisplayListener} */
	private function createListener(): array {
		$reflection = new \ReflectionClass(MissionBayToolEventDisplayListener::class);

		return [
			'reflection' => $reflection,
			'instance' => $reflection->newInstanceWithoutConstructor()
		];
	}
}

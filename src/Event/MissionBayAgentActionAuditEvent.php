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

namespace MissionBay\Event;

use AssistantFoundation\Dto\AgentAction;
use Base3\Event\BaseEvent;

/** Typed audit event for approval, resume, and mutation commit transitions. */
final class MissionBayAgentActionAuditEvent extends BaseEvent {

	private readonly string $timestamp;

	public const TYPE_APPROVAL_REQUESTED = 'approval_requested';
	public const TYPE_APPROVAL_GRANTED = 'approval_granted';
	public const TYPE_APPROVAL_DENIED = 'approval_denied';
	public const TYPE_COMMIT_ALLOWED = 'commit_allowed';
	public const TYPE_COMMIT_BLOCKED = 'commit_blocked';
	public const TYPE_COMMIT_SUCCEEDED = 'commit_succeeded';
	public const TYPE_COMMIT_FAILED = 'commit_failed';

	/**
	 * @param array<string,mixed> $trace
	 * @param array<string,mixed> $metadata
	 */
	public function __construct(
		private readonly string $type,
		private readonly AgentAction $action,
		private readonly string $reason = '',
		private readonly array $trace = [],
		private readonly array $metadata = [],
		string $timestamp = ''
	) {
		if (!in_array($this->type, self::getAllowedTypes(), true)) {
			throw new \InvalidArgumentException('Unsupported agent action audit type: ' . $this->type);
		}
		$this->timestamp = trim($timestamp) !== '' ? trim($timestamp) : gmdate('c');
	}

	public function getType(): string {
		return $this->type;
	}

	public function getAction(): AgentAction {
		return $this->action;
	}

	public function getReason(): string {
		return $this->reason;
	}

	/** @return array<string,mixed> */
	public function getTrace(): array {
		return $this->trace;
	}

	/** @return array<string,mixed> */
	public function getMetadata(): array {
		return $this->metadata;
	}

	public function getTimestamp(): string {
		return $this->timestamp;
	}

	/** @return array<int,string> */
	public static function getAllowedTypes(): array {
		return [
			self::TYPE_APPROVAL_REQUESTED,
			self::TYPE_APPROVAL_GRANTED,
			self::TYPE_APPROVAL_DENIED,
			self::TYPE_COMMIT_ALLOWED,
			self::TYPE_COMMIT_BLOCKED,
			self::TYPE_COMMIT_SUCCEEDED,
			self::TYPE_COMMIT_FAILED
		];
	}
}

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

use Base3\Event\BaseEvent;

/**
 * MissionBayToolStartedEvent
 *
 * Fired before a tool call is executed.
 */
class MissionBayToolStartedEvent extends BaseEvent {

	/**
	 * @param array<string,mixed> $arguments
	 */
	public function __construct(
		private string $nodeId,
		private string $callId,
		private string $toolName,
		private string $label,
		private array $arguments,
		private int $iteration,
		private string $timestamp = ''
	) {
		if ($this->timestamp === '') {
			$this->timestamp = (new \DateTimeImmutable())->format('c');
		}
	}

	public function getNodeId(): string {
		return $this->nodeId;
	}

	public function getCallId(): string {
		return $this->callId;
	}

	public function getToolName(): string {
		return $this->toolName;
	}

	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getArguments(): array {
		return $this->arguments;
	}

	public function getIteration(): int {
		return $this->iteration;
	}

	public function getTimestamp(): string {
		return $this->timestamp;
	}
}

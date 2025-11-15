<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;

interface IAgentEventEmitter extends IBase {

	/**
	 * Set the sink callback receiving emitted events.
	 *
	 * @param callable $sink function(array $event): void
	 */
	public function setSink(callable $sink): void;

	/**
	 * Emit a flow event to the sink.
	 *
	 * @param array<string,mixed> $event
	 */
	public function emitEvent(array $event): void;

	/**
	 * Optional finalizer.
	 */
	public function finish(): void;
}

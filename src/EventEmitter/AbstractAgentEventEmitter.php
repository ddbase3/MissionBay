<?php declare(strict_types=1);

namespace MissionBay\EventEmitter;

use MissionBay\Api\IAgentEventEmitter;

abstract class AbstractAgentEventEmitter implements IAgentEventEmitter {

	protected $sink = null;

	abstract public static function getName(): string;

	public function setSink(callable $sink): void {
		$this->sink = $sink;
	}

	public function finish(): void {
		// no-op by default
	}
}

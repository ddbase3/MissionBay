<?php declare(strict_types=1);

namespace MissionBay\Api;

interface IAgentEventEmitterFactory {

	/**
         * Creates an instance of an agent event emitter based on the given type name.
         *
         * @param string $type Type identifier of the event emitter (typically matches getName()).
         * @return IAgentEventEmitter|null New instance of the event emitter, or null if type is unknown.
	 */
	public function createEventEmitter(string $type): ?IAgentEventEmitter;
}

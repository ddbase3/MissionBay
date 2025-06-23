<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;

/**
 * Interface for a flow-wide execution context.
 * Carries memory, scoped variables, and possibly other flow-specific state.
 */
interface IAgentContext extends IBase {

	/**
	 * Returns the associated memory instance for this context.
	 *
	 * @return IAgentMemory
	 */
	public function getMemory(): IAgentMemory;

	/**
	 * Replaces the memory instance at runtime.
	 * Useful for subflows, testing, or switching memory strategies dynamically.
	 *
	 * @param IAgentMemory $memory
	 */
	public function setMemory(IAgentMemory $memory): void;

	/**
	 * Sets a flow-scoped variable.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function setVar(string $key, mixed $value): void;

	/**
	 * Retrieves a flow-scoped variable.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function getVar(string $key): mixed;
}


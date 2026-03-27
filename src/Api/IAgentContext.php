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

	/**
	 * Forgets a flow-scoped variable.
	 *
	 * @param string $key
	 */
	public function forgetVar(string $key): void;

	/**
	 * Returns a list of all variable keys.
	 *
	 * @return string[]
	 */
	public function listVars(): array;
}


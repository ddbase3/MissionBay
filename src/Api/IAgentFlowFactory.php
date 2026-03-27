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

use MissionBay\Api\IAgentContext;

/**
 * Factory interface for creating agent flows from definitions or templates.
 */
interface IAgentFlowFactory {

	/**
	 * Creates a new agent flow instance from an associative array definition.
	 *
	 * @param string $type Type identifier of the flow (e.g. 'strictflow').
	 * @param array $data Parsed flow structure including nodes and connections.
	 * @param IAgentContext $context Execution context to be injected into the flow.
	 * @return IAgentFlow Fully configured flow ready for execution.
	 */
	public function createFromArray(string $type, array $data, IAgentContext $context): IAgentFlow;

	/**
	 * Creates a new, empty agent flow of the given type.
	 *
	 * @param string $type Type identifier of the flow to initialize.
	 * @param IAgentContext|null $context Optional execution context.
	 * @return IAgentFlow Flow instance without any nodes or connections.
	 */
	public function createEmpty(string $type, ?IAgentContext $context = null): IAgentFlow;
}


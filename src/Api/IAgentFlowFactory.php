<?php declare(strict_types=1);

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


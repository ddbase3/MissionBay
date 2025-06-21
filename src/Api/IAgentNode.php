<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;
use MissionBay\Agent\AgentContext;

interface IAgentNode extends IBase {

	/**
	 * Returns the unique ID of this node within the flow.
	 *
	 * @return string
	 */
	public function getId(): string;

	/**
	 * Returns the list of named input ports (e.g., ["text", "config"]).
	 *
	 * @return string[]
	 */
	public function getInputDefinitions(): array;

	/**
	 * Returns the list of named output ports (e.g., ["result", "error"]).
	 *
	 * @return string[]
	 */
	public function getOutputDefinitions(): array;

	/**
	 * Executes the node's logic using provided inputs and context.
	 *
	 * @param array $inputs Named input values
	 * @param AgentContext $context Flow-wide context object
	 * @return array Named outputs
	 */
	public function execute(array $inputs, AgentContext $context): array;
}


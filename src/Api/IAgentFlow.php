<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * Interface representing a chainable, executable agent flow.
 */
interface IAgentFlow {

	/**
	 * Sets the agent context.
	 *
	 * @param IAgentContext $context Shared context (router, memory, variables)
	 */
	public function setContext(IAgentContext $context): void;

	/**
	 * Executes the flow with given input and context.
	 *
	 * @param array $inputs Initial flow inputs (key => value)
	 * @return array[] List of output maps from terminal nodes
	 */
	public function run(array $inputs): array;

	/**
	 * Adds a node to the flow.
	 *
	 * @param IAgentNode $node
	 */
	public function addNode(IAgentNode $node): void;

	/**
	 * Adds a connection between two nodes.
	 *
	 * @param string $fromNode
	 * @param string $fromOutput
	 * @param string $toNode
	 * @param string $toInput
	 */
	public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput): void;
}


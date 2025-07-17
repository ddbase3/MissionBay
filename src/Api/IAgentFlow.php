<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;

/**
 * Interface for executable agent flows within the MissionBay system.
 * Defines lifecycle management, node linking, data routing, and resource docking.
 */
interface IAgentFlow extends IBase {

	/**
	 * Sets the runtime context used for node execution.
	 *
	 * @param IAgentContext $context Execution context providing runtime dependencies.
	 */
	public function setContext(IAgentContext $context): void;

	/**
	 * Executes the flow using the provided input values.
	 *
	 * @param array $inputs Initial inputs passed to the flow (typically mapped to special '__input__' node).
	 * @return array Output data from terminal nodes or errors encountered during execution.
	 */
	public function run(array $inputs): array;

	/**
	 * Adds a node to the flow. The node must have a unique ID and implement IAgentNode.
	 *
	 * @param IAgentNode $node Node to register within the flow.
	 */
	public function addNode(IAgentNode $node): void;

	/**
	 * Connects one node’s output to another node’s input.
	 *
	 * @param string $fromNode ID of the source node.
	 * @param string $fromOutput Name of the output port on the source node.
	 * @param string $toNode ID of the target node.
	 * @param string $toInput Name of the input port on the target node.
	 */
	public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput): void;

	/**
	 * Sets a fixed input value for a given node. Useful for static configuration or testing.
	 *
	 * @param string $nodeId ID of the target node.
	 * @param string $key Name of the input parameter.
	 * @param mixed $value Value to assign to that input.
	 */
	public function addInitialInput(string $nodeId, string $key, mixed $value): void;

	/**
	 * Returns all initially defined static inputs for the flow.
	 *
	 * @return array Nested array of initial input values indexed by node ID.
	 */
	public function getInitialInputs(): array;

	/**
	 * Returns the list of connections (edges) between node outputs and inputs.
	 *
	 * @return array List of connections, each with fromNode, fromOutput, toNode, toInput.
	 */
	public function getConnections(): array;

	/**
	 * Determines the next node to execute based on the output of the current node.
	 *
	 * @param string $currentNodeId ID of the node just executed.
	 * @param array $output Resulting output data from the current node.
	 * @return string|null ID of the next node to run, or null if none found.
	 */
	public function getNextNode(string $currentNodeId, array $output): ?string;

	/**
	 * Maps output values from one node to input parameters for another node.
	 *
	 * @param string $fromNodeId ID of the source node.
	 * @param string $toNodeId ID of the target node.
	 * @param array $output Output data from the source node.
	 * @return array Associative array of input values for the target node.
	 */
	public function mapInputs(string $fromNodeId, string $toNodeId, array $output): array;

	/**
	 * Checks if a node has all required inputs and is ready for execution.
	 *
	 * @param string $nodeId ID of the node to evaluate.
	 * @param array $currentInputs Inputs currently assigned to the node.
	 * @return bool True if the node is ready to run, false otherwise.
	 */
	public function isReady(string $nodeId, array $currentInputs): bool;

	/**
	 * Registers a globally available resource that can be docked to nodes.
	 *
	 * @param IAgentResource $resource
	 */
	public function addResource(IAgentResource $resource): void;

	/**
	 * Returns all globally available resources in this flow.
	 *
	 * @return IAgentResource[]
	 */
	public function getResources(): array;

	/**
	 * Assigns a resource to a specific node's dock.
	 *
	 * @param string $nodeId
	 * @param string $dockName
	 * @param string $resourceId
	 */
	public function addDockConnection(string $nodeId, string $dockName, string $resourceId): void;

	/**
	 * Returns all dock-to-resource assignments for all nodes.
	 *
	 * @return array<string, array<string, string[]>>
	 */
	public function getAllDockConnections(): array;

	/**
	 * Returns all dock-to-resource assignments for a specific node.
	 *
	 * @param string $nodeId
	 * @return array<string, string[]>
	 */
	public function getDockConnections(string $nodeId): array;
}


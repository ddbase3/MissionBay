<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;

/**
 * Interface for executable agent flows within the MissionBay system.
 * Defines lifecycle management, node linking, data routing, resource docking,
 * and optional event emission for UI/stream integrations.
 */
interface IAgentFlow extends IBase {

	/**
	 * Sets the runtime context used for node execution.
	 *
	 * @param IAgentContext $context Execution context providing runtime dependencies.
	 */
	public function setContext(IAgentContext $context): void;

	/**
	 * Assigns an optional event emitter used for interactive feedback.
	 * If null, event emission is disabled and emitEvent() becomes a no-op.
	 *
	 * @param IAgentEventEmitter|null $emitter
	 */
	public function setEventEmitter(?IAgentEventEmitter $emitter): void;

	/**
	 * Emits an event during flow execution.
	 * Events are forwarded to the assigned IAgentEventEmitter if available.
	 *
	 * @param array $event Arbitrary event payload (status, canvas updates, etc.).
	 */
	public function emitEvent(array $event): void;

	/**
	 * Executes the flow using the provided input values.
	 *
	 * @param array $inputs Initial inputs passed to the flow (mapped to the "__input__" node).
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
	 * Sets a fixed input value for a given node.
	 * Useful for static parameters or test-driven configurations.
	 *
	 * @param string $nodeId
	 * @param string $key
	 * @param mixed $value
	 */
	public function addInitialInput(string $nodeId, string $key, mixed $value): void;

	/**
	 * Returns all initially defined static inputs for the flow.
	 *
	 * @return array
	 */
	public function getInitialInputs(): array;

	/**
	 * Returns the list of connections between node outputs and inputs.
	 *
	 * @return array
	 */
	public function getConnections(): array;

	/**
	 * Determines the next node to execute.
	 *
	 * @param string $currentNodeId
	 * @param array $output
	 * @return string|null
	 */
	public function getNextNode(string $currentNodeId, array $output): ?string;

	/**
	 * Maps output values from one node to input parameters of another.
	 *
	 * @param string $fromNodeId
	 * @param string $toNodeId
	 * @param array $output
	 * @return array
	 */
	public function mapInputs(string $fromNodeId, string $toNodeId, array $output): array;

	/**
	 * Checks if a node has all required inputs and is ready to run.
	 *
	 * @param string $nodeId
	 * @param array $currentInputs
	 * @return bool
	 */
	public function isReady(string $nodeId, array $currentInputs): bool;

	/**
	 * Registers a globally available resource.
	 *
	 * @param IAgentResource $resource
	 */
	public function addResource(IAgentResource $resource): void;

	/**
	 * Returns all globally available resources.
	 *
	 * @return IAgentResource[]
	 */
	public function getResources(): array;

	/**
	 * Assigns a resource to a specific node dock.
	 *
	 * @param string $nodeId
	 * @param string $dockName
	 * @param string $resourceId
	 */
	public function addDockConnection(string $nodeId, string $dockName, string $resourceId): void;

	/**
	 * Returns all dock connections for all nodes.
	 *
	 * @return array<string, array<string, string[]>>
	 */
	public function getAllDockConnections(): array;

	/**
	 * Returns dock connections for a specific node.
	 *
	 * @param string $nodeId
	 * @return array<string, string[]>
	 */
	public function getDockConnections(string $nodeId): array;
}

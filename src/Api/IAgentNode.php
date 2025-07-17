<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Agent\AgentNodeDock;

/**
 * Interface IAgentNode
 *
 * Represents a modular execution unit within a flow-based architecture.
 * Each node declares its input/output ports and optional docked resources.
 * It encapsulates executable logic that transforms inputs into outputs.
 */
interface IAgentNode extends IBase {

	/**
	 * Returns the unique ID of this node within the flow.
	 *
	 * Used for identifying this node in connection mappings.
	 *
	 * @return string Unique node ID (e.g., "reverse1", "fetch_url")
	 */
	public function getId(): string;

	/**
	 * Sets the unique ID of this node within the flow.
	 *
	 * Called by the flow when adding the node to the execution graph.
	 *
	 * @param string $id Unique node ID
	 */
	public function setId(string $id): void;

	/**
	 * Returns a human-readable description of what the node does.
	 *
	 * Used in visual editors, documentation, and dynamic flows (e.g. GPT-based).
	 *
	 * Example: "Fetches a URL via HTTP GET and returns the JSON-decoded result."
	 *
	 * @return string Descriptive summary of the node’s purpose
	 */
	public function getDescription(): string;

	/**
	 * Declares the list of input ports this node accepts.
	 *
	 * Each input is described by an AgentNodePort, specifying:
	 * - name (e.g., "url", "text")
	 * - type (string, int, bool, array, etc.)
	 * - required flag (true/false)
	 * - default value (if missing)
	 * - description (for UI and GPT)
	 *
	 * @return AgentNodePort[] Ordered list of expected inputs
	 */
	public function getInputDefinitions(): array;

	/**
	 * Declares the list of output ports this node produces.
	 *
	 * Outputs can define:
	 * - name (e.g., "result", "error")
	 * - type (string, array, etc.)
	 * - optional default values if not set during execution
	 * - description (used in documentation or flow inspection)
	 *
	 * @return AgentNodePort[] Ordered list of possible outputs
	 */
	public function getOutputDefinitions(): array;

	/**
	 * Declares the docked resource interfaces this node can access.
	 *
	 * Docks describe typed dependencies such as:
	 * - ILogger for logging
	 * - IHttpClient for making HTTP calls
	 * - Custom APIs for file access, storage, etc.
	 *
	 * Each dock specifies:
	 * - name (logical identifier)
	 * - interface (fully qualified interface name)
	 * - maxConnections (number of allowed bindings)
	 * - description (for documentation and UI)
	 *
	 * @return AgentNodeDock[] List of named dock definitions
	 */
	public function getDockDefinitions(): array;

	/**
	 * Optional: Gibt zusätzliche Konfigurationseinstellungen dieses Nodes zurück.
	 *
	 * Diese Konfiguration wird unabhängig von Inputs bereitgestellt – z.B. für interne Parameter
	 * oder GPT-gesteuerte Flows, die zusätzliche Einstellungen benötigen.
	 *
	 * @return array<string, mixed> Frei definierbare Konfiguration
	 */
	public function getConfig(): array;

	/**
	 * Optional: Setzt Konfigurationswerte dieses Nodes.
	 *
	 * Wird z.B. vom Flow-Loader oder einem visuellen Editor aufgerufen.
	 *
	 * @param array<string, mixed> $config
	 */
	public function setConfig(array $config): void;

	/**
	 * Executes the node’s logic using provided inputs, docked resources, and context.
	 *
	 * Inputs are passed as a key-value array.
	 * Resources are grouped by dock name and injected as lists of matching objects.
	 * The context gives access to memory, variables, and flow-wide state.
	 *
	 * @param array<string, mixed> $inputs Named input values
	 * @param array<string, IAgentResource[]> $resources Docked resources by dock name
	 * @param IAgentContext $context Flow-wide execution context
	 * @return array<string, mixed> Named output values
	 */
	public function execute(array $inputs, array $resources, IAgentContext $context): array;
}


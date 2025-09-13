<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;
use MissionBay\Agent\AgentNodeDock;

/**
 * Interface IAgentResource
 *
 * Defines a reusable, pluggable component (e.g. logger, database, memory)
 * that can be attached ("docked") to nodes within a flow.
 * Resources provide logic or services required by nodes during execution.
 */
interface IAgentResource extends IBase {

	/**
	 * Returns the globally unique ID of this resource.
	 * This ID is used to refer to the resource in flow definitions and dock mappings.
	 *
	 * @return string Resource ID
	 */
	public function getId(): string;

	/**
	 * Sets the globally unique ID of this resource.
	 * Called during flow initialization or configuration loading.
	 *
	 * @param string $id Resource ID
	 */
	public function setId(string $id): void;

	/**
	 * Returns a human-readable description of this resource.
	 * This is used in UIs, debugging, and GPT node introspection.
	 *
	 * @return string Description of the resource's purpose
	 */
	public function getDescription(): string;

	/**
	 * Returns the list of external resource docks that this resource uses.
	 * A resource may depend on other services (e.g. a memory may use a logger).
	 * This list is typically empty, but can express dependencies.
	 *
	 * @return AgentNodeDock[] List of docks this resource supports
	 */
	public function getDockDefinitions(): array;

	/**
	 * Optional: Returns the configuration for this resource instance.
	 *
	 * Used to store custom parameters, credentials, limits, etc.
	 *
	 * @return array<string, mixed>
	 */
	public function getConfig(): array;

	/**
	 * Optional: Sets the configuration for this resource instance.
	 *
	 * Called by flow or loader before execution.
	 *
	 * @param array<string, mixed> $config
	 */
	public function setConfig(array $config): void;

	/**
	 * Hook: allows a resource to be initialized with its docked resources.
	 * Works like node execution, but for resources that wrap services.
	 *
	 * @param array<string, IAgentResource[]> $resources
	 * @param IAgentContext $context
	 * @return void
	 */
	public function init(array $resources, IAgentContext $context): void;
}


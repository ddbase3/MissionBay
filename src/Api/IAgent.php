<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;
use MissionBay\Context\AgentContext;

/**
 * Interface IAgent
 *
 * Defines a fully introspectable agent for use within MissionBay flows and MCP-based orchestration.
 */
interface IAgent extends IBase {

	/**
	 * Returns the unique runtime identifier of this agent instance.
	 *
	 * @return string
	 */
	public function getId(): string;

	/**
	 * Sets the unique runtime identifier for this agent instance.
	 *
	 * @param string $id
	 */
	public function setId(string $id): void;

	/**
	 * Assigns the AgentContext to this agent instance.
	 *
	 * @param AgentContext $context
	 */
	public function setContext(AgentContext $context): void;

	/**
	 * Returns the current AgentContext, if any.
	 *
	 * @return AgentContext|null
	 */
	public function getContext(): ?AgentContext;

	/**
	 * Executes the agent logic with the given inputs and returns the output.
	 *
	 * @param array $inputs
	 * @return array
	 */
	public function run(array $inputs = []): array;

	/**
	 * Returns the public function name under which this agent is exposed via MCP.
	 *
	 * This name should be short, descriptive, and user-friendly (e.g. "stringreverse").
	 *
	 * @return string
	 */
	public function getFunctionName(): string;

	/**
	 * Returns a short human-readable description of what this agent does.
	 *
	 * @return string
	 */
	public function getDescription(): string;

	/**
	 * Returns the expected input specification for this agent.
	 *
	 * Keys are input names, values describe type, required status, etc.
	 *
	 * @return array
	 */
	public function getInputSpec(): array;

	/**
	 * Returns the output specification of this agent.
	 *
	 * Keys are output names, values describe data types and meanings.
	 *
	 * @return array
	 */
	public function getOutputSpec(): array;

	/**
	 * Returns the default configuration values for this agent.
	 *
	 * Typically used for UI prefill or static agent flows.
	 *
	 * @return array
	 */
	public function getDefaultConfig(): array;

	/**
	 * Returns the logical category of this agent (e.g. AI, IO, Logic).
	 *
	 * @return string
	 */
	public function getCategory(): string;

	/**
	 * Returns true if the agent supports asynchronous execution.
	 *
	 * @return bool
	 */
	public function supportsAsync(): bool;

	/**
	 * Returns a list of required resource or agent dependencies.
	 *
	 * @return string[] List of dependency identifiers or types
	 */
	public function getDependencies(): array;

	/**
	 * Returns the current version of the agent implementation.
	 *
	 * Used for reproducibility and flow version locking.
	 *
	 * @return string
	 */
	public function getVersion(): string;

	/**
	 * Returns a list of descriptive tags (keywords) for discovery and UI.
	 *
	 * @return string[]
	 */
	public function getTags(): array;
}


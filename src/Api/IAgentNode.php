<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;
use MissionBay\Agent\AgentNodePort;

interface IAgentNode extends IBase {

	/**
	 * Returns the unique ID of this node within the flow.
	 *
	 * @return string
	 */
	public function getId(): string;

	/**
	 * Sets the ID of this node within the flow.
	 *
	 * @param string $id
	 */
	public function setId(string $id): void;

	/**
	 * Returns the list of input ports.
	 *
	 * This can return either:
	 * - A list of strings (legacy format), e.g. ["text", "url"]
	 * - A list of AgentNodePort objects (recommended format)
	 *
	 * The AgentNodePort format allows specifying:
	 * - name
	 * - type (incl. union and array types)
	 * - default value
	 * - required flag
	 * - description (for UI / documentation)
	 *
	 * @return array<string|AgentNodePort>
	 */
	public function getInputDefinitions(): array;

	/**
	 * Returns the list of output ports.
	 *
	 * This can return either:
	 * - A list of strings (legacy format), e.g. ["result", "error"]
	 * - A list of AgentNodePort objects (recommended format)
	 *
	 * Output ports may optionally specify default values for missing outputs.
	 *
	 * @return array<string|AgentNodePort>
	 */
	public function getOutputDefinitions(): array;

	/**
	 * Executes the node's logic using provided inputs and context.
	 *
	 * @param array<string, mixed> $inputs Named input values
	 * @param IAgentContext $context Flow-wide context object
	 * @return array<string, mixed> Named outputs
	 */
	public function execute(array $inputs, IAgentContext $context): array;

	/**
	 * Returns a human-readable description of what the node does.
	 * Useful for documentation, UI display, and GPT interpretation.
	 *
	 * @return string
	 */
	public function getDescription(): string;
}


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

namespace MissionBay\Agent;

/**
 * Class AgentNodePort
 *
 * Represents a single input or output port of a node within a flow.
 * Ports are used to define data interfaces for nodes: type-safe, documented, UI-fähig.
 */
class AgentNodePort {

	/**
	 * @var string Logical name of the port (e.g., "text", "url", "result")
	 */
	public string $name;

	/**
	 * @var string Human-readable description of what the port represents
	 */
	public string $description;

	/**
	 * @var string Expected data type (e.g., "string", "int", "array<string>", "bool|null")
	 */
	public string $type;

	/**
	 * @var mixed|null Default value to use if input is missing (ignored for outputs)
	 */
	public mixed $default;

	/**
	 * @var bool Whether the input is required (true) or optional (false)
	 */
	public bool $required;

	/**
	 * AgentNodePort constructor.
	 *
	 * @param string $name Logical port name
	 * @param string $description Descriptive label (shown in UI or docs)
	 * @param string $type Data type (primitive or complex)
	 * @param mixed|null $default Default value (if input is optional)
	 * @param bool $required Indicates if this port must be supplied
	 */
	public function __construct(
		string $name,
		string $description = '',
		string $type = 'string',
		mixed $default = null,
		bool $required = true
	) {
		$this->name = $name;
		$this->description = $description;
		$this->type = $type;
		$this->default = $default;
		$this->required = $required;
	}

	/**
	 * Returns the port definition as a structured array.
	 * Useful for UI rendering, JSON exports, or schema introspection.
	 *
	 * @return array<string, mixed> Associative array representation of the port
	 */
	public function toArray(): array {
		return [
			'name'        => $this->name,
			'description' => $this->description,
			'type'        => $this->type,
			'default'     => $this->default,
			'required'    => $this->required,
		];
	}
}


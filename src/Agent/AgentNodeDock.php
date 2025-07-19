<?php declare(strict_types=1);

namespace MissionBay\Agent;

/**
 * Class AgentNodeDock
 *
 * Represents a named dock (external interface connector) of a node.
 * Docks are used to "plug in" external resources like loggers, APIs, AI models, etc.
 * Each dock defines the expected interface and can limit how many resources may be attached.
 */
class AgentNodeDock {

	/**
	 * @var string Unique dock name within the node (e.g. "logger", "llm", "storage")
	 */
	public string $name;

	/**
	 * @var string Human-readable description of the dock's purpose
	 */
	public string $description;

	/**
	 * @var string Fully qualified interface name that connected resources must implement
	 */
	public string $interface;

	/**
	 * @var int|null Maximum number of resources allowed to connect to this dock (null = unlimited)
	 */
	public ?int $maxConnections;

	/**
	 * @var bool Whether this dock is required for execution
	 */
	public bool $required;

	/**
	 * AgentNodeDock constructor.
	 *
	 * @param string $name Dock identifier used in the node
	 * @param string $description Short description of what this dock does
	 * @param string $interface Interface that connected resources must implement (FQCN)
	 * @param int|null $maxConnections Optional maximum number of allowed connections
	 * @param bool $required Whether this dock is required (default: false)
	 */
	public function __construct(
		string $name,
		string $description = '',
		string $interface = '',
		?int $maxConnections = null,
		bool $required = false
	) {
		$this->name = $name;
		$this->description = $description;
		$this->interface = $interface;
		$this->maxConnections = $maxConnections;
		$this->required = $required;
	}

	/**
	 * Returns the dock definition as an associative array.
	 * Useful for UI rendering, schema export, or GPT introspection.
	 *
	 * @return array<string, mixed> Structured representation of the dock
	 */
	public function toArray(): array {
		return [
			'name'           => $this->name,
			'description'    => $this->description,
			'interface'      => $this->interface,
			'maxConnections' => $this->maxConnections,
			'required'       => $this->required,
		];
	}
}


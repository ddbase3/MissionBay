<?php declare(strict_types=1);

namespace MissionBay\Agent;

class AgentNodePort {

	public string $name;
	public string $description;
	public string $type;
	public mixed $default;
	public bool $required;

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
	 * Returns the definition as associative array, useful for UI or export
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


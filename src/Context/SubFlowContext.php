<?php declare(strict_types=1);

namespace MissionBay\Context;

use MissionBay\Api\IAgentMemory;

/**
 * Extended context for subflows, with prioritized lookup in extraVars.
 */
class SubFlowContext extends AgentContext {

	private array $extraVars;

	/**
	 * @param IAgentMemory|null $memory Optional memory override
	 * @param array $vars Standard context vars
	 * @param array $extraVars Vars that take precedence when reading
	 */
	public function __construct(?IAgentMemory $memory = null, array $vars = [], array $extraVars = []) {
		parent::__construct($memory, $vars);
		$this->extraVars = $extraVars;
	}

	public static function getName(): string {
		return 'subflowcontext';
	}

	/**
	 * Retrieves a context variable, checking extraVars first.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function getVar(string $key): mixed {
		if (array_key_exists($key, $this->extraVars)) {
			return $this->extraVars[$key];
		}
		return parent::getVar($key);
	}
}


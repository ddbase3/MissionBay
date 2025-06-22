<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentMemory;

/**
 * Erweiterter Context, der zusätzliche Flow-spezifische Variablen bereitstellt
 */
class SubFlowContext extends AgentContext {

	private array $extraVars;

	/**
	 * @param IAgentMemory $memory
	 * @param array $vars Normale Context-Variablen
	 * @param array $extraVars Zusätzliche (vorrangige) Variablen – z. B. ['context_flow' => $subflow]
	 */
	public function __construct(IAgentMemory $memory, array $vars = [], array $extraVars = []) {
		parent::__construct($memory, $vars);
		$this->extraVars = $extraVars;
	}

	/**
	 * Gibt vorrangig die extraVars zurück
	 */
	public function getVar(string $key): mixed {
		if (array_key_exists($key, $this->extraVars)) {
			return $this->extraVars[$key];
		}
		return parent::getVar($key);
	}
}


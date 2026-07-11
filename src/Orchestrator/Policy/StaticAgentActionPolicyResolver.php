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

namespace MissionBay\Orchestrator\Policy;

use AssistantFoundation\Api\IAgentActionPolicy;

/**
 * StaticAgentActionPolicyResolver
 *
 * Resolves a fixed set of already constructed policies. This keeps direct
 * orchestrator construction and isolated tests operational without creating a
 * second component registry for normal application runtime.
 */
final class StaticAgentActionPolicyResolver implements IAgentActionPolicyResolver {

	/**
	 * @var array<string,IAgentActionPolicy>
	 */
	private array $policiesById = [];

	/**
	 * @param array<int,IAgentActionPolicy> $policies
	 */
	public function __construct(array $policies) {
		foreach ($policies as $policy) {
			if (!$policy instanceof IAgentActionPolicy) {
				throw new \InvalidArgumentException(
					'Static action policies must implement IAgentActionPolicy.'
				);
			}

			$policyId = trim($policy->id());
			if ($policyId === '') {
				throw new \InvalidArgumentException('Static action policy ids must not be empty.');
			}

			if (isset($this->policiesById[$policyId])) {
				throw new \InvalidArgumentException('Duplicate static action policy id: ' . $policyId);
			}

			$this->policiesById[$policyId] = $policy;
		}

		if ($this->policiesById === []) {
			throw new \InvalidArgumentException('At least one static action policy is required.');
		}
	}

	public function resolve(array $policyIds): array {
		if ($policyIds === []) {
			throw new \RuntimeException('At least one agent action policy id is required.');
		}

		$result = [];
		$known = [];

		foreach ($policyIds as $policyId) {
			if (!is_string($policyId) || trim($policyId) === '') {
				throw new \RuntimeException('Agent action policy ids must be non-empty strings.');
			}

			$policyId = trim($policyId);
			if (isset($known[$policyId])) {
				throw new \RuntimeException('Duplicate agent action policy id: ' . $policyId);
			}

			if (!isset($this->policiesById[$policyId])) {
				throw new \RuntimeException('Static agent action policy could not be resolved: ' . $policyId);
			}

			$known[$policyId] = true;
			$result[] = $this->policiesById[$policyId];
		}

		return $result;
	}
}

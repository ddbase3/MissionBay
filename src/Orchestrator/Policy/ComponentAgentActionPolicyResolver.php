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
use Base3\Api\IComponentResolver;

/**
 * ComponentAgentActionPolicyResolver
 *
 * Resolves configured action policies through the general BASE3 component
 * resolver. It owns no policy registry and performs no policy construction.
 */
final class ComponentAgentActionPolicyResolver implements IAgentActionPolicyResolver {

	public function __construct(
		private readonly IComponentResolver $componentResolver
	) {}

	public function resolve(array $policyIds): array {
		$policyIds = $this->normalizePolicyIds($policyIds);
		$policies = [];

		foreach ($policyIds as $policyId) {
			$policy = $this->componentResolver->get(IAgentActionPolicy::class, $policyId);

			if (!$policy instanceof IAgentActionPolicy) {
				throw new \RuntimeException(
					'Configured agent action policy could not be resolved: ' . $policyId
				);
			}

			$policies[] = $policy;
		}

		return $policies;
	}

	/**
	 * @param array<int,mixed> $policyIds
	 * @return array<int,string>
	 */
	private function normalizePolicyIds(array $policyIds): array {
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

			$known[$policyId] = true;
			$result[] = $policyId;
		}

		return $result;
	}
}

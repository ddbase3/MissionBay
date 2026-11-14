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

namespace Base3\MissionBay\Orchestrator\Decision;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\MissionBay\Api\IAgentModelDecisionStrategy;
use Base3\MissionBay\Api\IAgentModelDecisionStrategyResolver;

final class AgentModelDecisionStrategyResolver implements IAgentModelDecisionStrategyResolver {

	public function __construct(private readonly IClassMap $classMap) {}

	public function resolve(string $name): IAgentModelDecisionStrategy {
		$name = trim($name);
		if ($name === '') {
			throw new \RuntimeException('Model decision strategy name must not be empty.');
		}

		$strategy = $this->classMap->getInstanceByInterfaceName(IAgentModelDecisionStrategy::class, $name);
		if (!$strategy instanceof IAgentModelDecisionStrategy) {
			throw new \RuntimeException('Model decision strategy could not be resolved: ' . $name);
		}

		return $strategy;
	}
}

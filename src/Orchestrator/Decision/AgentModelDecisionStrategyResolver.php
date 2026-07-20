<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator\Decision;

use Base3\Api\IClassMap;
use MissionBay\Api\IAgentModelDecisionStrategy;
use MissionBay\Api\IAgentModelDecisionStrategyResolver;

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

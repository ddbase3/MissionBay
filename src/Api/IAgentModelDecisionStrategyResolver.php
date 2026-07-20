<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Api;

interface IAgentModelDecisionStrategyResolver {

	public function resolve(string $name): IAgentModelDecisionStrategy;
}

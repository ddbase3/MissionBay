<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Api;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentStageResult;
use Base3\Api\IBase;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;

interface IAgentModelDecisionStrategy extends IBase {

	public function decide(IAgentContext $context, AgentModelDecisionConfig $config): AgentStageResult;
}

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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Dto\AgentStageMount;
use Base3\AssistantFoundation\Dto\AgentStageResult;
use Base3\AssistantFoundation\Dto\AgentStageSlot;
use Base3\CoreFoundation\Api\IComponent;
use Base3\CoreFoundation\Api\IComponentResolver;
use Base3\MissionBay\Orchestrator\AgentStagePipelineResolver;
use PHPUnit\Framework\TestCase;

final class RecordingResolvedAgentStage implements IAgentStage {

	public function __construct(private readonly string $id) {}

	public static function getName(): string {
		return 'recordingresolvedagentstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->id;
	}

	public function getDescription(): string {
		return 'Test stage.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		return false;
	}

	public function process(IAgentContext $context): AgentStageResult {
		return AgentStageResult::none();
	}
}

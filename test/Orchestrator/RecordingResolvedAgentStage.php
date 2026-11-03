<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentStageMount;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentStageSlot;
use Base3\Api\IComponent;
use Base3\Api\IComponentResolver;
use MissionBay\Orchestrator\AgentStagePipelineResolver;
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

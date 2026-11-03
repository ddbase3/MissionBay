<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionReview;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
use MissionBay\Audit\AgentToolAuditContext;
use MissionBay\Context\AgentContext;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Resource\ConfiguredAgentToolResource;
use PHPUnit\Framework\TestCase;

final class FailingConfiguredToolTestDouble implements IAgentTool {

	public static function getName(): string {
		return 'failingconfiguredtooltestdouble';
	}

	public function getDescription(): string {
		return 'Failing configured tool test double.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'failing_tool',
				'description' => 'Always fails.',
				'parameters' => [
					'type' => 'object',
					'properties' => []
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		throw new \RuntimeException('Configured tool failed.');
	}
}

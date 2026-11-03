<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentBudgetAssessment;
use AssistantFoundation\Dto\AgentContextAssessment;
use AssistantFoundation\Dto\AgentContextCompaction;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentStageTraceEntry;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;
use Base3\Event\Api\IEventManager;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentBudgetGuardStage;
use MissionBay\Orchestrator\Stage\AgentContextAssessmentStage;
use MissionBay\Orchestrator\Stage\AgentContextCompactionStage;
use MissionBay\Orchestrator\Stage\AgentFinalAnswerStage;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentResultVerificationStage;
use MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use PHPUnit\Framework\TestCase;

final class QueueChatModel implements IAiChatModel {

	use NormalizedChatModelTrait;

	public static function getName(): string {
		return 'queuechatmodel';
	}

	/**
	 * @var array<int,mixed>
	 */
	private array $responses;

	/**
	 * @var array<int,array{0:array,1:array}>
	 */
	private array $calls = [];

	/**
	 * @param array<int,mixed> $responses
	 */
	public function __construct(array $responses) {
		$this->responses = $responses;
		$this->initializeProviderRequestEvents(new AiProviderRequestEventDispatcher(new EventManager()));
	}

	public function chat(array $messages): string {
		return '';
	}

	public function raw(array $messages, array $tools = []): mixed {
		$this->calls[] = [$messages, $tools];
		$response = array_shift($this->responses);

		if ($response instanceof \Throwable) {
			throw $response;
		}

		return $response;
	}

	public function stream(array $messages, array $tools, callable $onData, ?callable $onMeta = null): void {
	}

	public function setOptions(array $options): void {
	}

	public function getOptions(): array {
		return [];
	}

	/**
	 * @return array<int,array{0:array,1:array}>
	 */
	public function getCalls(): array {
		return $this->calls;
	}
}

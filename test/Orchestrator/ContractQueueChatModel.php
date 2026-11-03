<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentToolContractValidation;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentTool;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use PHPUnit\Framework\TestCase;

final class ContractQueueChatModel implements IAiChatModel {

	use NormalizedChatModelTrait;

	public static function getName(): string {
		return 'contractqueuechatmodel';
	}

	/** @var array<int,mixed> */
	private array $responses;

	/** @var array<int,array{0:array,1:array}> */
	private array $calls = [];

	/** @param array<int,mixed> $responses */
	public function __construct(array $responses) {
		$this->responses = $responses;
		$this->initializeProviderRequestEvents(new AiProviderRequestEventDispatcher(new EventManager()));
	}

	public function chat(array $messages): string {
		return '';
	}

	public function raw(array $messages, array $tools = []): mixed {
		$this->calls[] = [$messages, $tools];

		return array_shift($this->responses);
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
	}

	public function setOptions(array $options): void {
	}

	public function getOptions(): array {
		return [];
	}

	/** @return array<int,array{0:array,1:array}> */
	public function getCalls(): array {
		return $this->calls;
	}
}

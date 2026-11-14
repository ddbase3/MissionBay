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
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionReview;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentMutationCommitDecision;
use Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Dto\AgentSuspensionClaim;
use Base3\AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\MissionBay\Api\IAgentMutationGuardedTool;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Event\MissionBayAgentActionAuditEvent;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use Base3\MissionBay\Policy\MutationApprovalAgentActionPolicy;
use PHPUnit\Framework\TestCase;

final class ApprovalMemoryStateStore implements IStateStore {
	/** @var array<string,array{value:mixed,expires_at:?int}> */
	private array $values = [];

	public function get(string $key, mixed $default = null): mixed {
		if (!$this->has($key)) {
			return $default;
		}
		return $this->values[$key]['value'];
	}

	public function has(string $key): bool {
		if (!isset($this->values[$key])) {
			return false;
		}
		$expiresAt = $this->values[$key]['expires_at'];
		if ($expiresAt !== null && $expiresAt <= time()) {
			unset($this->values[$key]);
			return false;
		}
		return true;
	}

	public function set(string $key, mixed $value, ?int $ttlSeconds = null): void {
		$this->values[$key] = [
			'value' => $value,
			'expires_at' => $ttlSeconds === null ? null : time() + $ttlSeconds
		];
	}

	public function delete(string $key): bool {
		$exists = $this->has($key);
		unset($this->values[$key]);
		return $exists;
	}

	public function setIfNotExists(string $key, mixed $value, ?int $ttlSeconds = null): bool {
		if ($this->has($key)) {
			return false;
		}
		$this->set($key, $value, $ttlSeconds);
		return true;
	}

	public function listKeys(string $prefix): array {
		return array_values(array_filter(array_keys($this->values), static fn(string $key): bool => str_starts_with($key, $prefix)));
	}

	public function flush(): void {}
}

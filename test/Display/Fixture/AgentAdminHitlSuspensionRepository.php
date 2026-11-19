<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Display\Fixture;

use Base3\AssistantFoundation\Api\IAgentSuspensionRepository;
use Base3\AssistantFoundation\Dto\AgentSuspension;
use Base3\AssistantFoundation\Dto\AgentSuspensionClaim;
use Base3\AssistantFoundation\Dto\AgentSuspensionResolution;
use Base3\AssistantFoundation\Dto\AgentSuspensionState;
use LogicException;

final class AgentAdminHitlSuspensionRepository implements IAgentSuspensionRepository {
	public ?AgentSuspensionState $pending = null;
	public string $scope = '';
	public string $lastScope = '';
	public function create(AgentSuspension $suspension, int $ttlSeconds): string { throw new LogicException('Review owner persists suspensions.'); }
	public function findPending(string $scopeId): ?AgentSuspensionState {
		$this->lastScope = $scopeId;
		return $scopeId === $this->scope ? $this->pending : null;
	}
	public function findAll(string $scopeId): array { return []; }
	public function claim(string $resumeHandle): AgentSuspensionClaim { throw new LogicException('Resume owner claims suspensions.'); }
	public function release(AgentSuspensionClaim $claim): void { throw new LogicException('Resume owner releases claims.'); }
	public function consume(AgentSuspensionClaim $claim, ?AgentSuspensionResolution $resolution = null): void { throw new LogicException('Resume owner consumes claims.'); }
}

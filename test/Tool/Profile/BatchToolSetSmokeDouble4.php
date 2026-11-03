<?php declare(strict_types=1);

namespace MissionBay\Test\Tool\Profile;

final class BatchToolSetSmokeDouble4 implements \AssistantFoundation\Api\IAgentSuspensionRepository  {
		public function create(\AssistantFoundation\Dto\AgentSuspension $suspension, int $ttlSeconds): string { return 'resume-handle'; }
		public function findPending(string $scopeId): ?\AssistantFoundation\Dto\AgentSuspensionState { return null; }
		public function findAll(string $scopeId): array { return []; }
		public function claim(string $resumeHandle): \AssistantFoundation\Dto\AgentSuspensionClaim { throw new \RuntimeException('Not used.'); }
		public function release(\AssistantFoundation\Dto\AgentSuspensionClaim $claim): void {}
		public function consume(\AssistantFoundation\Dto\AgentSuspensionClaim $claim, ?\AssistantFoundation\Dto\AgentSuspensionResolution $resolution = null): void {}
	}

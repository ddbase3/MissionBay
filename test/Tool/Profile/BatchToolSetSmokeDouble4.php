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

namespace Base3\MissionBay\Test\Tool\Profile;

final class BatchToolSetSmokeDouble4 implements \Base3\AssistantFoundation\Api\IAgentSuspensionRepository  {
		public function create(\Base3\AssistantFoundation\Dto\AgentSuspension $suspension, int $ttlSeconds): string { return 'resume-handle'; }
		public function findPending(string $scopeId): ?\Base3\AssistantFoundation\Dto\AgentSuspensionState { return null; }
		public function findAll(string $scopeId): array { return []; }
		public function claim(string $resumeHandle): \Base3\AssistantFoundation\Dto\AgentSuspensionClaim { throw new \RuntimeException('Not used.'); }
		public function release(\Base3\AssistantFoundation\Dto\AgentSuspensionClaim $claim): void {}
		public function consume(\Base3\AssistantFoundation\Dto\AgentSuspensionClaim $claim, ?\Base3\AssistantFoundation\Dto\AgentSuspensionResolution $resolution = null): void {}
	}

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

namespace Base3\MissionBay\Test\Orchestrator\Service;

use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Api\IAgentBatchTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Service\AgentBatchExecutionService;
use Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentBatchExecutionServiceTest extends TestCase {

	public function testUnregisteredToolCannotBeTreatedAsApprovedBatch(): void {
		$service = $this->service();
		$context = new AgentContext();
		$this->assertFalse($service->isBatchCall(new AiToolCall('c', 'batch_run', []), $context));
	}

	public function testMatchingDefinitionAndBatchFunctionAreBothRequired(): void {
		$batch = $this->createStub(IAgentBatchTool::class);
		$batch->method('getToolDefinitions')->willReturn([['function' => ['name' => 'batch_run']]]);
		$batch->method('isBatchFunction')->willReturnCallback(static fn(string $name): bool => $name === 'batch_run');
		$context = new AgentContext(vars: [Keys::TOOLS => [$batch]]);
		$this->assertTrue($this->service()->isBatchCall(new AiToolCall('c', 'batch_run', []), $context));
		$this->assertFalse($this->service()->isBatchCall(new AiToolCall('c', 'unrelated', []), $context));
	}

	public function testBatchExpansionRejectsUnregisteredEnvelopeBeforeMutationGuard(): void {
		$this->expectException(\RuntimeException::class);
		$this->service()->expandApprovedCall(new AiToolCall('c', 'batch_run', []), new AgentContext());
	}

	private function service(): AgentBatchExecutionService {
		return new AgentBatchExecutionService(new AgentMutationCommitGuardService(new AgentActionFingerprint()));
	}
}

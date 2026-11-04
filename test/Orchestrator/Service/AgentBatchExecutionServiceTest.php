<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Service;

use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Api\IAgentBatchTool;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentBatchExecutionService;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
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

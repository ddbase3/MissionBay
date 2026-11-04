<?php declare(strict_types=1);

namespace MissionBay\Test\Resource\AgentTool\Batch;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Resource\AgentTool\Batch\BatchAgentTool;
use PHPUnit\Framework\TestCase;

final class BatchAgentToolApprovalBoundaryTest extends TestCase {

	public function testDefinitionRequiresCombinedApprovalAndCommitGuard(): void {
		$definition = $this->tool()->getToolDefinitions()[0];
		$this->assertSame(BatchAgentTool::FN_EXECUTE, $definition['function']['name']);
		$this->assertTrue($definition['mutation']);
		$this->assertTrue($definition['requiresApproval']);
		$this->assertTrue($definition['commitGuardRequired']);
		$this->assertFalse($definition['readOnlyHint']);
	}

	public function testBatchDefinitionRequiresAtLeastTwoItems(): void {
		$params = $this->tool()->getToolDefinitions()[0]['function']['parameters'];
		$this->assertSame(['target_function', 'items'], $params['required']);
		$this->assertSame(2, $params['properties']['items']['minItems']);
		$this->assertSame(100, $params['properties']['items']['maxItems']);
		$this->assertFalse($params['additionalProperties']);
	}

	public function testBatchToolDoesNotAllowDirectExecutionBypass(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('must be expanded by the governed MissionBay action pipeline');
		$this->tool()->callTool(BatchAgentTool::FN_EXECUTE, ['items' => []], $this->createStub(IAgentContext::class));
	}

	public function testBatchFunctionRecognitionIsExactAfterTrimming(): void {
		$tool = $this->tool();
		$this->assertTrue($tool->isBatchFunction(BatchAgentTool::FN_EXECUTE));
		$this->assertTrue($tool->isBatchFunction(' ' . BatchAgentTool::FN_EXECUTE . ' '));
		$this->assertFalse($tool->isBatchFunction('execute_other_tool_batch'));
	}

	public function testInstructionContributionExplainsSingleActionAndIndependentBatch(): void {
		$tool = $this->tool();
		$blocks = $tool->contribute($this->createStub(IAgentContext::class));
		$this->assertCount(1, $blocks);
		$this->assertSame('batch-tool-usage', $blocks[0]->getId());
		$this->assertStringContainsString('exactly one action', $blocks[0]->getContent());
		$this->assertStringContainsString('batchIndependent=true', $blocks[0]->getContent());
	}

	public function testContextPriorityMatchesInstructionBlock(): void {
		$tool = $this->tool();
		$blocks = $tool->contribute($this->createStub(IAgentContext::class));
		$items = is_array($blocks) ? $blocks : iterator_to_array($blocks);
		$this->assertSame(30, $tool->getPriority());
		$this->assertSame($tool->getPriority(), $items[0]->getPriority());
		$this->assertSame('batch-unit', $items[0]->getSource());
	}

	private function tool(): BatchAgentTool {
		$semantics = new AgentToolDefinitionSemantics();
		$fingerprint = new AgentActionFingerprint();
		return new BatchAgentTool(
			$semantics,
			new AgentToolContractValidationService(),
			$fingerprint,
			new AgentMutationCommitGuardService($fingerprint),
			'batch-unit'
		);
	}
}

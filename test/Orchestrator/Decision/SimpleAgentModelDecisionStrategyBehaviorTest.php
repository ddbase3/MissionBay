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

namespace Base3\MissionBay\Test\Orchestrator\Decision;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Orchestrator\Decision\SimpleAgentModelDecisionStrategy;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class SimpleAgentModelDecisionStrategyBehaviorTest extends TestCase {

	public function testStrategyRetainsLegacyDiscoveryName(): void {
		$this->assertSame(AgentModelDecisionConfig::STRATEGY_SIMPLE, SimpleAgentModelDecisionStrategy::getName());
	}

	public function testMissingModelReturnsFailedStageRatherThanThrowing(): void {
		$context = $this->context([]);
		$patch = (new SimpleAgentModelDecisionStrategy())->decide($context, AgentModelDecisionConfig::simple())->getPatch();
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FAILED, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame('stage_runtime_error', $patch[AgentToolLoopContextKeys::FAILURE_CODE]);
		$this->assertFalse($patch[AgentToolLoopContextKeys::COMPLETED]);
	}

	public function testMessageWithoutToolCallsStartsFinalResponsePhase(): void {
		$model = $this->model(new AiChatResult('final model message', [], new AiResultMetadata('local', 'test', 'result')));
		$context = $this->context([AgentToolLoopContextKeys::MODEL => $model, AgentToolLoopContextKeys::MESSAGES => [['role' => 'user', 'content' => 'hello']]]);
		$patch = (new SimpleAgentModelDecisionStrategy())->decide($context, AgentModelDecisionConfig::simple())->getPatch();
		$this->assertTrue($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FINAL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame('final model message', $patch[AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE]['content']);
		$this->assertSame([], $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS]);
	}

	public function testStructuredToolCallEntersToolPhaseWithoutControlText(): void {
		$call = new AiToolCall('call-1', 'lookup_record', ['id' => 5]);
		$model = $this->model(new AiChatResult('internal thoughts', [$call], new AiResultMetadata('local', 'test', 'result')));
		$context = $this->context([AgentToolLoopContextKeys::MODEL => $model]);
		$patch = (new SimpleAgentModelDecisionStrategy())->decide($context, AgentModelDecisionConfig::simple())->getPatch();
		$this->assertSame(AgentToolLoopContextKeys::PHASE_TOOLS, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame([$call], $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS]);
		$this->assertSame('', $patch[AgentToolLoopContextKeys::MESSAGES][0]['content']);
		$this->assertSame('', $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT]);
	}

	public function testDecisionPromptIncludesContinuationHint(): void {
		$observed = [];
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willReturnCallback(static function(array $messages) use (&$observed): AiChatResult {
			$observed = $messages;
			return new AiChatResult('done', [], new AiResultMetadata('local', 'test', 'result'));
		});
		$context = $this->context([
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::MESSAGES => [['role' => 'user', 'content' => 'please proceed']],
			AgentToolLoopContextKeys::CONTINUATION_HINT => 'Only verified records may be used.'
		]);
		(new SimpleAgentModelDecisionStrategy())->decide($context, AgentModelDecisionConfig::simple());
		$this->assertSame('system', $observed[0]['role']);
		$this->assertStringContainsString('Only verified records may be used.', $observed[0]['content']);
	}

	public function testFailedModelWithoutObservationsFailsStage(): void {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willThrowException(new \RuntimeException('provider offline'));
		$context = $this->context([AgentToolLoopContextKeys::MODEL => $model]);
		$patch = (new SimpleAgentModelDecisionStrategy())->decide($context, AgentModelDecisionConfig::simple())->getPatch();
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FAILED, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertFalse($patch[AgentToolLoopContextKeys::COMPLETED]);
	}

	public function testFailedModelAfterObservationsPreservesPartialAnswerOpportunity(): void {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willThrowException(new \RuntimeException('temporary failure'));
		$context = $this->context([
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::OBSERVATIONS => [['tool' => 'lookup', 'ok' => true]]
		]);
		$patch = (new SimpleAgentModelDecisionStrategy())->decide($context, AgentModelDecisionConfig::simple())->getPatch();
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FINAL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame(AgentToolLoopContextKeys::FINAL_RESPONSE_PARTIAL, $patch[AgentToolLoopContextKeys::FINAL_RESPONSE_MODE]);
		$this->assertTrue($patch[AgentToolLoopContextKeys::TERMINAL_EVIDENCE_READY]);
	}

	private function context(array $data): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $data[$key] ?? null);
		return $context;
	}

	private function model(AiChatResult $result): IAiChatModel {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willReturn($result);
		return $model;
	}
}

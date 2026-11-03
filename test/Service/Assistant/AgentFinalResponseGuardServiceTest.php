<?php declare(strict_types=1);

namespace MissionBay\Test\Service\Assistant;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Dto\Assistant\AgentExecutionLedger;
use MissionBay\Service\Assistant\AgentFinalResponseGuardService;
use PHPUnit\Framework\TestCase;

final class AgentFinalResponseGuardServiceTest extends TestCase {

	public function testNoMutationIntentReturnsTrimmedDraftWithoutModelCall(): void {
		$model = $this->createMock(IAiChatModel::class);
		$model->expects($this->never())->method('complete');
		$result = (new AgentFinalResponseGuardService())->guard($model, $this->turn(), AgentExecutionLedger::fromEvidence([], [], []), '  No changes  ');
		$this->assertSame('No changes', $result);
	}

	public function testEmptyDraftWithMutationIntentUsesSafeFallbackWithoutCallingModel(): void {
		$model = $this->createMock(IAiChatModel::class);
		$model->expects($this->never())->method('complete');
		$ledger = $this->ledger();
		$this->assertSame($ledger->getSafeFallbackResponse(), (new AgentFinalResponseGuardService())->guard($model, $this->turn(), $ledger, '  '));
	}

	public function testValidAcceptancePreservesSupportedDraftAndRecordsMetadata(): void {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willReturn($this->response('accept', false, ''));
		$turn = $this->turn();
		$this->assertSame('Changed.', (new AgentFinalResponseGuardService())->guard($model, $turn, $this->ledger(), ' Changed. '));
		$this->assertCount(1, $turn->getModelResults());
	}

	public function testReplacementAndUnsupportedClaimUseModelProposedReplacement(): void {
		$guard = new AgentFinalResponseGuardService();
		foreach ([['replace', false], ['accept', true]] as [$verdict, $unsupported]) {
			$model = $this->createStub(IAiChatModel::class);
			$model->method('complete')->willReturn($this->response($verdict, $unsupported, '  The change could not be verified. '));
			$this->assertSame('The change could not be verified.', $guard->guard($model, $this->turn(), $this->ledger(), 'Everything changed.'));
		}
	}

	public function testInvalidVerdictOrEmptyReplacementReturnsSafeFallback(): void {
		$guard = new AgentFinalResponseGuardService();
		$ledger = $this->ledger();
		foreach ([
			$this->response('unknown', false, ''),
			$this->response('replace', false, ''),
			$this->response('accept', true, ''),
			new AiChatResult('', [new AiToolCall('call', 'other_tool', ['verdict' => 'accept'])], new AiResultMetadata('guard', 'test', 'model')),
			new AiChatResult('', [new AiToolCall('call', 'missionbay_final_response_verdict', ['verdict' => 'accept', 'has_unsupported_mutation_claim' => 'false'])], new AiResultMetadata('guard', 'test', 'model'))
		] as $response) {
			$model = $this->createStub(IAiChatModel::class);
			$model->method('complete')->willReturn($response);
			$this->assertSame($ledger->getSafeFallbackResponse(), $guard->guard($model, $this->turn(), $ledger, 'I changed it.'));
		}
	}

	public function testProviderExceptionFailsClosed(): void {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willThrowException(new \RuntimeException('backend unavailable'));
		$ledger = $this->ledger();
		$this->assertSame($ledger->getSafeFallbackResponse(), (new AgentFinalResponseGuardService())->guard($model, $this->turn(), $ledger, 'I changed it.'));
	}

	private function ledger(): AgentExecutionLedger {
		return AgentExecutionLedger::fromEvidence(['change'], [], [['mutation_intent' => true]]);
	}

	private function turn(): AgentAssistantTurnResult {
		return new AgentAssistantTurnResult([], ['role' => 'user', 'content' => 'change'], [], 'node', 'message', false);
	}

	private function response(string $verdict, bool $unsupported, string $replacement): AiChatResult {
		return new AiChatResult('', [new AiToolCall('call', 'missionbay_final_response_verdict', [
			'verdict' => $verdict,
			'has_unsupported_mutation_claim' => $unsupported,
			'replacement' => $replacement
		])], new AiResultMetadata('guard', 'test', 'model'));
	}
}

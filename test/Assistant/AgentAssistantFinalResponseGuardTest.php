<?php declare(strict_types=1);

namespace MissionBay\Test\Assistant;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Dto\Orchestrator\AgentModelDecisionAssessment;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;
use MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use PHPUnit\Framework\TestCase;

final class AgentAssistantFinalResponseGuardTest extends TestCase {

	public function testDirectResponseReplacesUnsupportedMutationSuccessClaim(): void {
		$model = new FinalResponseGuardChatModel(
			completeResults: [
				new AiChatResult(
					'Das ReadSpeaker-Plugin ist jetzt deaktiviert.',
					[],
					new AiResultMetadata('final_response', 'test', 'draft')
				),
				$this->replacementVerdict('Die Deaktivierung wurde nicht durchgeführt, weil kein Änderungs-Tool erfolgreich ausgeführt wurde.')
			]
		);
		$service = new AgentAssistantFinalResponseService(new AgentAssistantMessageFactory());

		$content = $service->createDirectResponse($model, $this->turnResult());

		$this->assertSame('Die Deaktivierung wurde nicht durchgeführt, weil kein Änderungs-Tool erfolgreich ausgeführt wurde.', $content);
		$this->assertSame(2, $model->getCompleteCalls());
	}

	public function testDirectResponseUsesSuccessfulMutationEvidenceAndResult(): void {
		$model = new FinalResponseGuardChatModel(
			completeResults: [
				new AiChatResult(
					'WebDAV ist deaktiviert.',
					[],
					new AiResultMetadata('final_response', 'test', 'draft')
				)
			]
		);
		$service = new AgentAssistantFinalResponseService(new AgentAssistantMessageFactory());

		$content = $service->createDirectResponse($model, $this->successfulMutationTurnResult());

		$this->assertSame('WebDAV ist deaktiviert.', $content);
		$this->assertSame(1, $model->getCompleteCalls());
	}

	public function testStreamingResponseBuffersDraftUntilMutationGuardCompletes(): void {
		$model = new FinalResponseGuardChatModel(
			completeResults: [
				$this->replacementVerdict('Die Deaktivierung wurde nicht durchgeführt.')
			],
			streamResult: new AiChatResult(
				'Das ReadSpeaker-Plugin ist jetzt deaktiviert.',
				[],
				new AiResultMetadata('final_response', 'test', 'stream')
			)
		);
		$service = new AgentAssistantFinalResponseService(new AgentAssistantMessageFactory());
		$chunks = [];

		$content = $service->createStreamingResponse(
			$model,
			$this->turnResult(),
			static function(string $chunk) use (&$chunks): void { $chunks[] = $chunk; }
		);

		$this->assertSame('Die Deaktivierung wurde nicht durchgeführt.', $content);
		$this->assertSame(['Die Deaktivierung wurde nicht durchgeführt.'], $chunks);
		$this->assertSame(1, $model->getCompleteCalls());
		$this->assertSame(1, $model->getStreamCalls());
	}

	private function turnResult(): AgentAssistantTurnResult {
		$orchestrationResult = new AgentToolOrchestratorResult(
			messages: [
				['role' => 'system', 'content' => 'You are a helpful assistant.'],
				['role' => 'user', 'content' => 'deaktoviern']
			],
			finalAssistantMessage: null,
			completed: true,
			iterations: 1,
			finalResponseMode: AgentToolOrchestratorResult::FINAL_RESPONSE_COMPLETE,
			mutationToolNames: ['set_ilias_plugin_activation_state'],
			modelDecisionAssessments: [
				[
					'decision' => AgentModelDecisionAssessment::DECISION_TOOL_REQUIRED,
					'intent' => AgentModelDecisionAssessment::INTENT_MUTATION,
					'confidence' => 0.92,
					'candidate_tools' => ['set_ilias_plugin_activation_state'],
					'reason' => 'The user requests a plugin state change.',
					'clarification' => '',
					'repair_attempted' => false,
					'mutation_intent' => true
				],
				[
					'decision' => AgentModelDecisionAssessment::DECISION_COMPLETE,
					'intent' => AgentModelDecisionAssessment::INTENT_CONVERSATION,
					'confidence' => 0.9,
					'candidate_tools' => [],
					'reason' => 'The repair call tried to terminate without a tool call.',
					'clarification' => '',
					'repair_attempted' => true,
					'mutation_intent' => false
				]
			],
			toolResults: []
		);

		return new AgentAssistantTurnResult(
			messages: $orchestrationResult->getMessages(),
			userMessage: ['role' => 'user', 'content' => 'deaktoviern'],
			memories: [],
			nodeId: 'assistant',
			assistantMessageId: 'assistant-message',
			memoryWriteEnabled: false,
			orchestrationResult: $orchestrationResult
		);
	}

	private function successfulMutationTurnResult(): AgentAssistantTurnResult {
		$orchestrationResult = new AgentToolOrchestratorResult(
			messages: [
				['role' => 'system', 'content' => 'You are a helpful assistant.'],
				['role' => 'user', 'content' => 'deaktiviern'],
				['role' => 'tool', 'tool_call_id' => 'call-webdav', 'content' => '{"enabled":false}']
			],
			finalAssistantMessage: null,
			completed: true,
			iterations: 2,
			finalResponseMode: AgentToolOrchestratorResult::FINAL_RESPONSE_COMPLETE,
			mutationToolNames: ['update_ilias_webdav_settings'],
			modelDecisionAssessments: [[
				'decision' => AgentModelDecisionAssessment::DECISION_TOOL_REQUIRED,
				'intent' => AgentModelDecisionAssessment::INTENT_MUTATION,
				'confidence' => 0.98,
				'candidate_tools' => ['update_ilias_webdav_settings'],
				'reason' => 'The user requests a WebDAV state change.',
				'clarification' => '',
				'repair_attempted' => false,
				'mutation_intent' => true
			]],
			toolResults: [
				AgentToolResult::success(
					'call-webdav',
					'update_ilias_webdav_settings',
					['enabled' => false],
					['enabled' => false],
					[]
				)
			]
		);

		return new AgentAssistantTurnResult(
			messages: $orchestrationResult->getMessages(),
			userMessage: ['role' => 'user', 'content' => 'deaktiviern'],
			memories: [],
			nodeId: 'assistant',
			assistantMessageId: 'assistant-message',
			memoryWriteEnabled: false,
			orchestrationResult: $orchestrationResult
		);
	}

	private function replacementVerdict(string $replacement): AiChatResult {
		return new AiChatResult(
			'',
			[new AiToolCall('verdict-1', 'missionbay_final_response_verdict', [
				'verdict' => 'replace',
				'claims_successful_mutation' => true,
				'reason' => 'The draft claims a successful mutation without execution evidence.',
				'replacement' => $replacement
			])],
			new AiResultMetadata('final_response_guard', 'test', 'verdict')
		);
	}
}

final class FinalResponseGuardChatModel implements IAiChatModel {

	/** @var array<int,AiChatResult> */
	private array $completeResults;
	private int $completeCalls = 0;
	private int $streamCalls = 0;
	private array $options = [];

	/** @param array<int,AiChatResult> $completeResults */
	public function __construct(array $completeResults, private readonly ?AiChatResult $streamResult = null) {
		$this->completeResults = array_values($completeResults);
	}

	public function complete(array $messages, array $tools = []): AiChatResult {
		$this->completeCalls++;
		$result = array_shift($this->completeResults);
		if (!$result instanceof AiChatResult) {
			throw new \RuntimeException('No queued final response result available.');
		}
		return $result;
	}

	public function getCompleteCalls(): int { return $this->completeCalls; }
	public function getStreamCalls(): int { return $this->streamCalls; }
	public function chat(array $messages): string { return $this->complete($messages)->getContent(); }
	public function raw(array $messages, array $tools = []): mixed { return $this->complete($messages, $tools); }
	public function streamResult(array $messages, array $tools, callable $onData, callable $onMeta = null): AiChatResult {
		$this->streamCalls++;
		if (!$this->streamResult instanceof AiChatResult) {
			throw new \RuntimeException('No queued streaming result available.');
		}
		$content = $this->streamResult->getContent();
		$mid = max(1, intdiv(strlen($content), 2));
		$onData(substr($content, 0, $mid));
		$onData(substr($content, $mid));
		return $this->streamResult;
	}
	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
		$this->streamResult($messages, $tools, $onData, $onMeta);
	}
	public function setOptions(array $options): void { $this->options = $options; }
	public function getOptions(): array { return $this->options; }
}

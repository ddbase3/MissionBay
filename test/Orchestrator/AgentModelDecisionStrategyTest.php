<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Orchestrator\AgentModelDecisionAssessment;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentModelDecisionStrategyTest extends TestCase {

	public function testAiGuardedStrategyRepairsToolRequiredDecisionWithRealToolCall(): void {
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'',
				[new AiToolCall('control-1', 'missionbay_tool_phase_decision', [
					'decision' => AgentModelDecisionAssessment::DECISION_TOOL_REQUIRED,
					'intent' => AgentModelDecisionAssessment::INTENT_MUTATION,
					'confidence' => 0.96,
					'candidate_tools' => ['set_ilias_plugin_activation_state'],
					'reason' => 'The user requests a plugin state change.',
					'clarification' => ''
				])],
				new AiResultMetadata('model_decision', 'test', 'primary')
			),
			new AiChatResult(
				'',
				[new AiToolCall('call-1', 'set_ilias_plugin_activation_state', [
					'plugin' => 'ReadSpeaker',
					'state' => 'inactive'
				])],
				new AiResultMetadata('model_decision', 'test', 'repair')
			)
		]);
		$context = $this->context($model, AgentModelDecisionConfig::aiGuarded());

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame(2, $model->getCompleteCalls());
		$this->assertSame(AgentToolLoopContextKeys::PHASE_TOOLS, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame('set_ilias_plugin_activation_state', $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS][0]->getName());
		$this->assertCount(2, $patch[AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS]);
		$this->assertSame(AgentModelDecisionAssessment::DECISION_TOOL_REQUIRED, $patch[AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS][0]['decision']);
		$this->assertSame(AgentModelDecisionAssessment::DECISION_TOOL_CALL, $patch[AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS][1]['decision']);
		$this->assertTrue($patch[AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS][0]['mutation_intent']);
		$this->assertTrue($patch[AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS][1]['repair_attempted']);
	}

	public function testAiGuardedStrategyRepairsMutationCompletionEvenWithHighConfidence(): void {
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'',
				[new AiToolCall('control-1', 'missionbay_tool_phase_decision', [
					'decision' => AgentModelDecisionAssessment::DECISION_COMPLETE,
					'intent' => AgentModelDecisionAssessment::INTENT_MUTATION,
					'confidence' => 0.99,
					'candidate_tools' => ['set_ilias_plugin_activation_state'],
					'reason' => 'The user requests a plugin state change.',
					'clarification' => ''
				])],
				new AiResultMetadata('model_decision', 'test', 'primary')
			),
			new AiChatResult(
				'',
				[new AiToolCall('call-1', 'set_ilias_plugin_activation_state', [
					'plugin' => 'ReadSpeaker',
					'state' => 'inactive'
				])],
				new AiResultMetadata('model_decision', 'test', 'repair')
			)
		]);
		$context = $this->context($model, AgentModelDecisionConfig::aiGuarded());

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame(2, $model->getCompleteCalls());
		$this->assertSame(AgentToolLoopContextKeys::PHASE_TOOLS, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame('set_ilias_plugin_activation_state', $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS][0]->getName());
	}

	public function testAiGuardedStrategyAcceptsStructuredHighConfidenceCompletion(): void {
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'',
				[new AiToolCall('control-1', 'missionbay_tool_phase_decision', [
					'decision' => AgentModelDecisionAssessment::DECISION_COMPLETE,
					'intent' => AgentModelDecisionAssessment::INTENT_CONVERSATION,
					'confidence' => 0.95,
					'candidate_tools' => [],
					'reason' => 'No tool action is needed.',
					'clarification' => ''
				])],
				new AiResultMetadata('model_decision', 'test', 'primary')
			)
		]);
		$context = $this->context($model, AgentModelDecisionConfig::aiGuarded());

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame(1, $model->getCompleteCalls());
		$this->assertTrue($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FINAL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame(AgentModelDecisionAssessment::DECISION_COMPLETE, $patch[AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS][0]['decision']);
	}

	private function context(IAiChatModel $model, AgentModelDecisionConfig $config): AgentContext {
		return new AgentContext(vars: [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::MESSAGES => [
				['role' => 'system', 'content' => 'You are a tool-using assistant.'],
				['role' => 'user', 'content' => 'deaktoviern']
			],
			AgentToolLoopContextKeys::TOOL_DEFINITIONS => [[
				'type' => 'function',
				'readOnlyHint' => false,
				'function' => [
					'name' => 'set_ilias_plugin_activation_state',
					'description' => 'Changes an ILIAS plugin activation state.',
					'parameters' => ['type' => 'object', 'properties' => []]
				]
			]],
			AgentToolLoopContextKeys::MUTATION_TOOL_NAMES => ['set_ilias_plugin_activation_state'],
			AgentToolLoopContextKeys::MODEL_DECISION_CONFIG => $config,
			AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS => [],
			AgentToolLoopContextKeys::MODEL_RESULTS => [],
			AgentToolLoopContextKeys::CONTINUATION_HINT => '',
			AgentToolLoopContextKeys::ITERATION => 1
		]);
	}
}

final class ModelDecisionQueueChatModel implements IAiChatModel {

	/** @var array<int,AiChatResult> */
	private array $results;
	private int $completeCalls = 0;
	private array $options = [];

	/** @param array<int,AiChatResult> $results */
	public function __construct(array $results) {
		$this->results = array_values($results);
	}

	public function complete(array $messages, array $tools = []): AiChatResult {
		$this->completeCalls++;
		$result = array_shift($this->results);
		if (!$result instanceof AiChatResult) {
			throw new \RuntimeException('No queued model decision result available.');
		}
		return $result;
	}

	public function getCompleteCalls(): int {
		return $this->completeCalls;
	}

	public function chat(array $messages): string { return $this->complete($messages)->getContent(); }
	public function raw(array $messages, array $tools = []): mixed { return $this->complete($messages, $tools); }
	public function streamResult(array $messages, array $tools, callable $onData, callable $onMeta = null): AiChatResult {
		$result = $this->complete($messages, $tools);
		$onData($result->getContent());
		return $result;
	}
	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void { $onData($this->chat($messages)); }
	public function setOptions(array $options): void { $this->options = $options; }
	public function getOptions(): array { return $this->options; }
}

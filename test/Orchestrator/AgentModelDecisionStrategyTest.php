<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentToolResult;
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

	public function testAiGuardedStrategyRepairsUngroundedToolClarification(): void {
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'',
				[new AiToolCall('control-1', 'missionbay_tool_phase_decision', [
					'decision' => AgentModelDecisionAssessment::DECISION_CLARIFICATION_REQUIRED,
					'intent' => AgentModelDecisionAssessment::INTENT_MUTATION,
					'confidence' => 0.98,
					'candidate_tools' => ['set_ilias_plugin_activation_state'],
					'missing_arguments' => [],
					'reason' => 'The action requires approval.',
					'clarification' => 'Please confirm the requested action.'
				])],
				new AiResultMetadata('model_decision', 'test', 'primary')
			),
			new AiChatResult(
				'',
				[new AiToolCall('call-1', 'set_ilias_plugin_activation_state', [
					'plugin' => 'ExamplePlugin',
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
		$this->assertSame(AgentModelDecisionAssessment::DECISION_UNRESOLVED, $patch[AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS][0]['decision']);
		$this->assertSame(AgentModelDecisionAssessment::DECISION_TOOL_CALL, $patch[AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS][1]['decision']);
	}

	public function testAiGuardedStrategyAcceptsClarificationForMissingRequiredToolArgument(): void {
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'',
				[new AiToolCall('control-1', 'missionbay_tool_phase_decision', [
					'decision' => AgentModelDecisionAssessment::DECISION_CLARIFICATION_REQUIRED,
					'intent' => AgentModelDecisionAssessment::INTENT_READ,
					'confidence' => 0.94,
					'candidate_tools' => ['get_record'],
					'missing_arguments' => ['record_id'],
					'reason' => 'A required identifier is missing.',
					'clarification' => 'Which record should be read?'
				])],
				new AiResultMetadata('model_decision', 'test', 'primary')
			)
		]);
		$toolDefinitions = [[
			'type' => 'function',
			'function' => [
				'name' => 'get_record',
				'description' => 'Read one record.',
				'parameters' => [
					'type' => 'object',
					'properties' => ['record_id' => ['type' => 'string']],
					'required' => ['record_id']
				]
			]
		]];
		$context = $this->context(
			$model,
			AgentModelDecisionConfig::aiGuarded(),
			$toolDefinitions,
			[]
		);

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame(1, $model->getCompleteCalls());
		$this->assertTrue($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FINAL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame(['record_id'], $patch[AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS][0]['missing_arguments']);
		$this->assertStringContainsString('Which record should be read?', $patch[AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION]);
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

	public function testNativeStrategyStreamsTerminalAssistantContentWithoutCompleteCall(): void {
		$events = [];
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'Der Datensatz 42 ist aktiv.',
				[],
				new AiResultMetadata('model_decision', 'test', 'native-terminal')
			)
		], [['Der Datensatz ', '42 ist aktiv.']]);
		$context = $this->context(
			$model,
			AgentModelDecisionConfig::native(),
			[],
			[],
			static function(string $event, array $payload) use (&$events): void {
				$events[] = [$event, $payload];
			}
		);

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame(0, $model->getCompleteCalls());
		$this->assertSame(1, $model->getStreamCalls());
		$this->assertTrue($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FINAL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame('Der Datensatz 42 ist aktiv.', $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT]);
		$this->assertSame(AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY_STREAMED, $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY]);
		$this->assertSame('Der Datensatz 42 ist aktiv.', $patch[AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE]['content']);
		$this->assertSame([
			['token', ['text' => 'Der Datensatz ']],
			['token', ['text' => '42 ist aktiv.']]
		], $events);
	}

	public function testNativeStrategyContinuesWithToolCallsWithoutPublishingAssistantText(): void {
		$events = [];
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'',
				[new AiToolCall('call-1', 'get_record', ['record_id' => '42'])],
				new AiResultMetadata('model_decision', 'test', 'native-tool-call')
			)
		], [[]]);
		$context = $this->context(
			$model,
			AgentModelDecisionConfig::native(),
			[[
				'type' => 'function',
				'function' => [
					'name' => 'get_record',
					'description' => 'Reads one record.',
					'parameters' => ['type' => 'object']
				]
			]],
			[],
			static function(string $event, array $payload) use (&$events): void {
				$events[] = [$event, $payload];
			}
		);

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame(0, $model->getCompleteCalls());
		$this->assertSame(1, $model->getStreamCalls());
		$this->assertSame([], $events);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_TOOLS, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame('get_record', $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS][0]->getName());
		$this->assertSame('', $patch[AgentToolLoopContextKeys::MESSAGES][2]['content']);
		$this->assertArrayHasKey('tool_calls', $patch[AgentToolLoopContextKeys::MESSAGES][2]);
		$this->assertSame('', $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT]);
		$this->assertSame(AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY_NONE, $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY]);
		$this->assertStringContainsString(
			'you may emit brief plain-language progress text before the tool call',
			$model->getLastMessages()[0]['content']
		);
		$this->assertStringContainsString(
			'Do not start structured renderer blocks, JSON payloads, tables, charts or other final-answer formatting before required tools have returned',
			$model->getLastMessages()[0]['content']
		);
	}

	public function testNativeStrategyUsesConcreteHostApprovalContractForRegisteredMutationTools(): void {
		$toolDefinitions = [
			[
				'type' => 'function',
				'readOnlyHint' => true,
				'mutation' => false,
				'requiresApproval' => false,
				'function' => [
					'name' => 'get_global_ilias_webdav_status',
					'description' => 'Read the current global ILIAS WebDAV status.',
					'parameters' => ['type' => 'object']
				]
			],
			[
				'type' => 'function',
				'readOnlyHint' => false,
				'mutation' => true,
				'requiresApproval' => true,
				'commitGuardRequired' => true,
				'function' => [
					'name' => 'update_global_ilias_webdav_settings',
					'description' => 'Update global ILIAS WebDAV settings. Requires explicit user approval.',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'enabled' => ['type' => 'boolean'],
							'versioning_enabled' => ['type' => 'boolean']
						]
					]
				]
			],
			[
				'type' => 'function',
				'readOnlyHint' => false,
				'mutation' => true,
				'requiresApproval' => true,
				'commitGuardRequired' => true,
				'function' => [
					'name' => 'set_ilias_plugin_activation_state',
					'description' => 'Change one ILIAS plugin activation state.',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'plugin' => ['type' => 'string'],
							'state' => ['type' => 'string']
						]
					]
				]
			]
		];
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'',
				[
					new AiToolCall('call-1', 'update_global_ilias_webdav_settings', [
						'enabled' => true,
						'versioning_enabled' => false
					]),
					new AiToolCall('call-2', 'set_ilias_plugin_activation_state', [
						'plugin' => 'ReadSpeaker',
						'state' => 'inactive'
					])
				],
				new AiResultMetadata('model_decision', 'test', 'native-approval-contract')
			)
		], [[]]);
		$context = $this->context(
			$model,
			AgentModelDecisionConfig::native(),
			$toolDefinitions,
			['update_global_ilias_webdav_settings', 'set_ilias_plugin_activation_state']
		);

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();
		$instruction = $model->getLastMessages()[0]['content'];
		$modelTools = $model->getLastTools();

		$this->assertSame(AgentToolLoopContextKeys::PHASE_TOOLS, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertCount(2, $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS]);
		$this->assertStringContainsString('<BASE3-TOOL-GUIDELINES>', $instruction);
		$this->assertStringContainsString(
			'For tools that require approval, do not ask for confirmation in natural language. Call the tool once.',
			$instruction
		);
		$this->assertStringContainsString('per-iteration capability selection', $instruction);
		$this->assertStringContainsString('A tool error is evidence about the failed call.', $instruction);
		$this->assertStringContainsString('authoritative tool observations materially conflict', $instruction);
		$this->assertStringContainsString('Registered approval-bound tools for this turn:', $instruction);
		$this->assertStringContainsString('`update_global_ilias_webdav_settings`', $instruction);
		$this->assertStringContainsString('`set_ilias_plugin_activation_state`', $instruction);
		$this->assertStringNotContainsString('`get_global_ilias_webdav_status`', $instruction);
		$this->assertSame(
			'Read the current global ILIAS WebDAV status.',
			$modelTools[0]['function']['description']
		);
		$this->assertStringStartsWith(
			'Host approval is handled after this function call. When the user requests this action and the required arguments are available, call this function immediately. Do not ask for confirmation in natural language.',
			$modelTools[1]['function']['description']
		);
		$this->assertStringStartsWith(
			'Host approval is handled after this function call. When the user requests this action and the required arguments are available, call this function immediately. Do not ask for confirmation in natural language.',
			$modelTools[2]['function']['description']
		);
		$this->assertSame($toolDefinitions, $context->getVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS));
	}

	public function testNativeStrategyBuffersTerminalContentWithoutEventCallback(): void {
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'Der Datensatz 42 ist aktiv.',
				[],
				new AiResultMetadata('model_decision', 'test', 'native-terminal')
			)
		], [['Der Datensatz 42 ist aktiv.']]);
		$context = $this->context($model, AgentModelDecisionConfig::native(), [], []);

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame(0, $model->getCompleteCalls());
		$this->assertSame(1, $model->getStreamCalls());
		$this->assertSame('Der Datensatz 42 ist aktiv.', $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT]);
		$this->assertSame(AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY_BUFFERED, $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY]);
	}


	public function testNativeStrategySuppressesContentAfterStructuredToolCallMetadata(): void {
		$events = [];
		$model = new ModelDecisionQueueChatModel(
			[
				new AiChatResult(
					'Preparing the tool call.',
					[new AiToolCall('call-1', 'get_record', ['record_id' => '42'])],
					new AiResultMetadata('model_decision', 'test', 'native-tool-metadata')
				)
			],
			[['Preparing the tool call.']],
			null,
			[[[
				'event' => 'toolcall',
				'tool_calls' => [[
					'index' => 0,
					'id' => 'call-1',
					'function' => ['name' => 'get_record', 'arguments' => '{"record_id":"42"}']
				]]
			]]]
		);
		$context = $this->context(
			$model,
			AgentModelDecisionConfig::native(),
			[[
				'type' => 'function',
				'function' => [
					'name' => 'get_record',
					'description' => 'Reads one record.',
					'parameters' => ['type' => 'object']
				]
			]],
			[],
			static function(string $event, array $payload) use (&$events): void {
				$events[] = [$event, $payload];
			}
		);

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();
		$tokenEvents = array_values(array_filter(
			$events,
			static fn(array $event): bool => ($event[0] ?? '') === 'token'
		));

		$this->assertSame([], $tokenEvents);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_TOOLS, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame('get_record', $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS][0]->getName());
		$this->assertSame('', $patch[AgentToolLoopContextKeys::MESSAGES][2]['content']);
	}

	public function testNativeStrategyAllowsVisibleProgressBeforeLaterToolCallWithoutPersistingProgress(): void {
		$events = [];
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'Ich schaue kurz nach.',
				[new AiToolCall('call-1', 'get_record', ['record_id' => '42'])],
				new AiResultMetadata('model_decision', 'test', 'native-progress')
			)
		], [['Ich schaue kurz nach.']]);
		$context = $this->context(
			$model,
			AgentModelDecisionConfig::native(),
			[[
				'type' => 'function',
				'function' => [
					'name' => 'get_record',
					'description' => 'Reads one record.',
					'parameters' => ['type' => 'object']
				]
			]],
			[],
			static function(string $event, array $payload) use (&$events): void {
				$events[] = [$event, $payload];
			}
		);

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame([['token', ['text' => 'Ich schaue kurz nach.']]], $events);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_TOOLS, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame('get_record', $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS][0]->getName());
		$this->assertSame('', $patch[AgentToolLoopContextKeys::MESSAGES][2]['content']);
		$this->assertArrayNotHasKey(AgentToolLoopContextKeys::FAILURE_CODE, $patch);
	}

	public function testNativeStrategyBuffersTerminalContentAfterFailedMutation(): void {
		$events = [];
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'The plugin change was not completed.',
				[],
				new AiResultMetadata('model_decision', 'test', 'native-mutation-failure')
			)
		], [['The plugin change was not completed.']]);
		$context = $this->context(
			$model,
			AgentModelDecisionConfig::native(),
			null,
			null,
			static function(string $event, array $payload) use (&$events): void {
				$events[] = [$event, $payload];
			}
		);
		$context->setVar(AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS, [
			AgentModelDecisionAssessment::toolCall(
				['set_ilias_plugin_activation_state'],
				false,
				['set_ilias_plugin_activation_state']
			)->toArray()
		]);
		$context->setVar(AgentToolLoopContextKeys::TOOL_RESULTS, [
			AgentToolResult::failure(
				'call-1',
				'set_ilias_plugin_activation_state',
				['plugin' => 'ReadSpeaker', 'state' => 'inactive'],
				'mutation_rejected',
				'The requested mutation was not executed.'
			)
		]);

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame([], $events);
		$this->assertSame(AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY_BUFFERED, $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY]);
		$this->assertSame('The plugin change was not completed.', $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT]);
	}

	public function testNativeStrategyStreamsTerminalContentAfterSuccessfulMutation(): void {
		$events = [];
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'The ReadSpeaker plugin is now inactive.',
				[],
				new AiResultMetadata('model_decision', 'test', 'native-mutation-success')
			)
		], [['The ReadSpeaker plugin is now inactive.']]);
		$context = $this->context(
			$model,
			AgentModelDecisionConfig::native(),
			null,
			null,
			static function(string $event, array $payload) use (&$events): void {
				$events[] = [$event, $payload];
			}
		);
		$context->setVar(AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS, [
			AgentModelDecisionAssessment::toolCall(
				['set_ilias_plugin_activation_state'],
				false,
				['set_ilias_plugin_activation_state']
			)->toArray()
		]);
		$context->setVar(AgentToolLoopContextKeys::TOOL_RESULTS, [
			AgentToolResult::success(
				'call-1',
				'set_ilias_plugin_activation_state',
				['plugin' => 'ReadSpeaker', 'state' => 'inactive'],
				['active' => false]
			)
		]);

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame([
			['token', ['text' => 'The ReadSpeaker plugin is now inactive.']]
		], $events);
		$this->assertSame(AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY_STREAMED, $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY]);
		$this->assertSame('The ReadSpeaker plugin is now inactive.', $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT]);
		$systemContent = (string)($model->getLastMessages()[0]['content'] ?? '');
		$this->assertStringContainsString('Authoritative current-turn execution ledger:', $systemContent);
		$this->assertStringContainsString('successful_mutation_calls', $systemContent);
		$this->assertStringContainsString('set_ilias_plugin_activation_state', $systemContent);
		$this->assertStringContainsString('Never state or imply that a mutation succeeded unless successful_mutation_calls contains the corresponding tool call.', $systemContent);
	}

	public function testNativeStrategyReportsInterruptedVisibleStreamWithoutRecoveryCall(): void {
		$events = [];
		$model = new ModelDecisionQueueChatModel(
			[
				new AiChatResult(
					'Partial answer',
					[],
					new AiResultMetadata('model_decision', 'test', 'native-interrupted')
				)
			],
			[['Partial answer']],
			new \RuntimeException('Provider stream interrupted.')
		);
		$context = $this->context(
			$model,
			AgentModelDecisionConfig::native(),
			[],
			[],
			static function(string $event, array $payload) use (&$events): void {
				$events[] = [$event, $payload];
			}
		);

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame(0, $model->getCompleteCalls());
		$this->assertSame(1, $model->getStreamCalls());
		$this->assertSame('Partial answer', $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT]);
		$this->assertSame(AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY_STREAMED, $patch[AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY]);
		$this->assertSame('native_stream_interrupted', $patch[AgentToolLoopContextKeys::FAILURE_CODE]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FAILED, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame('token', $events[0][0]);
		$this->assertSame('native_stream_interrupted', $events[1][1]['event']);
	}

	public function testNativeStrategyFailsClearlyForEmptyTerminalResponse(): void {
		$model = new ModelDecisionQueueChatModel([
			new AiChatResult(
				'',
				[],
				new AiResultMetadata('model_decision', 'test', 'native-empty')
			)
		], [[]]);
		$context = $this->context($model, AgentModelDecisionConfig::native(), [], []);

		$patch = (new AgentModelDecisionStage())->process($context)->getPatch();

		$this->assertSame('native_model_decision_empty', $patch[AgentToolLoopContextKeys::FAILURE_CODE]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FAILED, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertFalse($patch[AgentToolLoopContextKeys::COMPLETED]);
	}

	private function context(
		IAiChatModel $model,
		AgentModelDecisionConfig $config,
		?array $toolDefinitions = null,
		?array $mutationToolNames = null,
		?callable $eventCallback = null
	): AgentContext {
		$toolDefinitions ??= [[
			'type' => 'function',
			'readOnlyHint' => false,
			'function' => [
				'name' => 'set_ilias_plugin_activation_state',
				'description' => 'Changes an ILIAS plugin activation state.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'plugin' => ['type' => 'string'],
						'state' => ['type' => 'string']
					],
					'required' => ['plugin', 'state']
				]
			]
		]];
		$mutationToolNames ??= ['set_ilias_plugin_activation_state'];

		return new AgentContext(vars: [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::MESSAGES => [
				['role' => 'system', 'content' => 'You are a tool-using assistant.'],
				['role' => 'user', 'content' => 'deaktoviern']
			],
			AgentToolLoopContextKeys::TOOL_DEFINITIONS => $toolDefinitions,
			AgentToolLoopContextKeys::MUTATION_TOOL_NAMES => $mutationToolNames,
			AgentToolLoopContextKeys::MODEL_DECISION_CONFIG => $config,
			AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS => [],
			AgentToolLoopContextKeys::MODEL_RESULTS => [],
			AgentToolLoopContextKeys::CONTINUATION_HINT => '',
			AgentToolLoopContextKeys::EVENT_CALLBACK => $eventCallback,
			AgentToolLoopContextKeys::TOOL_RESULTS => [],
			AgentToolLoopContextKeys::OBSERVATIONS => [],
			AgentToolLoopContextKeys::ITERATION => 1
		]);
	}
}

final class ModelDecisionQueueChatModel implements IAiChatModel {

	/** @var array<int,AiChatResult> */
	private array $results;
	/** @var array<int,array<int,string>> */
	private array $streamChunks;
	private int $completeCalls = 0;
	private int $streamCalls = 0;
	private array $options = [];
	private array $lastMessages = [];
	private array $lastTools = [];
	private ?\Throwable $streamError;
	/** @var array<int,array<int,array<string,mixed>>> */
	private array $streamMetadataEvents;

	/**
	 * @param array<int,AiChatResult> $results
	 * @param array<int,array<int,string>> $streamChunks
	 * @param array<int,array<int,array<string,mixed>>> $streamMetadataEvents
	 */
	public function __construct(
		array $results,
		array $streamChunks = [],
		?\Throwable $streamError = null,
		array $streamMetadataEvents = []
	) {
		$this->results = array_values($results);
		$this->streamChunks = array_values($streamChunks);
		$this->streamError = $streamError;
		$this->streamMetadataEvents = array_values($streamMetadataEvents);
	}

	public function complete(array $messages, array $tools = []): AiChatResult {
		$this->completeCalls++;
		$this->lastMessages = $messages;
		$this->lastTools = $tools;
		return $this->nextResult();
	}

	public function getCompleteCalls(): int {
		return $this->completeCalls;
	}

	public function getStreamCalls(): int {
		return $this->streamCalls;
	}

	public function getLastMessages(): array {
		return $this->lastMessages;
	}

	public function getLastTools(): array {
		return $this->lastTools;
	}

	public function chat(array $messages): string { return $this->complete($messages)->getContent(); }
	public function raw(array $messages, array $tools = []): mixed { return $this->complete($messages, $tools); }
	public function streamResult(array $messages, array $tools, callable $onData, callable $onMeta = null): AiChatResult {
		$this->streamCalls++;
		$this->lastMessages = $messages;
		$this->lastTools = $tools;
		$result = $this->nextResult();
		$metadataEvents = array_shift($this->streamMetadataEvents);
		foreach (is_array($metadataEvents) ? $metadataEvents : [] as $metadata) {
			if ($onMeta !== null) {
				$onMeta($metadata);
			}
		}
		$chunks = array_shift($this->streamChunks);
		if (!is_array($chunks)) {
			$chunks = $result->hasToolCalls() ? [] : [$result->getContent()];
		}
		foreach ($chunks as $chunk) {
			$onData($chunk);
		}
		if ($this->streamError !== null) {
			throw $this->streamError;
		}
		return $result;
	}
	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
		$this->streamResult($messages, $tools, $onData, $onMeta);
	}
	public function setOptions(array $options): void { $this->options = $options; }
	public function getOptions(): array { return $this->options; }

	private function nextResult(): AiChatResult {
		$result = array_shift($this->results);
		if (!$result instanceof AiChatResult) {
			throw new \RuntimeException('No queued model decision result available.');
		}
		return $result;
	}
}

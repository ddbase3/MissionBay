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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentCapability;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AgentResultVerification;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentSemanticVerificationStageTest extends TestCase {

	public function testStageSkipsDuringNormalToolIteration(): void {
		$context = $this->createContext(
			new SemanticVerificationQueueModel([]),
			[AgentToolResult::success('call-1', 'lookup', [], ['found' => true])]
		);
		$context->setVar(AgentToolLoopContextKeys::PHASE, AgentToolLoopContextKeys::PHASE_OBSERVED);
		$context->setVar(AgentToolLoopContextKeys::COMPLETED, false);

		$this->assertFalse((new AgentSemanticVerificationStage())->supports($context));
	}

	public function testStageRunsOnceForTerminalCandidateWithObservations(): void {
		$model = new SemanticVerificationQueueModel([[
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => json_encode([
						'verdict' => 'verified',
						'summary' => 'The plugin detail result is sufficient.',
						'issues' => [],
						'recommendation' => 'answer',
						'confidence' => 0.91
					])
			],
				'finish_reason' => 'stop'
			]],
			'usage' => [
				'prompt_tokens' => 20,
				'completion_tokens' => 10,
				'total_tokens' => 30
			]
		]]);
		$observation = AgentToolResult::success(
			'call-1',
			'plugin-detail',
			['query' => 'Igor2Mail'],
			['active' => 1],
			['iteration' => 2]
		);
		$context = $this->createContext($model, [$observation], 3);
		$stage = new AgentSemanticVerificationStage();

		$this->assertTrue($stage->supports($context));
		$result = $stage->process($context);
		$patch = $result->getPatch();
		$verification = $patch[AgentToolLoopContextKeys::RESULT_VERIFICATIONS][0];

		$this->assertInstanceOf(AgentResultVerification::class, $verification);
		$this->assertTrue($verification->isVerified());
		$this->assertSame('answer', $verification->getMetadata()['recommendation']);
		$this->assertSame(0.91, $verification->getMetadata()['confidence']);
		$this->assertSame(30, $patch[AgentToolLoopContextKeys::MODEL_RESULTS][0]['usage']['total_tokens']);
		$this->assertSame('valid', $result->getMetadata()['verification']['parse_status']);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FINAL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertTrue($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::CONTINUATION_DECISIONS]);
	}

	public function testVerifierReceivesAllCommittedObservationsAndNoCurrentResultSet(): void {
		$model = new SemanticVerificationQueueModel([[
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => json_encode([
						'verdict' => 'verified',
						'summary' => 'Accumulated evidence is sufficient.',
						'issues' => [],
						'recommendation' => 'answer',
						'confidence' => 0.9
					])
				]
			]]
		]]);
		$first = AgentToolResult::success(
			'call-summary',
			'plugin-summary',
			['query' => 'Igor2Mail'],
			['installed' => true],
			['iteration' => 1]
		);
		$second = AgentToolResult::success(
			'call-detail',
			'plugin-detail',
			['query' => 'Igor2Mail'],
			['active' => 1],
			['iteration' => 2]
		);
		$context = $this->createContext($model, [$first, $second], 3);

		(new AgentSemanticVerificationStage())->process($context);
		$calls = $model->getCalls();
		$payload = json_decode($calls[0][0][1]['content'], true);
		$this->assertStringContainsString('Previous assistant statements are not evidence', $calls[0][0][0]['content']);
		$this->assertStringContainsString('successful mutation call supports only what its returned evidence establishes', $calls[0][0][0]['content']);
		$this->assertStringContainsString('authoritative observations materially contradict each other', $calls[0][0][0]['content']);
		$this->assertStringContainsString('failed tool result is evidence about that attempted call', $calls[0][0][0]['content']);

		$this->assertCount(2, $payload['previous_observations']);
		$this->assertSame([], $payload['current_tool_results']);
		$this->assertSame(2, $payload['evidence_count']);
		$this->assertSame(2, $payload['available_tool_count']);
		$this->assertSame(['lookup'], $payload['currently_selected_tools']);
		$this->assertSame(['lookup', 'secondary_lookup'], array_column($payload['available_tools'], 'name'));
	}

	public function testParserAcceptsNestedAliasesAndPercentageConfidence(): void {
		$model = new SemanticVerificationQueueModel([[
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => json_encode([
						'assessment' => [
							'status' => 'sufficient',
							'reason' => 'The evidence supports an answer.',
							'decision' => 'finish',
							'score' => '88%',
							'gaps' => []
						]
					])
				]
			]]
		]]);
		$context = $this->createContext($model, [
			AgentToolResult::success('call-1', 'lookup', [], ['found' => true])
		]);
		$verification = (new AgentSemanticVerificationStage())
			->process($context)
			->getPatch()[AgentToolLoopContextKeys::RESULT_VERIFICATIONS][0];

		$this->assertTrue($verification->isVerified());
		$this->assertSame('answer', $verification->getMetadata()['recommendation']);
		$this->assertSame(0.88, $verification->getMetadata()['confidence']);
	}

	public function testMalformedVerifierResponseIsInconclusiveWithoutReopeningByItself(): void {
		$model = new SemanticVerificationQueueModel([[
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => 'not valid verification json'
				]
			]]
		]]);
		$context = $this->createContext($model, [
			AgentToolResult::success('call-1', 'lookup', [], ['found' => true])
		]);
		$result = (new AgentSemanticVerificationStage())->process($context);
		$verification = $result->getPatch()[AgentToolLoopContextKeys::RESULT_VERIFICATIONS][0];

		$this->assertSame(AgentResultVerification::VERDICT_INCONCLUSIVE, $verification->getVerdict());
		$this->assertSame('invalid_semantic_verifier_response', $verification->getIssues()[0]['code']);
		$this->assertSame('invalid', $result->getMetadata()['verification']['parse_status']);
		$this->assertArrayNotHasKey(AgentToolLoopContextKeys::FAILURE_CODE, $result->getPatch());
	}

	/**
	 * @param array<int,AgentToolResult> $observations
	 */
	private function createContext(IAiChatModel $model, array $observations, int $iteration = 1): AgentContext {
		$lookupDefinition = [
			'type' => 'function',
			'function' => [
				'name' => 'lookup',
				'description' => 'Look up authoritative runtime information.',
				'parameters' => [
					'type' => 'object',
					'properties' => ['query' => ['type' => 'string']],
					'required' => ['query']
				]
			]
		];
		$secondaryDefinition = [
			'type' => 'function',
			'function' => [
				'name' => 'secondary_lookup',
				'description' => 'Look up another assigned runtime domain.',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		];
		$catalog = new AgentCapabilityCatalog([
			new AgentCapability('lookup', 'Lookup', 'Look up authoritative runtime information.', '', [], 0, $lookupDefinition, 'lookup-source', 'lookup-source'),
			new AgentCapability('secondary_lookup', 'Secondary lookup', 'Look up another assigned runtime domain.', '', [], 0, $secondaryDefinition, 'secondary-source', 'secondary-source')
		]);

		return new AgentContext(vars: [
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::MESSAGES => [
				['role' => 'system', 'content' => 'You are a test assistant.'],
				['role' => 'user', 'content' => 'Check the plugin status.']
			],
			AgentToolLoopContextKeys::TOOL_RESULTS => [],
			AgentToolLoopContextKeys::TOOL_DEFINITIONS => [$lookupDefinition],
			AgentToolLoopContextKeys::CAPABILITY_CATALOG => $catalog,
			AgentToolLoopContextKeys::CAPABILITY_SELECTION_CONFIG => new AgentCapabilitySelectionConfig(),
			AgentToolLoopContextKeys::OBSERVATIONS => $observations,
			AgentToolLoopContextKeys::MODEL_RESULTS => [],
			AgentToolLoopContextKeys::RESULT_VERIFICATIONS => [],
			AgentToolLoopContextKeys::LOGGER => null,
			AgentToolLoopContextKeys::ITERATION => $iteration,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL,
			AgentToolLoopContextKeys::COMPLETED => true,
			AgentToolLoopContextKeys::FAILURE_CODE => ''
		]);
	}
}

require_once __DIR__ . '/SemanticVerificationQueueModel.php';

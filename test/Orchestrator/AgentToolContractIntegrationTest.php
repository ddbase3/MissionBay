<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentToolContractValidation;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentTool;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\Test\State\StateStoreStub;
use MissionBay\Orchestrator\Service\AgentActionReviewService;
use MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use PHPUnit\Framework\TestCase;

final class AgentToolContractIntegrationTest extends TestCase {

	public function testInvalidOutputBecomesCorrectableToolObservation(): void {
		$model = new ContractQueueChatModel([
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => null,
						'tool_calls' => [[
							'id' => 'call-contract-1',
							'function' => [
								'name' => 'contract_lookup',
								'arguments' => '{"query":"BASE3"}'
							]
						]]
					]
				]]
			],
			[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => 'The tool returned an invalid result contract.'
					]
				]]
			]
		]);
		$tool = new InvalidOutputContractTool();
		$events = new ContractRecordingEventManager();
		$orchestrator = new AgentToolOrchestrator(null, null, [
			new AgentModelDecisionStage(),
			new AgentActionPolicyStage(
				new StaticAgentActionPolicyResolver([new AllowAllAgentActionPolicy()]),
				new AgentActionFingerprint(),
				new AgentActionReviewService(new AgentActionFingerprint(), new StateStoreAgentSuspensionRepository(new StateStoreStub())),
				new AgentToolContractValidationService(),
				new AgentCapabilitySelectionGuardService(),
				'action-policy',
				'action-policy',
				['allow-all-actions']
			),
			new AgentToolExecutionStage($events),
			new AgentToolObservationStage()
		]);

		$result = $orchestrator->run(
			$model,
			[['role' => 'user', 'content' => 'Look up BASE3.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$this->assertTrue($result->isCompleted());
		$this->assertSame(1, $tool->getExecutions());
		$this->assertCount(2, $result->getToolContractValidations());
		$this->assertSame(
			AgentToolContractValidation::STATUS_VALID,
			$result->getToolContractValidations()[0]->getStatus()
		);
		$this->assertSame(
			'tool_output_contract_violation',
			$result->getToolContractValidations()[1]->getReasonCode()
		);

		$toolMessages = array_values(array_filter(
			$model->getCalls()[1][0],
			static fn(array $message): bool => ($message['role'] ?? '') === 'tool'
		));
		$this->assertCount(1, $toolMessages);
		$this->assertStringContainsString('tool_output_contract_violation', (string)$toolMessages[0]['content']);
	}
}

require_once __DIR__ . '/InvalidOutputContractTool.php';
require_once __DIR__ . '/ContractQueueChatModel.php';
require_once __DIR__ . '/ContractRecordingEventManager.php';

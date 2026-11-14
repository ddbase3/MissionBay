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

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentToolContractValidation;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\Test\State\StateStoreStub;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
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

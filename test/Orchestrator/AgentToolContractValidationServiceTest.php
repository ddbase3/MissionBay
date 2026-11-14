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
use Base3\AssistantFoundation\Dto\AgentToolContractValidation;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\CoreFoundation\Api\IOutputSchemaProvider;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use PHPUnit\Framework\TestCase;

final class AgentToolContractValidationServiceTest extends TestCase {

	public function testInvalidInputIsRejectedBeforeToolExecution(): void {
		$service = new AgentToolContractValidationService();
		$validation = $service->validateInput(
			new AiToolCall('call-1', 'lookup', ['limit' => 'five']),
			[new ContractTestTool()]
		);

		$this->assertFalse($validation->passes());
		$this->assertSame('tool_input_contract_violation', $validation->getReasonCode());
		$this->assertSame('$.query', $validation->getIssues()[0]['path']);
	}

	public function testOutputSchemaProviderIsUsedWhenDefinitionHasNoOutputSchema(): void {
		$service = new AgentToolContractValidationService();
		$validation = $service->validateOutput(
			new AiToolCall('call-1', 'lookup', ['query' => 'BASE3']),
			['found' => 1],
			[new ContractTestTool()]
		);

		$this->assertFalse($validation->passes());
		$this->assertSame('tool_output_contract_violation', $validation->getReasonCode());
		$this->assertStringContainsString(IOutputSchemaProvider::class, $validation->getSchemaSource());
	}

	public function testMissingOutputSchemaDoesNotBlockExecution(): void {
		$service = new AgentToolContractValidationService();
		$validation = $service->validateOutput(
			new AiToolCall('call-1', 'plain', []),
			['anything' => true],
			[new NoOutputContractTool()]
		);

		$this->assertTrue($validation->passes());
		$this->assertFalse($validation->isDeclared());
		$this->assertSame(AgentToolContractValidation::STATUS_NOT_DECLARED, $validation->getStatus());
	}
}

require_once __DIR__ . '/ContractTestTool.php';
require_once __DIR__ . '/NoOutputContractTool.php';

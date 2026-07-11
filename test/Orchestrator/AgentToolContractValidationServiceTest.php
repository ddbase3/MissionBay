<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentToolContractValidation;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Api\IOutputSchemaProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
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

final class ContractTestTool implements IAgentTool, IOutputSchemaProvider {

	public static function getName(): string {
		return 'contracttesttool';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'lookup',
				'description' => 'Looks up a record.',
				'parameters' => [
					'type' => 'object',
					'required' => ['query'],
					'properties' => [
						'query' => ['type' => 'string'],
						'limit' => ['type' => 'integer']
					]
				]
			]
		]];
	}

	public function getOutputSchemas(): array {
		return [
			'lookup' => [
				'type' => 'object',
				'required' => ['found'],
				'properties' => [
					'found' => ['type' => 'boolean']
				]
			]
		];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return ['found' => true];
	}
}

final class NoOutputContractTool implements IAgentTool {

	public static function getName(): string {
		return 'nooutputcontracttool';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'plain',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return [];
	}
}

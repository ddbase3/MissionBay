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

final class InvalidOutputContractTool implements IAgentTool {

	private int $executions = 0;

	public static function getName(): string {
		return 'invalidoutputcontracttool';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Contract lookup',
			'outputSchema' => [
				'type' => 'object',
				'required' => ['found'],
				'properties' => [
					'found' => ['type' => 'boolean']
				]
			],
			'function' => [
				'name' => 'contract_lookup',
				'description' => 'Returns a deliberately invalid result for the integration test.',
				'parameters' => [
					'type' => 'object',
					'required' => ['query'],
					'properties' => [
						'query' => ['type' => 'string']
					]
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->executions++;

		return ['found' => 1];
	}

	public function getExecutions(): int {
		return $this->executions;
	}
}

final class ContractQueueChatModel implements IAiChatModel {

	use NormalizedChatModelTrait;

	/** @var array<int,mixed> */
	private array $responses;

	/** @var array<int,array{0:array,1:array}> */
	private array $calls = [];

	/** @param array<int,mixed> $responses */
	public function __construct(array $responses) {
		$this->responses = $responses;
	}

	public function chat(array $messages): string {
		return '';
	}

	public function raw(array $messages, array $tools = []): mixed {
		$this->calls[] = [$messages, $tools];

		return array_shift($this->responses);
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
	}

	public function setOptions(array $options): void {
	}

	public function getOptions(): array {
		return [];
	}

	/** @return array<int,array{0:array,1:array}> */
	public function getCalls(): array {
		return $this->calls;
	}
}

final class ContractRecordingEventManager implements IEventManager {

	public function on(string $event, callable $listener, int $priority = 0): void {
	}

	public function once(string $event, callable $listener, int $priority = 0): void {
	}

	public function off(string $event, callable $listener): void {
	}

	public function fire(object|string $event, ...$args): array {
		return [];
	}
}

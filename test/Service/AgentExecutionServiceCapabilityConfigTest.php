<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use MissionBay\Api\IAgentComponentFlowBuilder;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Service\AgentExecutionService;
use PHPUnit\Framework\TestCase;

final class AgentExecutionServiceCapabilityConfigTest extends TestCase {

	public function testHighLevelCapabilitySettingsAreAppliedToNamedAssistantNode(): void {
		$service = $this->createService();

		$flow = $service->buildEffectiveFlow([
			'agent_flow' => [
				'nodes' => [[
					'id' => 'assistant',
					'type' => 'aiassistantnode',
					'inputs' => ['system' => 'Keep me']
				]],
				'connections' => []
			],
			'capability_sources' => [
				'tools' => ['internal-rag'],
				'providers' => ['github-mcp'],
				'modules' => ['coding-style']
			],
			'capability_selection' => [
				'max_tools' => 12,
				'include_tags' => ['crm']
			]
		]);

		$this->assertSame('Keep me', $flow['nodes'][0]['inputs']['system']);
		$this->assertSame(['internal-rag'], $flow['nodes'][0]['inputs']['capabilitysources']['tools']);
		$this->assertSame(['github-mcp'], $flow['nodes'][0]['inputs']['capabilitysources']['providers']);
		$this->assertSame(['coding-style'], $flow['nodes'][0]['inputs']['capabilitysources']['modules']);
		$this->assertSame(12, $flow['nodes'][0]['inputs']['capabilityselection']['max_tools']);
		$this->assertSame(['crm'], $flow['nodes'][0]['inputs']['capabilityselection']['include_tags']);
		$this->assertSame(0, $this->countResumeConnections($flow, 'assistant'));
	}

	public function testBufferedAssistantIsUsedAsFallbackWhenConfiguredIdIsAbsent(): void {
		$service = $this->createService();

		$flow = $service->buildEffectiveFlow([
			'agent_components_assistant_node' => 'missing',
			'agent_flow' => [
				'nodes' => [[
					'id' => 'buffered',
					'type' => 'aiassistantnode'
				]],
				'connections' => []
			],
			'capability_sources' => ['modules' => ['customer-research']]
		]);

		$this->assertSame(['customer-research'], $flow['nodes'][0]['inputs']['capabilitysources']['modules']);
		$this->assertSame(0, $this->countResumeConnections($flow, 'buffered'));
	}


	public function testNormalRunDoesNotAddResumeConnection(): void {
		$context = $this->createMock(\AssistantFoundation\Api\IAgentContext::class);
		$contextFactory = $this->createMock(IAgentContextFactory::class);
		$contextFactory->method('createContext')->willReturn($context);
		$flow = $this->createMock(\MissionBay\Api\IAgentFlow::class);
		$flow->method('run')->willReturn([]);
		$flowFactory = $this->createMock(IAgentFlowFactory::class);
		$flowFactory->expects($this->once())
			->method('createFromArray')
			->with(
				'strictflow',
				$this->callback(fn(array $effectiveFlow): bool => $this->countResumeConnections($effectiveFlow, 'assistant') === 0),
				$context
			)
			->willReturn($flow);
		$service = new AgentExecutionService(
			$contextFactory,
			$flowFactory,
			$this->createMock(IAgentComponentFlowBuilder::class)
		);

		$service->run($this->minimalAgentSettings(), ['prompt' => 'Hello']);
	}

	public function testResumeRunAddsResumeConnection(): void {
		$context = $this->createMock(\AssistantFoundation\Api\IAgentContext::class);
		$contextFactory = $this->createMock(IAgentContextFactory::class);
		$contextFactory->method('createContext')->willReturn($context);
		$flow = $this->createMock(\MissionBay\Api\IAgentFlow::class);
		$flow->method('run')->willReturn([]);
		$flowFactory = $this->createMock(IAgentFlowFactory::class);
		$flowFactory->expects($this->once())
			->method('createFromArray')
			->with(
				'strictflow',
				$this->callback(fn(array $effectiveFlow): bool => $this->countResumeConnections($effectiveFlow, 'assistant') === 1),
				$context
			)
			->willReturn($flow);
		$service = new AgentExecutionService(
			$contextFactory,
			$flowFactory,
			$this->createMock(IAgentComponentFlowBuilder::class)
		);

		$service->run($this->minimalAgentSettings(), [
			'resume' => [
				'resume_handle' => str_repeat('a', 43),
				'response_text' => 'go',
				'responses' => []
			]
		]);
	}

	public function testExistingResumeConnectionIsNotDuplicated(): void {
		$service = $this->createService();

		$flow = $service->buildEffectiveFlow([
			'agent_flow' => [
				'nodes' => [[
					'id' => 'assistant',
					'type' => 'streamingaiassistantnode'
				]],
				'connections' => [[
					'from' => '__input__',
					'output' => 'resume',
					'to' => 'assistant',
					'input' => 'resume'
				]]
			]
		]);

		$this->assertSame(1, $this->countResumeConnections($flow, 'assistant'));
	}


	/** @return array<string,mixed> */
	private function minimalAgentSettings(): array {
		return [
			'agent_flow' => [
				'nodes' => [[
					'id' => 'assistant',
					'type' => 'streamingaiassistantnode'
				]],
				'connections' => [[
					'from' => '__input__',
					'output' => 'prompt',
					'to' => 'assistant',
					'input' => 'prompt'
				]]
			]
		];
	}

	private function createService(): AgentExecutionService {
		return new AgentExecutionService(
			$this->createMock(IAgentContextFactory::class),
			$this->createMock(IAgentFlowFactory::class),
			$this->createMock(IAgentComponentFlowBuilder::class)
		);
	}

	/** @param array<string,mixed> $flow */
	private function countResumeConnections(array $flow, string $nodeId): int {
		$count = 0;

		foreach (($flow['connections'] ?? []) as $connection) {
			if (!is_array($connection)) {
				continue;
			}
			if (
				(string)($connection['from'] ?? '') === '__input__'
				&& (string)($connection['output'] ?? '') === 'resume'
				&& (string)($connection['to'] ?? '') === $nodeId
				&& (string)($connection['input'] ?? '') === 'resume'
			) {
				$count++;
			}
		}

		return $count;
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentExecutionRequest;
use MissionBay\Api\IAgentComponentFlowBuilder;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Dto\AgentFlowCompilation;
use MissionBay\Service\AgentExecutionService;
use MissionBay\Service\AgentFlowCompiler;
use PHPUnit\Framework\TestCase;

final class AgentExecutionServiceCapabilityConfigTest extends TestCase {

	public function testHighLevelCapabilitySettingsAreAppliedToNamedAssistantNode(): void {
		$flow = $this->createCompiler()->compile([
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
		])->getFlow();

		$this->assertSame('Keep me', $flow['nodes'][0]['inputs']['system']);
		$this->assertSame(['internal-rag'], $flow['nodes'][0]['inputs']['capabilitysources']['tools']);
		$this->assertSame(['github-mcp'], $flow['nodes'][0]['inputs']['capabilitysources']['providers']);
		$this->assertSame(['coding-style'], $flow['nodes'][0]['inputs']['capabilitysources']['modules']);
		$this->assertSame(12, $flow['nodes'][0]['inputs']['capabilityselection']['max_tools']);
		$this->assertSame(['crm'], $flow['nodes'][0]['inputs']['capabilityselection']['include_tags']);
	}

	public function testAssistantIsUsedAsFallbackWhenConfiguredIdIsAbsent(): void {
		$flow = $this->createCompiler()->compile([
			'agent_components_assistant_node' => 'missing',
			'agent_flow' => [
				'nodes' => [[
					'id' => 'buffered',
					'type' => 'aiassistantnode'
				]],
				'connections' => []
			],
			'capability_sources' => ['modules' => ['customer-research']]
		])->getFlow();

		$this->assertSame(['customer-research'], $flow['nodes'][0]['inputs']['capabilitysources']['modules']);
	}

	public function testLegacyStreamingNodeIsNormalizedDuringCompilation(): void {
		$compilation = $this->createCompiler()->compile([
			'agent_flow' => [
				'nodes' => [[
					'id' => 'assistant',
					'type' => 'streamingaiassistantnode'
				]],
				'connections' => []
			]
		]);

		$this->assertSame('aiassistantnode', $compilation->getFlow()['nodes'][0]['type']);
		$this->assertNotEmpty($compilation->getWarnings());
	}

	public function testNormalExecutionDoesNotAddResumeConnection(): void {
		$service = $this->createExecutionService(false);

		$service->execute(new AgentExecutionRequest(
			$this->minimalAgentSettings(),
			['prompt' => 'Hello']
		));
	}

	public function testResumeExecutionAddsResumeConnection(): void {
		$service = $this->createExecutionService(true);

		$service->execute(new AgentExecutionRequest(
			$this->minimalAgentSettings(),
			[
				'resume' => [
					'resume_handle' => str_repeat('a', 43),
					'response_text' => 'go',
					'responses' => []
				]
			]
		));
	}

	private function createCompiler(): AgentFlowCompiler {
		return new AgentFlowCompiler($this->createMock(IAgentComponentFlowBuilder::class));
	}

	private function createExecutionService(bool $expectResumeConnection): AgentExecutionService {
		$context = $this->createMock(IAgentContext::class);
		$contextFactory = $this->createMock(IAgentContextFactory::class);
		$contextFactory->method('createContext')->willReturn($context);

		$flow = $this->createMock(IAgentFlow::class);
		$flow->method('run')->willReturn([]);

		$flowFactory = $this->createMock(IAgentFlowFactory::class);
		$flowFactory->expects($this->once())
			->method('createFromArray')
			->with(
				'strictflow',
				$this->callback(function(array $effectiveFlow) use ($expectResumeConnection): bool {
					return $this->countResumeConnections($effectiveFlow, 'assistant') === ($expectResumeConnection ? 1 : 0);
				}),
				$context
			)
			->willReturn($flow);

		$compiler = $this->createMock(IAgentFlowCompiler::class);
		$compiler->method('compile')->willReturn(new AgentFlowCompilation(
			$this->minimalAgentSettings()['agent_flow']
		));

		return new AgentExecutionService($contextFactory, $flowFactory, $compiler);
	}

	/** @return array<string,mixed> */
	private function minimalAgentSettings(): array {
		return [
			'agent_flow' => [
				'nodes' => [[
					'id' => 'assistant',
					'type' => 'aiassistantnode'
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

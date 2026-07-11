<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use MissionBay\Api\IAgentComponentFlowBuilder;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Service\AgentExecutionService;
use PHPUnit\Framework\TestCase;

final class AgentExecutionServiceCapabilityConfigTest extends TestCase {

	public function testHighLevelCapabilitySettingsAreAppliedToNamedAssistantNode(): void {
		$service = new AgentExecutionService(
			$this->createMock(IAgentContextFactory::class),
			$this->createMock(IAgentFlowFactory::class),
			$this->createMock(IAgentComponentFlowBuilder::class)
		);

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
	}

	public function testBufferedAssistantIsUsedAsFallbackWhenConfiguredIdIsAbsent(): void {
		$service = new AgentExecutionService(
			$this->createMock(IAgentContextFactory::class),
			$this->createMock(IAgentFlowFactory::class),
			$this->createMock(IAgentComponentFlowBuilder::class)
		);

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
	}
}

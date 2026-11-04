<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Assistant;

use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use AssistantFoundation\Dto\AgentStageMount;
use AssistantFoundation\Dto\AgentStageSlot;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Dto\Assistant\AgentCapabilityDiscoveryResult;
use PHPUnit\Framework\TestCase;

final class AgentCapabilityDiscoveryResultTest extends TestCase {

	public function testEmptyDiscoveryHasNoErrorsAndNoExposedRuntimeObjects(): void {
		$sources = AgentCapabilitySourceConfig::fromArray([]);
		$result = new AgentCapabilityDiscoveryResult($sources);
		$this->assertSame($sources, $result->getSourceConfig());
		$this->assertFalse($result->hasErrors());
		$this->assertSame([], $result->getTools());
		$this->assertSame([], $result->getResourceProviders());
		$this->assertSame([], $result->getPromptProviders());
		$this->assertSame(0, $result->toArray()['tool_count']);
		$this->assertSame([], $result->toArray()['errors']);
	}

	public function testReportIncludesResolutionIdentifiersWithoutExecutableObjects(): void {
		$sources = AgentCapabilitySourceConfig::fromArray([]);
		$tool = $this->createStub(IAgentTool::class);
		$resources = $this->createStub(IAgentResourceProvider::class);
		$prompts = $this->createStub(IAgentPromptProvider::class);
		$mount = new AgentStageMount(AgentStageSlot::BEFORE_TOOL_CALL, $this->createStub(IAgentStage::class));
		$result = new AgentCapabilityDiscoveryResult(
			sourceConfig: $sources,
			tools: [$tool], resourceProviders: [$resources], promptProviders: [$prompts],
			instructions: ['use citations'], stageMounts: [$mount],
			resolvedToolIds: ['search'], resolvedProviderIds: ['catalog'], resolvedModuleIds: ['module'],
			resolvedResourceProviderIds: ['resources'], resolvedPromptProviderIds: ['prompts'],
			warnings: ['missing optional'], errors: ['unavailable required']
		);
		$report = $result->toArray();
		$this->assertTrue($result->hasErrors());
		$this->assertSame([$tool], $result->getTools());
		$this->assertSame([$resources], $result->getResourceProviders());
		$this->assertSame([$prompts], $result->getPromptProviders());
		$this->assertSame([$mount], $result->getStageMounts());
		$this->assertSame(['missing optional'], $result->getWarnings());
		$this->assertSame(['unavailable required'], $result->getErrors());
		$this->assertSame(1, $report['tool_count']);
		$this->assertSame(1, $report['resource_provider_count']);
		$this->assertSame(1, $report['prompt_provider_count']);
		$this->assertSame(1, $report['instruction_count']);
		$this->assertSame(1, $report['stage_mount_count']);
		$this->assertSame(['search'], $report['resolved']['tools']);
		$this->assertSame(['catalog'], $report['resolved']['providers']);
		$this->assertSame(['module'], $report['resolved']['modules']);
		$this->assertSame(['resources'], $report['resolved']['resourceProviders']);
		$this->assertSame(['prompts'], $report['resolved']['promptProviders']);
		$this->assertArrayNotHasKey('tools', $report);
	}
}

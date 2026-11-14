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

namespace Base3\MissionBay\Test\Dto\Assistant;

use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use Base3\AssistantFoundation\Dto\AgentStageMount;
use Base3\AssistantFoundation\Dto\AgentStageSlot;
use Base3\MissionBay\Api\IAgentPromptProvider;
use Base3\MissionBay\Api\IAgentResourceProvider;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Dto\Assistant\AgentCapabilityDiscoveryResult;
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

<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Ai;

use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantTurnService;
use MissionBay\Api\IAgentProfileSelector;
use MissionBay\Api\IAgentTool;
use MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use MissionBay\Node\Ai\AiAssistantNode;
use PHPUnit\Framework\TestCase;

final class AiAssistantNodeConfigurationTest extends TestCase {

	public function testInputAndOutputPortsExposeResumeAndInteractionContracts(): void {
		$node = $this->node();
		$this->assertSame('aiassistantnode', AiAssistantNode::getName());
		$this->assertSame([
			'prompt', 'resume', 'system', 'maxtoolloops', 'orchestratorprofile', 'modeldecision',
			'deliberateplanning', 'stages', 'budget', 'capabilitysources', 'capabilityselection',
			'toolcache', 'mode'
		], array_map(static fn($port): string => $port->name, $node->getInputDefinitions()));
		$this->assertSame([
			'message', 'tool_calls', 'status', 'interaction_requests', 'resume_handle', 'warning', 'error'
		], array_map(static fn($port): string => $port->name, $node->getOutputDefinitions()));
		$this->assertSame('chat', $node->getInputDefinitions()[12]->default);
	}

	public function testDocksKeepModelRequiredAndOptionalContributorsSeparate(): void {
		$docks = $this->node()->getDockDefinitions();
		$this->assertSame(['chatmodel', 'memory', 'contextcontributors', 'logger', 'tools', 'profileselector'], array_map(static fn($dock): string => $dock->name, $docks));
		$this->assertTrue($docks[0]->required);
		$this->assertFalse($docks[1]->required);
		$this->assertFalse($docks[2]->required);
		$this->assertSame(512, $docks[4]->maxConnections);
	}

	public function testNodeIdAndConfigRemainOwnedByTheExistingNode(): void {
		$node = $this->node();
		$this->assertSame('assistant-node', $node->getId());
		$node->setId('updated-node');
		$node->setConfig(['mode' => 'chat', 'tools' => ['one']]);
		$this->assertSame('updated-node', $node->getId());
		$this->assertSame(['mode' => 'chat', 'tools' => ['one']], $node->getConfig());
	}

	public function testMissingChatModelIsRejectedDuringResourceConstruction(): void {
		$this->expectException(\RuntimeException::class);
		$this->buildResources(['chatmodel' => ['not a model']]);
	}

	public function testOnlyValidDockedResourcesArePassedToTurnService(): void {
		$model = $this->createStub(IAiChatModel::class);
		$memory = $this->createStub(IAgentMemory::class);
		$tool = $this->createStub(IAgentTool::class);
		$contributor = $this->createStub(IAgentContextContributor::class);
		$selector = $this->createStub(IAgentProfileSelector::class);
		$resources = $this->buildResources([
			'chatmodel' => [$model],
			'memory' => ['invalid', $memory],
			'contextcontributors' => ['invalid', $contributor],
			'tools' => [123, $tool],
			'profileselector' => [$selector]
		]);
		$this->assertSame($model, $resources->getModel());
		$this->assertSame([$memory], $resources->getMemories());
		$this->assertSame([$contributor], $resources->getContextContributors());
		$this->assertSame([$tool], $resources->getTools());
		$this->assertSame($selector, $resources->getProfileSelector());
	}

	public function testContextContributorConnectedToBothDocksIsDeduplicated(): void {
		$contributor = $this->createStub(IAgentContextContributor::class);
		$resources = $this->buildResources([
			'chatmodel' => [$this->createStub(IAiChatModel::class)],
			'contextcontributors' => [$contributor, $contributor],
			'memory' => [$contributor]
		]);
		$this->assertSame([$contributor], $resources->getContextContributors());
		$this->assertSame([], $resources->getMemories());
	}

	public function testDefaultTurnOptionsUseSafeChatConfiguration(): void {
		$options = $this->buildOptions([]);
		$this->assertSame('', $options->getPrompt());
		$this->assertSame('You are a helpful assistant.', $options->getSystem());
		$this->assertSame(10, $options->getMaxToolLoops());
		$this->assertSame('chat', $options->getMode());
		$this->assertTrue($options->areToolsEnabled());
		$this->assertTrue($options->isMemoryReadEnabled());
		$this->assertTrue($options->isMemoryWriteEnabled());
		$this->assertFalse($options->isDeliberatePlanningEnabled());
		$this->assertSame([], $options->getStageIds());
		$this->assertNull($options->getResume());
		$this->assertSame('assistant-node', $options->getNodeId());
		$this->assertSame('msg-constant', $options->getAssistantMessageId());
	}

	public function testPromptSystemAndModeAreNormalizedWithoutChangingTheNodeId(): void {
		$options = $this->buildOptions([
			'prompt' => '  user prompt  ',
			'system' => '   ',
			'mode' => '  SUGGESTIONS  '
		]);
		$this->assertSame('user prompt', $options->getPrompt());
		$this->assertSame('You are a helpful assistant.', $options->getSystem());
		$this->assertSame('suggestions', $options->getMode());
		$this->assertSame('assistant-node', $options->getNodeId());
	}

	public function testBooleanPlanningInputsAreParsedConsistently(): void {
		foreach (['yes', 'true', 'ON', '1', 1, 3, true] as $value) {
			$this->assertTrue($this->buildOptions(['deliberateplanning' => $value])->isDeliberatePlanningEnabled());
		}
		foreach (['no', 'off', 'false', '0', 0, false, null, ''] as $value) {
			$this->assertFalse($this->buildOptions(['deliberateplanning' => $value])->isDeliberatePlanningEnabled());
		}
	}

	public function testMaxToolLoopsAcceptsNumericInputsAndDefaults(): void {
		$this->assertSame(3, $this->buildOptions(['maxtoolloops' => 3])->getMaxToolLoops());
		$this->assertSame(4, $this->buildOptions(['maxtoolloops' => '4'])->getMaxToolLoops());
		$this->assertSame(2, $this->buildOptions(['maxtoolloops' => 2.9])->getMaxToolLoops());
		$this->assertSame(10, $this->buildOptions(['maxtoolloops' => ''])->getMaxToolLoops());
		$this->assertSame(10, $this->buildOptions(['maxtoolloops' => null])->getMaxToolLoops());
	}

	public function testMaxToolLoopsRejectsZeroNegativeOrNonnumericValues(): void {
		foreach ([0, -1, '0', 'invalid', [], new \stdClass()] as $value) {
			try {
				$this->buildOptions(['maxtoolloops' => $value]);
				$this->fail('Invalid tool loop count was accepted');
			} catch (\RuntimeException $e) {
				$this->assertStringContainsString('maxtoolloops', strtolower($e->getMessage()));
			}
		}
	}

	public function testConfiguredStagesAreNormalizedAndOrderIsPreserved(): void {
		$this->assertSame(['preparation', 'execution'], $this->buildOptions(['stages' => [' preparation ', 'execution']])->getStageIds());
	}

	public function testNonArrayStageIdsAreRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->buildOptions(['stages' => 'execution']);
	}

	public function testDuplicateStageIdsAreRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->buildOptions(['stages' => ['one', ' one ']]);
	}

	public function testEmptyStageIdsAreRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->buildOptions(['stages' => ['one', '   ']]);
	}

	public function testNonStringStageIdsAreRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->buildOptions(['stages' => [1, 'execution']]);
	}

	public function testInvalidModelDecisionConfigurationIsRejectedBeforeTurnExecution(): void {
		$this->expectException(\RuntimeException::class);
		$this->buildOptions(['modeldecision' => 'native']);
	}

	public function testNamedModelDecisionStrategyIsPreserved(): void {
		$options = $this->buildOptions(['modeldecision' => ['strategy' => 'native-model-decision']]);
		$this->assertSame('native-model-decision', $options->getModelDecisionConfig()->getStrategy());
		$this->assertFalse($options->getModelDecisionConfig()->isRepairEnabled());
	}

	public function testInvalidCapabilitySelectionConfigurationIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->buildOptions(['capabilityselection' => 'all']);
	}

	public function testInvalidCapabilitySourceConfigurationIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->buildOptions(['capabilitysources' => 'all']);
	}

	public function testInvalidBudgetConfigurationIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->buildOptions(['budget' => 'unlimited']);
	}

	public function testInvalidToolCacheConfigurationIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->buildOptions(['toolcache' => 'on']);
	}

	public function testInvalidResumeConfigurationIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->buildOptions(['resume' => 'opaque-handle']);
	}

	public function testResumeRequiresAnOpaqueHandle(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->buildOptions(['resume' => ['response_text' => 'yes']]);
	}

	public function testResumeHandleAndResponseTextArePreserved(): void {
		$options = $this->buildOptions(['resume' => ['resume_handle' => 'handle-7', 'response_text' => 'approved']]);
		$this->assertSame('handle-7', $options->getResume()->getResumeHandle());
		$this->assertSame('approved', $options->getResume()->getResponseText());
	}

	public function testAllOptionalConfigurationsAcceptNullOrEmptyInput(): void {
		$options = $this->buildOptions([
			'modeldecision' => null,
			'capabilityselection' => '',
			'capabilitysources' => null,
			'toolcache' => '',
			'budget' => null,
			'resume' => null,
			'stages' => ''
		]);
		$this->assertNull($options->getResume());
		$this->assertSame([], $options->getStageIds());
		$this->assertSame('ai-guarded-model-decision', $options->getModelDecisionConfig()->getStrategy());
	}

	private function node(): AiAssistantNode {
		return new AiAssistantNode(
			$this->createStub(IAgentAssistantTurnService::class),
			$this->createStub(IAgentAssistantFinalResponseService::class),
			$this->createStub(IAgentAssistantMemoryService::class),
			$this->createStub(IAgentAssistantFallbackBuilder::class),
			'assistant-node'
		);
	}

	private function buildOptions(array $inputs): AgentAssistantTurnOptions {
		$node = $this->node();
		$mode = (new \ReflectionMethod(AiAssistantNode::class, 'readInputMode'))->invoke($node, $inputs);
		$method = new \ReflectionMethod(AiAssistantNode::class, 'buildTurnOptions');
		return $method->invoke($node, $inputs, 'msg-constant', true, true, true, $mode);
	}

	private function buildResources(array $resources): AgentAssistantTurnResources {
		$method = new \ReflectionMethod(AiAssistantNode::class, 'buildTurnResources');
		return $method->invoke($this->node(), $resources, 'Missing required chat model.');
	}
}

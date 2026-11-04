<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use MissionBay\Audit\AgentToolAuditContext;
use MissionBay\Context\AgentContext;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;
use MissionBay\Resource\ConfiguredAgentToolResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredAgentToolResource::class)]
final class ConfiguredAgentToolResourceBehaviorCoverageTest extends TestCase {

	private function wrapper(array $config = [], ?ConfiguredAgentToolCoverageTool $tool = null, ?ConfiguredToolRecordingEventManager $events = null): ConfiguredAgentToolResource {
		$wrapper = new ConfiguredAgentToolResource(new ConfiguredAgentToolCoverageResolver(), $events ?? new ConfiguredToolRecordingEventManager(), 'configured_fixture');
		$wrapper->setConfig($config);
		$wrapper->init($tool !== null ? ['tool' => [$tool]] : [], new AgentContext());
		return $wrapper;
	}

	public function testWrapperHasCanonicalNameAndRequiredToolDock(): void {
		$wrapper = $this->wrapper();
		$this->assertSame('configuredagenttoolresource', $wrapper::getName());
		$this->assertSame('configured_fixture', $wrapper->getId());
		$this->assertCount(1, $wrapper->getDockDefinitions());
	}

	public function testDefaultMetadataFallsBackToResourceId(): void {
		$wrapper = $this->wrapper();
		$this->assertSame('configured_fixture', $wrapper->getCapabilitySourceId());
		$this->assertSame('configured_fixture', $wrapper->getCapabilitySourceLabel());
		$this->assertSame('', $wrapper->getCapabilitySourceDescription());
	}

	public function testExplicitCapabilitySourceMetadataIsNormalized(): void {
		$wrapper = $this->wrapper();
		$wrapper->setCapabilitySourceMetadata(' source-1 ', ' Alias ', ' Summary ');
		$this->assertSame('source-1', $wrapper->getCapabilitySourceId());
		$this->assertSame('Alias', $wrapper->getCapabilitySourceLabel());
		$this->assertSame('Summary', $wrapper->getCapabilitySourceDescription());
	}

	public function testExplicitSourceIdSuppliesLabelWhenLabelMissing(): void {
		$wrapper = $this->wrapper();
		$wrapper->setCapabilitySourceMetadata('source-1');
		$this->assertSame('source-1', $wrapper->getCapabilitySourceLabel());
	}

	public function testMissingDockedToolProducesNoCallableDefinitions(): void {
		$this->assertSame([], $this->wrapper()->getToolDefinitions());
	}

	public function testDisabledWrapperProducesNoCallableDefinitions(): void {
		$this->assertSame([], $this->wrapper(['enabled' => false], new ConfiguredAgentToolCoverageTool())->getToolDefinitions());
	}

	public function testFalseLikeEnabledValuesDisableTool(): void {
		foreach (['false', 'off', 'NO', 0] as $value) {
			$this->assertSame([], $this->wrapper(['enabled' => $value], new ConfiguredAgentToolCoverageTool())->getToolDefinitions());
		}
	}

	public function testTrueLikeEnabledValuesKeepToolAvailable(): void {
		foreach (['true', 'yes', 'on', 1] as $value) {
			$this->assertCount(2, $this->wrapper(['enabled' => $value], new ConfiguredAgentToolCoverageTool())->getToolDefinitions());
		}
	}

	public function testNullEnabledConfigurationRetainsDefaultAvailability(): void {
		$this->assertCount(2, $this->wrapper(['enabled' => null], new ConfiguredAgentToolCoverageTool())->getToolDefinitions());
	}

	public function testNamespacePrefixesToolNamesAndPreservesUnderlyingDefinitions(): void {
		$tool = new ConfiguredAgentToolCoverageTool();
		$definitions = $this->wrapper(['namespace' => 'team'], $tool)->getToolDefinitions();
		$this->assertSame('team__lookup', $definitions[0]['function']['name']);
		$this->assertSame('team__ping', $definitions[1]['function']['name']);
		$this->assertSame('lookup', $tool->definitions[0]['function']['name']);
	}

	public function testNamespaceRejectsLeadingNumber(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->wrapper(['namespace' => '2invalid']);
	}

	public function testNamespaceRejectsWhitespaceAndSeparators(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->wrapper(['namespace' => 'bad-namespace']);
	}

	public function testNamespaceCanBeginWithUnderscore(): void {
		$definitions = $this->wrapper(['namespace' => '_private'], new ConfiguredAgentToolCoverageTool())->getToolDefinitions();
		$this->assertSame('_private__lookup', $definitions[0]['function']['name']);
	}

	public function testBlankNamespaceLeavesToolNameUnchanged(): void {
		$this->assertSame('lookup', $this->wrapper(['namespace' => '  '], new ConfiguredAgentToolCoverageTool())->getToolDefinitions()[0]['function']['name']);
	}

	public function testWrapperOverridesLabelsDescriptionsCategoriesTagsAndPriorities(): void {
		$definitions = $this->wrapper([
			'label' => 'Custom label', 'description' => 'Custom description', 'category' => 'Operations',
			'tags' => ' ops,secure,ops, ', 'priority' => '3'
		], new ConfiguredAgentToolCoverageTool())->getToolDefinitions();
		$this->assertSame('Custom label', $definitions[0]['label']);
		$this->assertSame('Custom description', $definitions[0]['function']['description']);
		$this->assertSame('Operations', $definitions[0]['category']);
		$this->assertSame(['ops', 'secure'], $definitions[0]['tags']);
		$this->assertSame(3, $definitions[0]['priority']);
	}

	public function testWrapperWithNoOverridesPreservesOriginalLabels(): void {
		$definitions = $this->wrapper([], new ConfiguredAgentToolCoverageTool())->getToolDefinitions();
		$this->assertSame('Read', $definitions[0]['label']);
		$this->assertArrayNotHasKey('label', $definitions[1]);
	}

	public function testNonArrayAndNamelessDefinitionsAreSkipped(): void {
		$tool = new ConfiguredAgentToolCoverageTool();
		$tool->definitions = ['invalid', [], ['function' => ['name' => '   ']], ['function' => ['name' => 'valid']]];
		$definitions = $this->wrapper([], $tool)->getToolDefinitions();
		$this->assertCount(1, $definitions);
		$this->assertSame('valid', $definitions[0]['function']['name']);
	}

	public function testOutputSchemasAreMappedToEffectiveNames(): void {
		$wrapper = $this->wrapper(['namespace' => 'sec'], new ConfiguredAgentToolCoverageTool());
		$this->assertSame([
			'sec__lookup' => ['type' => 'string'], 'sec__ping' => ['type' => 'boolean']
		], $wrapper->getOutputSchemas());
	}

	public function testOutputSchemasOfDisabledWrapperAreEmpty(): void {
		$this->assertSame([], $this->wrapper(['enabled' => false], new ConfiguredAgentToolCoverageTool())->getOutputSchemas());
	}

	public function testOutputSchemasWithoutToolAreEmpty(): void {
		$this->assertSame([], $this->wrapper()->getOutputSchemas());
	}

	public function testToolExecutionTranslatesNamespaceToOriginalName(): void {
		$tool = new ConfiguredAgentToolCoverageTool();
		$wrapper = $this->wrapper(['namespace' => 'ops'], $tool);
		$result = $wrapper->callTool('ops__lookup', ['id' => 14], new AgentContext());
		$this->assertSame(['called' => 'lookup', 'arguments' => ['id' => 14]], $result);
		$this->assertSame([['lookup', ['id' => 14]]], $tool->calls);
	}

	public function testUnknownConfiguredFunctionIsRejected(): void {
		$wrapper = $this->wrapper([], new ConfiguredAgentToolCoverageTool());
		$this->expectException(\InvalidArgumentException::class);
		$wrapper->callTool('unknown', [], new AgentContext());
	}

	public function testCallingDisabledWrapperIsRejected(): void {
		$wrapper = $this->wrapper(['enabled' => false], new ConfiguredAgentToolCoverageTool());
		$this->expectException(\InvalidArgumentException::class);
		$wrapper->callTool('lookup', [], new AgentContext());
	}

	public function testCallingWrapperWithoutDockedToolIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->wrapper()->callTool('lookup', [], new AgentContext());
	}

	public function testSuccessEmitsStartedAndFinishedEventsWithMatchingCalls(): void {
		$events = new ConfiguredToolRecordingEventManager();
		$wrapper = $this->wrapper(['namespace' => 'ops'], new ConfiguredAgentToolCoverageTool(), $events);
		$wrapper->callTool('ops__lookup', ['id' => 8], new AgentContext());
		$fired = $events->getFiredEvents();
		$this->assertCount(2, $fired);
		$this->assertTrue($fired[0] instanceof MissionBayToolStartedEvent);
		$this->assertTrue($fired[1] instanceof MissionBayToolFinishedEvent);
		$this->assertSame('ops__lookup', $fired[0]->getToolName());
		$this->assertSame('lookup', $fired[1]->getTrace()['original_tool_name']);
	}

	public function testFinishedEventContainsToolDuration(): void {
		$events = new ConfiguredToolRecordingEventManager();
		$wrapper = $this->wrapper([], new ConfiguredAgentToolCoverageTool(), $events);
		$wrapper->callTool('lookup', [], new AgentContext());
		$trace = $events->getFiredEvents()[1]->getTrace();
		$this->assertArrayHasKey('tool_execution_ms', $trace['orchestrator_timing']);
	}

	public function testAuditContextCorrelatesResourceNodeIterationAndCallIndex(): void {
		$context = new AgentContext();
		$events = new ConfiguredToolRecordingEventManager();
		$wrapper = $this->wrapper([], new ConfiguredAgentToolCoverageTool(), $events);
		AgentToolAuditContext::push($context, [
			'source' => 'agent', 'node_id' => 'node-4', 'call_id' => 'call-99',
			'iteration' => 3, 'call_index' => 6, 'trace' => ['turn_id' => 'turn-3']
		]);
		$wrapper->callTool('lookup', [], $context);
		$fired = $events->getFiredEvents();
		$this->assertSame('node-4', $fired[0]->getNodeId());
		$this->assertSame('call-99', $fired[0]->getCallId());
		$this->assertSame('turn-3', $fired[0]->getTrace()['turn_id']);
	}

	public function testMcpAuditContextAttachesProfileIdentity(): void {
		$context = new AgentContext();
		$context->setVar('mcp', true);
		$context->setVar('mcp_profile_id', 'restricted');
		$context->setVar('mcp_profile_label', 'Restricted Profile');
		$events = new ConfiguredToolRecordingEventManager();
		$this->wrapper([], new ConfiguredAgentToolCoverageTool(), $events)->callTool('lookup', [], $context);
		$trace = $events->getFiredEvents()[0]->getTrace();
		$this->assertSame('mcp', $trace['source']);
		$this->assertSame('restricted', $trace['mcp_profile_id']);
		$this->assertSame('Restricted Profile', $trace['mcp_profile_label']);
		$this->assertSame('mcp:restricted', $trace['chatbot_key']);
	}

	public function testFailureEmitsStartedThenFailedEventAndRethrows(): void {
		$tool = new ConfiguredAgentToolCoverageTool();
		$tool->throwOnCall = true;
		$events = new ConfiguredToolRecordingEventManager();
		$wrapper = $this->wrapper([], $tool, $events);
		try {
			$wrapper->callTool('lookup', [], new AgentContext());
			$this->fail('Expected the tool exception to be rethrown.');
		}
		catch (\RuntimeException $e) {
			$this->assertSame('Tool execution failed', $e->getMessage());
		}
		$this->assertTrue($events->getFiredEvents()[0] instanceof MissionBayToolStartedEvent);
		$this->assertTrue($events->getFiredEvents()[1] instanceof MissionBayToolFailedEvent);
	}

	public function testPlainToolDoesNotProvideBatchOrConfirmationCapabilities(): void {
		$wrapper = $this->wrapper([], new ConfiguredAgentToolCoverageTool());
		$this->assertFalse($wrapper->isBatchFunction('lookup'));
		$this->assertFalse($wrapper->supportsConfirmation());
		$this->assertNull($wrapper->getConfirmationRequest('lookup', [], new AgentContext()));
	}

	public function testNonBatchToolRejectsBatchExpansion(): void {
		$wrapper = $this->wrapper([], new ConfiguredAgentToolCoverageTool());
		$action = new AgentAction('action-1', AgentAction::TYPE_TOOL_CALL, 'lookup');
		$snapshot = new AgentMutationCommitSnapshot('action-1', 'hash-1');
		$this->expectException(\RuntimeException::class);
		$wrapper->expandApprovedBatch($action, $snapshot, new AgentContext());
	}

	public function testNonGuardedToolRejectsMutationSnapshot(): void {
		$wrapper = $this->wrapper([], new ConfiguredAgentToolCoverageTool());
		$action = new AgentAction('action-1', AgentAction::TYPE_TOOL_CALL, 'lookup');
		$this->expectException(\RuntimeException::class);
		$wrapper->captureMutationCommitSnapshot($action, 'hash-1', new AgentContext());
	}

	public function testInitWithNoToolClearsPreviouslyDockedTool(): void {
		$wrapper = $this->wrapper([], new ConfiguredAgentToolCoverageTool());
		$this->assertCount(2, $wrapper->getToolDefinitions());
		$wrapper->init([], new AgentContext());
		$this->assertSame([], $wrapper->getToolDefinitions());
	}

	public function testSetConfigReplacesEarlierNamespaceAndLabel(): void {
		$wrapper = $this->wrapper(['namespace' => 'old', 'label' => 'Old'], new ConfiguredAgentToolCoverageTool());
		$wrapper->setConfig(['namespace' => 'new']);
		$definitions = $wrapper->getToolDefinitions();
		$this->assertSame('new__lookup', $definitions[0]['function']['name']);
		$this->assertSame('Read', $definitions[0]['label']);
	}
}

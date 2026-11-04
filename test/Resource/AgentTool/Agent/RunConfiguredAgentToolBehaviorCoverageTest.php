<?php declare(strict_types=1);

namespace MissionBay\Test\Resource\AgentTool\Agent;

use MissionBay\Context\AgentContext;
use MissionBay\Resource\AgentTool\Agent\RunConfiguredAgentTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/RunConfiguredAgentTestConfigResolver.php';
require_once __DIR__ . '/RunConfiguredAgentTestExecutionService.php';
require_once __DIR__ . '/RunConfiguredAgentTestSettingsStore.php';

#[CoversClass(RunConfiguredAgentTool::class)]
final class RunConfiguredAgentToolBehaviorCoverageTest extends TestCase {

	private function tool(
		RunConfiguredAgentTestSettingsStore $settings,
		RunConfiguredAgentTestExecutionService $execution,
		array $config = []
	): RunConfiguredAgentTool {
		$tool = new RunConfiguredAgentTool(new RunConfiguredAgentTestConfigResolver(), $settings, $execution, 'subagent_fixture');
		$tool->setConfig($config);
		return $tool;
	}

	private function settings(): RunConfiguredAgentTestSettingsStore {
		$settings = new RunConfiguredAgentTestSettingsStore();
		$settings->set('agent', 'worker', ['label' => 'Worker Agent', 'user_prompt' => 'Default task', 'system_prompt' => 'System policy']);
		return $settings;
	}

	private function call(
		RunConfiguredAgentTestSettingsStore $settings,
		RunConfiguredAgentTestExecutionService $execution,
		array $config = [],
		array $arguments = []
	): array {
		$config = ['agent_id' => 'worker'] + $config;
		$tool = $this->tool($settings, $execution, $config);
		$name = $tool->getToolDefinitions()[0]['function']['name'];
		return $tool->callTool($name, $arguments, new AgentContext());
	}

	public function testTechnicalNameAndSchemaDescribeMandatoryAgentId(): void {
		$tool = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService());
		$this->assertSame('runconfiguredagenttool', $tool::getName());
		$this->assertSame(['agent_id'], $tool->getSchema()['required']);
		$this->assertSame('object', $tool->getSchema()['type']);
	}

	public function testDefaultToolDefinitionUsesConfiguredFunctionShape(): void {
		$tool = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService(), ['agent_id' => 'worker']);
		$definitions = $tool->getToolDefinitions();
		$this->assertCount(1, $definitions);
		$this->assertSame('run_configured_agent', $definitions[0]['function']['name']);
		$this->assertSame(['agent', 'sub-agent', 'delegation'], $definitions[0]['tags']);
		$this->assertSame([], $definitions[0]['function']['parameters']['required']);
	}

	public function testConfiguredNameLabelAndDescriptionOverrideDefaults(): void {
		$tool = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService(), [
			'agent_id' => 'worker', 'tool_name' => 'Delegate Case', 'tool_label' => 'Delegate', 'tool_description' => 'Handle a case.'
		]);
		$definition = $tool->getToolDefinitions()[0];
		$this->assertSame('Delegate_Case', $definition['function']['name']);
		$this->assertSame('Delegate', $definition['label']);
		$this->assertSame('Handle a case.', $definition['function']['description']);
	}

	public function testMalformedToolNameFallsBackToDefault(): void {
		$tool = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService(), ['tool_name' => '?!']);
		$this->assertSame('run_configured_agent', $tool->getToolDefinitions()[0]['function']['name']);
	}

	public function testTechnicalAgentIdIsNormalizedBeforeSettingsLookup(): void {
		$settings = $this->settings();
		$execution = new RunConfiguredAgentTestExecutionService();
		$result = $this->call($settings, $execution, ['agent_id' => ' WOrker +'], ['user_prompt' => 'Do it']);
		$this->assertTrue($result['ok']);
		$this->assertSame([['agent', 'worker']], $settings->reads);
	}

	public function testEmptyCustomLabelFallsBackToDefault(): void {
		$tool = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService(), ['tool_label' => '   ']);
		$this->assertSame('Run Agent', $tool->getToolDefinitions()[0]['label']);
	}

	public function testDescriptionExplainsWhetherPromptOverrideIsAllowed(): void {
		$allowed = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService(), ['agent_id' => 'worker']);
		$fixed = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService(), ['agent_id' => 'worker', 'allow_prompt_override' => false]);
		$this->assertStringContainsString('Provide user_prompt', $allowed->getToolDefinitions()[0]['function']['description']);
		$this->assertStringContainsString('fixed by configuration', $fixed->getToolDefinitions()[0]['function']['description']);
	}

	public function testMemoryContributionDisabledByConfiguration(): void {
		$tool = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService(), ['agent_id' => 'worker', 'memory_enabled' => false]);
		$this->assertSame([], $tool->contribute(new AgentContext()));
	}

	public function testMemoryContributionDisabledWithoutAgentId(): void {
		$tool = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService());
		$this->assertSame([], $tool->contribute(new AgentContext()));
	}

	public function testMemoryContributionIncludesTrustedAgentLabelAndToolName(): void {
		$tool = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService(), ['agent_id' => 'worker', 'tool_name' => 'delegate_work']);
		$blocks = $tool->contribute(new AgentContext());
		$this->assertCount(1, $blocks);
		$this->assertStringContainsString('delegate_work', $blocks[0]->getContent());
		$this->assertStringContainsString('Worker Agent', $blocks[0]->getContent());
		$this->assertSame('subagent_fixture', $blocks[0]->getSource());
	}

	public function testMemoryContributionOmitsLabelWhenLookupFails(): void {
		$settings = $this->settings();
		$settings->throwOnGet = true;
		$tool = $this->tool($settings, new RunConfiguredAgentTestExecutionService(), ['agent_id' => 'worker']);
		$this->assertStringContainsString('configured agent "worker"', $tool->contribute(new AgentContext())[0]->getContent());
	}

	public function testMemoryPriorityIsConfigurable(): void {
		$tool = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService(), ['memory_priority' => '18']);
		$this->assertSame(18, $tool->getPriority());
	}

	public function testInvalidMemoryPriorityUsesDefault(): void {
		$tool = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService(), ['memory_priority' => 'not a number']);
		$this->assertSame(40, $tool->getPriority());
	}

	public function testUnknownFunctionThrowsBeforeExecution(): void {
		$tool = $this->tool($this->settings(), new RunConfiguredAgentTestExecutionService());
		$this->expectException(\InvalidArgumentException::class);
		$tool->callTool('other', [], new AgentContext());
	}

	public function testMissingAgentIdReturnsStableErrorCode(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$tool = $this->tool($this->settings(), $execution);
		$result = $tool->callTool('run_configured_agent', [], new AgentContext());
		$this->assertFalse($result['ok']);
		$this->assertSame('missing_agent_id', $result['error_code']);
		$this->assertSame([], $execution->requests);
	}

	public function testUnknownConfiguredAgentDoesNotInvokeExecutor(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$result = $this->call(new RunConfiguredAgentTestSettingsStore(), $execution);
		$this->assertSame('execution_failed', $result['error_code']);
		$this->assertStringContainsString('Configured agent not found', $result['error']);
		$this->assertSame([], $execution->requests);
	}

	public function testMissingAllPromptSourcesReturnsErrorWithoutExecution(): void {
		$settings = new RunConfiguredAgentTestSettingsStore();
		$settings->set('agent', 'worker', ['label' => 'worker']);
		$execution = new RunConfiguredAgentTestExecutionService();
		$result = $this->call($settings, $execution);
		$this->assertSame('missing_prompt', $result['error_code']);
		$this->assertSame([], $execution->requests);
	}

	public function testExplicitUserPromptOverridesAgentPromptWhenAllowed(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$result = $this->call($this->settings(), $execution, [], ['user_prompt' => 'Custom task']);
		$this->assertTrue($result['ok']);
		$this->assertSame('Custom task', $execution->requests[0]->getInputs()['prompt']);
		$this->assertSame('Custom task', $result['prompt']);
	}

	public function testPromptOverrideCanBeForbidden(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$this->call($this->settings(), $execution, ['allow_prompt_override' => false], ['user_prompt' => 'Untrusted task']);
		$this->assertSame('Default task', $execution->requests[0]->getInputs()['prompt']);
	}

	public function testConfiguredDefaultPromptTakesPrecedenceOverAgentPrompt(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$this->call($this->settings(), $execution, ['default_user_prompt' => 'Configured task']);
		$this->assertSame('Configured task', $execution->requests[0]->getInputs()['prompt']);
	}

	public function testEmptyArgumentPromptFallsBackToAgentPrompt(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$this->call($this->settings(), $execution, [], ['user_prompt' => "   \n "]);
		$this->assertSame('Default task', $execution->requests[0]->getInputs()['prompt']);
	}

	public function testPromptNormalizesWindowsNewlines(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$this->call($this->settings(), $execution, [], ['user_prompt' => "one\r\ntwo\rthree"]);
		$this->assertSame("one\ntwo\nthree", $execution->requests[0]->getInputs()['prompt']);
	}

	public function testAgentRequestCarriesOnlyDeclaredCoreInputs(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$this->call($this->settings(), $execution);
		$this->assertSame(['system' => 'System policy', 'prompt' => 'Default task', 'mode' => 'sub_agent'], $execution->requests[0]->getInputs());
	}

	public function testAgentRequestUsesIsolatedSubAgentChannel(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$this->call($this->settings(), $execution);
		$context = $execution->requests[0]->getContext();
		$this->assertSame('sub-agent:subagent_fixture:worker', $context['conversation_channel_id']);
		$this->assertSame('worker', $context['sub_agent_id']);
		$this->assertSame('subagent_fixture', $context['sub_agent_tool_id']);
	}

	public function testAgentRequestPreservesSettingsAsExplicitConfiguration(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$this->call($this->settings(), $execution);
		$request = $execution->requests[0];
		$this->assertSame('Worker Agent', $request->getAgentConfiguration()['label']);
		$this->assertSame('Worker Agent', $request->getContext()['sub_agent_config']['label']);
	}

	public function testCallContextKeepsScalarValuesAndNestedArrays(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$this->call($this->settings(), $execution, [], ['context' => ['ticket' => 77, 'enabled' => false, 'nested' => ['a' => 'b'], 'nullable' => null]]);
		$this->assertSame(['ticket' => 77, 'enabled' => false, 'nested' => ['a' => 'b'], 'nullable' => null], $execution->requests[0]->getContext()['sub_agent_call_context']);
	}

	public function testCallContextDoesNotIncludeArbitraryObjects(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$this->call($this->settings(), $execution, [], ['context' => ['safe' => 1, 'object' => new \stdClass()]]);
		$this->assertSame(['safe' => 1], $execution->requests[0]->getContext()['sub_agent_call_context']);
	}

	public function testCallContextMustBeAnArray(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$this->call($this->settings(), $execution, [], ['context' => 'untrusted scalar']);
		$this->assertSame([], $execution->requests[0]->getContext()['sub_agent_call_context']);
	}

	public function testCallContextStopsRecursiveDataAfterConfiguredDepth(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$this->call($this->settings(), $execution, [], ['context' => ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => ['g' => 1]]]]]]]]);
		$this->assertSame([], $execution->requests[0]->getContext()['sub_agent_call_context']['a']['b']['c']['d']['e']['f']);
	}

	public function testSuccessfulResponseIncludesMessageAndAgentIdentity(): void {
		$result = $this->call($this->settings(), new RunConfiguredAgentTestExecutionService());
		$this->assertTrue($result['ok']);
		$this->assertSame('worker', $result['agent_id']);
		$this->assertSame('Worker Agent', $result['agent_label']);
		$this->assertSame('Completed', $result['message']);
		$this->assertSame('msg-1', $result['message_id']);
	}

	public function testRawOutputIsHiddenByDefault(): void {
		$result = $this->call($this->settings(), new RunConfiguredAgentTestExecutionService());
		$this->assertArrayNotHasKey('raw_output', $result);
	}

	public function testRawOutputCanBeExplicitlyEnabled(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$result = $this->call($this->settings(), $execution, ['include_raw_output' => 'yes']);
		$this->assertSame($execution->output, $result['raw_output']);
	}

	public function testBooleanMessageContentIsNormalized(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$execution->output = ['assistant' => ['message' => ['id' => 'result', 'content' => true]]];
		$this->assertSame('true', $this->call($this->settings(), $execution)['message']);
	}

	public function testNumericMessageContentIsNormalized(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$execution->output = ['assistant' => ['message' => ['id' => 'result', 'content' => 17.5]]];
		$this->assertSame('17.5', $this->call($this->settings(), $execution)['message']);
	}

	public function testArrayMessageContentIsSerializedAsJson(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$execution->output = ['assistant' => ['message' => ['id' => 'result', 'content' => ['key' => 'value']]]];
		$this->assertSame('{"key":"value"}', $this->call($this->settings(), $execution)['message']);
	}

	public function testNullMessageContentBecomesEmptyString(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$execution->output = ['assistant' => ['message' => ['id' => 'result', 'content' => null]]];
		$this->assertSame('', $this->call($this->settings(), $execution)['message']);
	}

	public function testMissingMessageIdIsGenerated(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$execution->output = ['assistant' => ['message' => ['content' => 'Done']]];
		$this->assertStringContainsString('msg_', $this->call($this->settings(), $execution)['message_id']);
	}

	public function testSubAgentFlowErrorIsReturnedAsStructuredFailure(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$execution->output = ['assistant' => ['error' => 'Invalid stage']];
		$result = $this->call($this->settings(), $execution);
		$this->assertFalse($result['ok']);
		$this->assertSame('flow_output_missing_message', $result['error_code']);
		$this->assertSame('Invalid stage', $result['error']);
	}

	public function testEmptyFlowOutputExplainsMissingTerminalNodes(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$execution->output = [];
		$result = $this->call($this->settings(), $execution);
		$this->assertSame('flow_output_missing_message', $result['error_code']);
		$this->assertStringContainsString('No terminal node output', $result['error']);
		$this->assertSame([], $result['terminal_nodes']);
	}

	public function testOtherTerminalNodesAreNamedWhenAssistantMessageMissing(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$execution->output = ['other_node' => ['status' => 'done']];
		$result = $this->call($this->settings(), $execution);
		$this->assertStringContainsString('other_node', $result['error']);
		$this->assertSame(['other_node'], $result['terminal_nodes']);
	}

	public function testFlowFailureMayIncludeRawOutputWhenOptedIn(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$execution->output = ['assistant' => ['error' => 'failed']];
		$result = $this->call($this->settings(), $execution, ['include_raw_output' => true]);
		$this->assertSame($execution->output, $result['raw_output']);
	}

	public function testExecutorFailureIsReportedWithoutCrashingCaller(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$execution->throwOnExecute = true;
		$result = $this->call($this->settings(), $execution);
		$this->assertFalse($result['ok']);
		$this->assertSame('execution_failed', $result['error_code']);
		$this->assertStringContainsString('Execution unavailable', $result['error']);
	}

	public function testAgentSettingsFailureIsReportedWithoutCallingExecutor(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$settings = $this->settings();
		$settings->throwOnGet = true;
		$result = $this->call($settings, $execution);
		$this->assertSame('execution_failed', $result['error_code']);
		$this->assertSame([], $execution->requests);
	}

	public function testLongPromptIsTruncatedOnlyInReturnedSummary(): void {
		$execution = new RunConfiguredAgentTestExecutionService();
		$longPrompt = str_repeat('A', 12100);
		$result = $this->call($this->settings(), $execution, [], ['user_prompt' => $longPrompt]);
		$this->assertSame($longPrompt, $execution->requests[0]->getInputs()['prompt']);
		$this->assertSame(12000, strlen($result['prompt']));
		$this->assertSame('...', substr($result['prompt'], -3));
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Test\Resource\AgentTool\Agent;

use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentExecutionResult;
use Base3\Settings\Api\ISettingsStore;
use Base3\Api\IClassMap;
use MissionBay\Service\AgentConfigurationService;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\AgentTool\Agent\RunAvailableAgentTool;
use PHPUnit\Framework\TestCase;

final class RunAvailableAgentToolBehaviorTest extends TestCase {

	public function testToolDefinitionsExposeListDescribeAndRun(): void {
		$definitions = $this->tool()->getToolDefinitions();
		$this->assertSame(['agent_catalog_list', 'agent_catalog_describe', 'agent_catalog_run'], array_column(array_column($definitions, 'function'), 'name'));
		$this->assertSame(['agent_id'], $definitions[1]['function']['parameters']['required']);
		$this->assertSame(['agent_id'], $definitions[2]['function']['parameters']['required']);
	}

	public function testUnsupportedFunctionIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->call('untrusted_tool', []);
	}

	public function testListEmptyGroupDoesNotInvokeExecution(): void {
		$result = $this->call('agent_catalog_list', []);
		$this->assertTrue($result['ok']);
		$this->assertSame(0, $result['total']);
		$this->assertSame([], $result['agents']);
		$this->assertFalse($result['has_more']);
	}

	public function testDisabledAgentsAreExcludedFromListing(): void {
		$group = ['enabled' => ['label' => 'Active'], 'disabled' => ['label' => 'Hidden', 'enabled' => false]];
		$result = $this->call('agent_catalog_list', [], $group);
		$this->assertSame(1, $result['total']);
		$this->assertSame(['enabled'], array_column($result['agents'], 'agent_id'));
	}

	public function testDefaultEnabledAgentWithoutEnabledFieldIsIncluded(): void {
		$result = $this->call('agent_catalog_list', [], ['ready' => ['label' => 'Ready']]);
		$this->assertSame(['ready'], array_column($result['agents'], 'agent_id'));
	}

	public function testInvalidSettingsRowsAreExcluded(): void {
		$result = $this->call('agent_catalog_list', [], ['empty' => null, 'false' => false, 'good' => ['label' => 'Good']]);
		$this->assertSame(['good'], array_column($result['agents'], 'agent_id'));
	}

	public function testLabelsSortCaseInsensitively(): void {
		$group = ['third' => ['label' => 'zulu'], 'first' => ['label' => 'Alpha'], 'second' => ['label' => 'beta']];
		$this->assertSame(['first', 'second', 'third'], array_column($this->call('agent_catalog_list', [], $group)['agents'], 'agent_id'));
	}

	public function testEqualLabelsSortByAgentId(): void {
		$group = ['z-agent' => ['label' => 'Same'], 'a-agent' => ['label' => 'same']];
		$this->assertSame(['a-agent', 'z-agent'], array_column($this->call('agent_catalog_list', [], $group)['agents'], 'agent_id'));
	}

	public function testListingSupportsPaginationAndNextPage(): void {
		$group = ['a' => ['label' => 'Alpha'], 'b' => ['label' => 'Beta'], 'c' => ['label' => 'Gamma']];
		$first = $this->call('agent_catalog_list', ['page_size' => 2], $group);
		$this->assertSame(2, $first['total_pages']);
		$this->assertSame(['a', 'b'], array_column($first['agents'], 'agent_id'));
		$this->assertTrue($first['has_more']);
		$this->assertSame(2, $first['next_page']);
		$last = $this->call('agent_catalog_list', ['page_size' => 2, 'page' => 2], $group);
		$this->assertSame(['c'], array_column($last['agents'], 'agent_id'));
		$this->assertFalse($last['has_more']);
		$this->assertNull($last['next_page']);
	}

	public function testListingRejectsNegativePageByClamping(): void {
		$result = $this->call('agent_catalog_list', ['page' => -99, 'page_size' => -9], ['a' => ['label' => 'Alpha']]);
		$this->assertSame(1, $result['page']);
		$this->assertSame(1, $result['page_size']);
	}

	public function testPageSizeRespectsConfiguredMaximum(): void {
		$tool = $this->tool();
		$tool->setConfig(['list_max_page_size' => 3, 'list_default_page_size' => 10]);
		$result = $tool->callTool('agent_catalog_list', ['page_size' => 500], new AgentContext());
		$this->assertSame(3, $result['page_size']);
		$this->assertSame(3, $tool->callTool('agent_catalog_list', [], new AgentContext())['page_size']);
	}

	public function testSearchingMatchesLabelAndIdWithoutCaseSensitivity(): void {
		$group = ['math-agent' => ['label' => 'Arithmetic'], 'writer' => ['label' => 'Narrative']];
		$this->assertSame(['math-agent'], array_column($this->call('agent_catalog_list', ['search' => 'MATH'], $group)['agents'], 'agent_id'));
		$this->assertSame(['writer'], array_column($this->call('agent_catalog_list', ['search' => 'NARRATIVE'], $group)['agents'], 'agent_id'));
	}

	public function testSearchingMatchesPromptChatmodelAndPolicy(): void {
		$group = ['agent-a' => ['label' => 'Generic', 'user_prompt' => 'Read stock report', 'chatmodel' => 'model-one', 'policy' => ['policy' => 'strict']], 'agent-b' => ['label' => 'Other']];
		foreach (['stock', 'model-one', 'strict'] as $query) {
			$this->assertSame(['agent-a'], array_column($this->call('agent_catalog_list', ['search' => $query], $group)['agents'], 'agent_id'));
		}
	}

	public function testListingDefaultsToCompactWithoutPromptExposure(): void {
		$group = ['agent-a' => ['label' => 'Agent A', 'user_prompt' => 'Sensitive default prompt']];
		$result = $this->call('agent_catalog_list', [], $group);
		$this->assertArrayNotHasKey('default_prompt_preview', $result['agents'][0]);
		$this->assertArrayNotHasKey('policy', $result['agents'][0]);
	}

	public function testPreviewIsOnlyProvidedWhenRequested(): void {
		$group = ['agent-a' => ['label' => 'Agent A', 'user_prompt' => 'Preview me', 'policy' => ['policy' => 'fixed']]];
		$result = $this->call('agent_catalog_list', ['detail' => 'preview'], $group);
		$this->assertSame('Preview me', $result['agents'][0]['default_prompt_preview']);
		$this->assertSame('fixed', $result['agents'][0]['policy']);
	}

	public function testUnknownDetailModeStaysCompact(): void {
		$result = $this->call('agent_catalog_list', ['detail' => 'raw'], ['a' => ['label' => 'A']]);
		$this->assertSame('compact', $result['detail']);
		$this->assertArrayNotHasKey('default_prompt_preview', $result['agents'][0]);
	}

	public function testCustomPrefixChangesAllFunctionNames(): void {
		$tool = $this->tool();
		$tool->setConfig(['tool_prefix' => '  Sub Agents:2026 ']);
		$this->assertSame(['Sub_Agents_2026_list', 'Sub_Agents_2026_describe', 'Sub_Agents_2026_run'], array_column(array_column($tool->getToolDefinitions(), 'function'), 'name'));
	}

	public function testInvalidPrefixUsesDefault(): void {
		$tool = $this->tool();
		$tool->setConfig(['tool_prefix' => '**!!!**']);
		$this->assertSame('agent_catalog_list', $tool->getToolDefinitions()[0]['function']['name']);
	}

	public function testCustomSettingsGroupIsUsedForListing(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturnCallback(static fn(string $group): array => $group === 'customagents' ? ['a' => ['label' => 'Only custom']] : []);
		$tool = $this->tool([], $settings);
		$tool->setConfig(['settings_group' => 'Custom Agents']);
		$result = $tool->callTool('agent_catalog_list', [], new AgentContext());
		$this->assertSame('customagents', $result['settings_group']);
		$this->assertSame(['a'], array_column($result['agents'], 'agent_id'));
	}

	public function testListHidesSettingsStorageFailure(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willThrowException(new \RuntimeException('storage unavailable'));
		$this->assertSame([], $this->tool([], $settings)->callTool('agent_catalog_list', [], new AgentContext())['agents']);
	}

	public function testDescribeRequiresAgentId(): void {
		$this->assertSame('missing_agent_id', $this->call('agent_catalog_describe', [])['error_code']);
	}

	public function testDescribeRefusesUnknownAgent(): void {
		$this->assertSame('agent_unavailable', $this->call('agent_catalog_describe', ['agent_id' => 'unknown'])['error_code']);
	}

	public function testDescribeRefusesDisabledAgent(): void {
		$group = ['hidden' => ['label' => 'Hidden', 'enabled' => 'false']];
		$result = $this->call('agent_catalog_describe', ['agent_id' => 'hidden'], $group);
		$this->assertSame('agent_unavailable', $result['error_code']);
		$this->assertStringContainsString('disabled', $result['error']);
	}

	public function testDescribeDoesNotIncludeDefaultPromptWhenDisabled(): void {
		$result = $this->call('agent_catalog_describe', ['agent_id' => 'alpha', 'include_default_prompt' => false], ['alpha' => ['label' => 'Alpha', 'user_prompt' => 'Secret prompt']]);
		$this->assertTrue($result['ok']);
		$this->assertArrayNotHasKey('default_user_prompt', $result['agent']);
	}

	public function testDescribeIncludesDefaultPromptWhenRequested(): void {
		$result = $this->call('agent_catalog_describe', ['agent_id' => 'alpha'], ['alpha' => ['label' => 'Alpha', 'user_prompt' => 'Configured prompt']]);
		$this->assertSame('Configured prompt', $result['agent']['default_user_prompt']);
		$this->assertSame('alpha', $result['agent']['agent_id']);
	}

	public function testRunRequiresAgentId(): void {
		$this->assertSame('missing_agent_id', $this->call('agent_catalog_run', [])['error_code']);
	}

	public function testRunRefusesUnknownAgent(): void {
		$this->assertSame('execution_failed', $this->call('agent_catalog_run', ['agent_id' => 'unknown'])['error_code']);
	}

	public function testRunRefusesDisabledAgent(): void {
		$result = $this->call('agent_catalog_run', ['agent_id' => 'hidden'], ['hidden' => ['enabled' => false, 'user_prompt' => 'Run']]);
		$this->assertSame('execution_failed', $result['error_code']);
		$this->assertStringContainsString('disabled', $result['error']);
	}

	public function testRunWithoutArgumentOrConfiguredPromptIsRejected(): void {
		$this->assertSame('missing_prompt', $this->call('agent_catalog_run', ['agent_id' => 'empty'], ['empty' => ['label' => 'No prompt']])['error_code']);
	}

	public function testSuccessfulRunUsesConfiguredPromptAndOmitsRawOutput(): void {
		$execution = $this->executionResult(['assistant' => ['message' => ['id' => 'msg1', 'content' => 'The answer']]]);
		$tool = $this->tool(['alpha' => ['label' => 'Alpha', 'user_prompt' => 'Configured work']], null, $execution);
		$result = $tool->callTool('agent_catalog_run', ['agent_id' => 'alpha'], new AgentContext());
		$this->assertTrue($result['ok']);
		$this->assertSame('configured_agent', $result['prompt_source']);
		$this->assertSame('Configured work', $result['prompt']);
		$this->assertSame('The answer', $result['message']);
		$this->assertSame('msg1', $result['message_id']);
		$this->assertArrayNotHasKey('raw_output', $result);
	}

	public function testPromptOverrideIsPassedToAgentExecution(): void {
		$captured = null;
		$execution = $this->createStub(IAgentExecutionService::class);
		$execution->method('execute')->willReturnCallback(static function(AgentExecutionRequest $request) use (&$captured): AgentExecutionResult {
			$captured = $request;
			return new AgentExecutionResult(['assistant' => ['message' => ['content' => 'Done']]]);
		});
		$tool = $this->tool(['alpha' => ['user_prompt' => 'Configured', 'system_prompt' => 'Rules']], null, $execution);
		$result = $tool->callTool('agent_catalog_run', ['agent_id' => 'alpha', 'user_prompt' => 'Override', 'context' => ['allowed' => 5]], new AgentContext());
		$this->assertTrue($result['ok']);
		$this->assertSame('argument', $result['prompt_source']);
		$this->assertSame('Override', $captured->getInputs()['prompt']);
		$this->assertSame('Rules', $captured->getInputs()['system']);
		$this->assertSame('sub_agent_catalog', $captured->getInputs()['mode']);
		$this->assertSame(['allowed' => 5], $captured->getContext()['sub_agent_call_context']);
	}

	public function testPromptOverrideCanBeDisabled(): void {
		$execution = $this->executionResult(['assistant' => ['message' => ['content' => 'Done']]]);
		$tool = $this->tool(['alpha' => ['user_prompt' => 'Trusted prompt']], null, $execution);
		$tool->setConfig(['allow_prompt_override' => false]);
		$result = $tool->callTool('agent_catalog_run', ['agent_id' => 'alpha', 'user_prompt' => 'Untrusted prompt'], new AgentContext());
		$this->assertSame('Trusted prompt', $result['prompt']);
		$this->assertSame('configured_agent', $result['prompt_source']);
	}

	public function testContextDropsObjectsAndPreservesNestedScalars(): void {
		$captured = null;
		$execution = $this->createStub(IAgentExecutionService::class);
		$execution->method('execute')->willReturnCallback(static function(AgentExecutionRequest $request) use (&$captured): AgentExecutionResult {
			$captured = $request;
			return new AgentExecutionResult(['assistant' => ['message' => ['content' => 'Done']]]);
		});
		$tool = $this->tool(['alpha' => ['user_prompt' => 'Default']], null, $execution);
		$tool->callTool('agent_catalog_run', ['agent_id' => 'alpha', 'context' => ['x' => new \stdClass(), 'nested' => ['valid' => 2, 'unsafe' => new \stdClass()], 'ok' => null]], new AgentContext());
		$this->assertSame(['nested' => ['valid' => 2], 'ok' => null], $captured->getContext()['sub_agent_call_context']);
	}

	public function testSuccessfulRunWithRawOutputEnabledIncludesTerminalNodes(): void {
		$output = ['assistant' => ['message' => ['content' => ['answer' => 'done']]], 'trace' => ['ok' => true]];
		$tool = $this->tool(['alpha' => ['user_prompt' => 'Default']], null, $this->executionResult($output));
		$tool->setConfig(['include_raw_output' => true]);
		$result = $tool->callTool('agent_catalog_run', ['agent_id' => 'alpha'], new AgentContext());
		$this->assertSame($output, $result['raw_output']);
		$this->assertSame('{"answer":"done"}', $result['message']);
	}

	public function testMissingAssistantMessageReturnsStructuredFailure(): void {
		$tool = $this->tool(['alpha' => ['user_prompt' => 'Default']], null, $this->executionResult(['other' => ['content' => 'x']]));
		$result = $tool->callTool('agent_catalog_run', ['agent_id' => 'alpha'], new AgentContext());
		$this->assertSame('flow_output_missing_message', $result['error_code']);
		$this->assertSame(['other'], $result['terminal_nodes']);
	}

	public function testAssistantFlowErrorIsReported(): void {
		$tool = $this->tool(['alpha' => ['user_prompt' => 'Default']], null, $this->executionResult(['assistant' => ['error' => 'Tool failed']]));
		$result = $tool->callTool('agent_catalog_run', ['agent_id' => 'alpha'], new AgentContext());
		$this->assertSame('flow_output_missing_message', $result['error_code']);
		$this->assertSame('Tool failed', $result['error']);
	}

	public function testExecutionExceptionIsReturnedAsFailure(): void {
		$execution = $this->createStub(IAgentExecutionService::class);
		$execution->method('execute')->willThrowException(new \RuntimeException('sub-agent unavailable'));
		$result = $this->tool(['alpha' => ['user_prompt' => 'Default']], null, $execution)->callTool('agent_catalog_run', ['agent_id' => 'alpha'], new AgentContext());
		$this->assertSame('execution_failed', $result['error_code']);
		$this->assertStringContainsString('sub-agent unavailable', $result['error']);
	}

	public function testContextContributionCanBeDisabled(): void {
		$tool = $this->tool(['alpha' => ['label' => 'Alpha']]);
		$tool->setConfig(['memory_enabled' => false]);
		$this->assertSame([], $tool->contribute(new AgentContext()));
	}

	public function testContextContributionMentionsUnavailableCatalog(): void {
		$blocks = $this->tool()->contribute(new AgentContext());
		$this->assertCount(1, $blocks);
		$this->assertStringContainsString('No enabled configured agents', $blocks[0]->getContent());
		$this->assertSame('available-agent-catalog', $blocks[0]->getId());
	}

	public function testContextContributionInlinesSmallCatalog(): void {
		$blocks = $this->tool(['alpha' => ['label' => 'Alpha', 'user_prompt' => 'Collect numbers']])->contribute(new AgentContext());
		$this->assertStringContainsString('Alpha', $blocks[0]->getContent());
		$this->assertStringContainsString('Collect numbers', $blocks[0]->getContent());
	}

	public function testContextContributionHidesInlineAgentsAboveConfiguredLimit(): void {
		$tool = $this->tool(['alpha' => ['label' => 'Private example'], 'beta' => ['label' => 'Other example']]);
		$tool->setConfig(['memory_agent_limit' => 1]);
		$blocks = $tool->contribute(new AgentContext());
		$this->assertStringContainsString('There are 2 enabled agents', $blocks[0]->getContent());
		$this->assertStringNotContainsString('Private example', $blocks[0]->getContent());
	}

	public function testMemoryPriorityIsConfigurableWithinBounds(): void {
		$tool = $this->tool();
		$tool->setConfig(['memory_priority' => 1200]);
		$this->assertSame(1000, $tool->getPriority());
		$tool->setConfig(['memory_priority' => -50]);
		$this->assertSame(0, $tool->getPriority());
	}

	private function call(string $name, array $arguments, array $group = []): array {
		return $this->tool($group)->callTool($name, $arguments, new AgentContext());
	}

	private function tool(array $group = [], ?ISettingsStore $settings = null, ?IAgentExecutionService $execution = null): RunAvailableAgentTool {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(static fn(mixed $value): mixed => $value);
		if ($settings === null) {
			$settings = $this->createStub(ISettingsStore::class);
			$settings->method('getGroup')->willReturn($group);
			$settings->method('get')->willReturnCallback(static fn(string $settingsGroup, string $id, array $default = []): array => $group[$id] ?? $default);
		}
		$execution ??= $this->createStub(IAgentExecutionService::class);
		return new RunAvailableAgentTool($resolver, new AgentConfigurationService($settings, $this->createStub(IClassMap::class)), $execution, 'catalog-test');
	}

	private function executionResult(array $output): IAgentExecutionService {
		$execution = $this->createStub(IAgentExecutionService::class);
		$execution->method('execute')->willReturn(new AgentExecutionResult($output));
		return $execution;
	}
}

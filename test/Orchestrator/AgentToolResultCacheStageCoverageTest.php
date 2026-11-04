<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentToolCacheConfig;
use AssistantFoundation\Dto\AgentToolCacheEntry;
use AssistantFoundation\Dto\AgentToolCacheRule;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Cache\AgentToolCacheKeyBuilder;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\Stage\AgentResultVerificationStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as K;
use MissionBay\Orchestrator\Stage\AgentToolResultCacheStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentToolResultCacheStage::class)]
final class AgentToolResultCacheStageCoverageTest extends TestCase {

	private function stage(AgentToolCacheCoverageRecordingCache $cache, string $checkpoint = AgentToolResultCacheStage::CHECKPOINT_LOOKUP): AgentToolResultCacheStage {
		return new AgentToolResultCacheStage($cache, new RecordingEventManager(), new AgentToolCacheKeyBuilder(), checkpoint: $checkpoint);
	}

	private function config(string $scope = AgentToolCacheConfig::SCOPE_GLOBAL, int $maxBytes = 262144): AgentToolCacheConfig {
		return new AgentToolCacheConfig(true, $scope, $scope === AgentToolCacheConfig::SCOPE_CUSTOM ? 'tenant-a' : '', 'mission', $maxBytes, [new AgentToolCacheRule('lookup', 45)]);
	}

	private function lookupContext(?AgentToolCacheConfig $config = null): AgentContext {
		return new AgentContext(vars: [
			K::TOOL_CACHE_CONFIG => $config ?? $this->config(),
			K::PHASE => K::PHASE_TOOLS,
			K::ITERATION => 2,
			K::PENDING_TOOL_CALLS => [new AiToolCall('c1', 'lookup', ['query' => 'alpha'])],
			K::TOOLS => [new LookupTool()],
			K::TRACE => ['turn_id' => 'turn-1', 'chatbot_key' => 'chat-1', 'config_group' => 'agent', 'config_name' => 'one']
		]);
	}

	private function storeContext(array $results, array $plans, ?AgentToolCacheConfig $config = null): AgentContext {
		return new AgentContext(vars: [
			K::TOOL_CACHE_CONFIG => $config ?? $this->config(),
			K::PHASE => K::PHASE_AFTER_TOOLS,
			K::ITERATION => 2,
			K::TOOL_RESULTS => $results,
			K::TOOL_CACHE_PLANS => $plans,
			K::RESULT_VERIFICATIONS => [$this->verification(2, true)]
		]);
	}

	private function verification(int $iteration, bool $verified, string $verifier = AgentResultVerificationStage::VERIFIER): AgentResultVerification {
		return new AgentResultVerification($iteration, $verifier, $verified ? AgentResultVerification::VERDICT_VERIFIED : AgentResultVerification::VERDICT_FAILED, 'checked');
	}

	private function plan(): array {
		return ['c1' => ['cache_key' => 'cache-a', 'arguments_hash' => 'hash', 'tool_identity' => 'lookuptool', 'tool' => 'lookup', 'scope' => 'global', 'ttl_seconds' => 45, 'rule' => ['tool' => 'lookup']]];
	}

	private function entryFromPlan(array $plan, mixed $output = ['found' => true]): AgentToolCacheEntry {
		return new AgentToolCacheEntry($plan['tool_identity'], $plan['tool'], $plan['arguments_hash'], $plan['scope'], $output, '2026-01-01T10:00:00+00:00', '2027-01-01T10:00:00+00:00');
	}

	public function testMetadataIdentifiesLookupCheckpoint(): void {
		$stage = $this->stage(new AgentToolCacheCoverageRecordingCache());
		$this->assertSame('agenttoolresultcachestage', $stage::getName());
		$this->assertSame('tool-cache-lookup', $stage->id());
		$this->assertSame('tool-cache-lookup', $stage->name());
		$this->assertSame('none', $stage->getAiUsage());
		$this->assertStringContainsString('Reuses', $stage->getDescription());
	}

	public function testStoreDescriptionAndCustomIdentity(): void {
		$stage = new AgentToolResultCacheStage(new AgentToolCacheCoverageRecordingCache(), new RecordingEventManager(), new AgentToolCacheKeyBuilder(), 'cache-store-id', 'Cache store', AgentToolResultCacheStage::CHECKPOINT_STORE);
		$this->assertSame('cache-store-id', $stage->id());
		$this->assertSame('Cache store', $stage->name());
		$this->assertStringContainsString('Stores', $stage->getDescription());
	}

	public function testUnsupportedCheckpointIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		new AgentToolResultCacheStage(new AgentToolCacheCoverageRecordingCache(), new RecordingEventManager(), new AgentToolCacheKeyBuilder(), checkpoint: 'cache-bypass');
	}

	public function testLookupSupportsPreparedContext(): void {
		$this->assertTrue($this->stage(new AgentToolCacheCoverageRecordingCache())->supports($this->lookupContext()));
	}

	public function testLookupDoesNotSupportMissingConfig(): void {
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache())->supports(new AgentContext()));
	}

	public function testLookupDoesNotSupportDisabledConfiguration(): void {
		$context = $this->lookupContext(AgentToolCacheConfig::disabled());
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache())->supports($context));
	}

	public function testLookupDoesNotSupportMissingBackend(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$cache->available = false;
		$this->assertFalse($this->stage($cache)->supports($this->lookupContext()));
	}

	public function testLookupDoesNotSupportCompletedRun(): void {
		$context = $this->lookupContext();
		$context->setVar(K::COMPLETED, true);
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache())->supports($context));
	}

	public function testLookupDoesNotSupportFailure(): void {
		$context = $this->lookupContext();
		$context->setVar(K::FAILURE_CODE, 'tool_error');
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache())->supports($context));
	}

	public function testLookupDoesNotSupportWrongPhase(): void {
		$context = $this->lookupContext();
		$context->setVar(K::PHASE, K::PHASE_MODEL);
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache())->supports($context));
	}

	public function testLookupDoesNotSupportEmptyCalls(): void {
		$context = $this->lookupContext();
		$context->setVar(K::PENDING_TOOL_CALLS, []);
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache())->supports($context));
	}

	public function testLookupDoesNotSupportNonArrayCalls(): void {
		$context = $this->lookupContext();
		$context->setVar(K::PENDING_TOOL_CALLS, 'invalid');
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache())->supports($context));
	}

	public function testStoreSupportsVerifiedCurrentIteration(): void {
		$context = $this->storeContext([AgentToolResult::success('c1', 'lookup', [], [])], $this->plan());
		$this->assertTrue($this->stage(new AgentToolCacheCoverageRecordingCache(), AgentToolResultCacheStage::CHECKPOINT_STORE)->supports($context));
	}

	public function testStoreDoesNotSupportMissingResult(): void {
		$context = $this->storeContext([], $this->plan());
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache(), AgentToolResultCacheStage::CHECKPOINT_STORE)->supports($context));
	}

	public function testStoreDoesNotSupportWrongPhase(): void {
		$context = $this->storeContext([AgentToolResult::success('c1', 'lookup', [], [])], $this->plan());
		$context->setVar(K::PHASE, K::PHASE_TOOLS);
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache(), AgentToolResultCacheStage::CHECKPOINT_STORE)->supports($context));
	}

	public function testStoreRejectsNonArrayVerifications(): void {
		$context = $this->storeContext([AgentToolResult::success('c1', 'lookup', [], [])], $this->plan());
		$context->setVar(K::RESULT_VERIFICATIONS, null);
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache(), AgentToolResultCacheStage::CHECKPOINT_STORE)->supports($context));
	}

	public function testStoreRejectsOtherVerifier(): void {
		$context = $this->storeContext([AgentToolResult::success('c1', 'lookup', [], [])], $this->plan());
		$context->setVar(K::RESULT_VERIFICATIONS, [$this->verification(2, true, 'semantic')]);
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache(), AgentToolResultCacheStage::CHECKPOINT_STORE)->supports($context));
	}

	public function testStoreRejectsPreviousIteration(): void {
		$context = $this->storeContext([AgentToolResult::success('c1', 'lookup', [], [])], $this->plan());
		$context->setVar(K::RESULT_VERIFICATIONS, [$this->verification(1, true)]);
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache(), AgentToolResultCacheStage::CHECKPOINT_STORE)->supports($context));
	}

	public function testStoreRejectsFailedVerification(): void {
		$context = $this->storeContext([AgentToolResult::success('c1', 'lookup', [], [])], $this->plan());
		$context->setVar(K::RESULT_VERIFICATIONS, [$this->verification(2, false)]);
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache(), AgentToolResultCacheStage::CHECKPOINT_STORE)->supports($context));
	}

	public function testStoreUsesLatestCurrentStructuralVerification(): void {
		$context = $this->storeContext([AgentToolResult::success('c1', 'lookup', [], [])], $this->plan());
		$context->setVar(K::RESULT_VERIFICATIONS, [$this->verification(2, true), $this->verification(2, false)]);
		$this->assertFalse($this->stage(new AgentToolCacheCoverageRecordingCache(), AgentToolResultCacheStage::CHECKPOINT_STORE)->supports($context));
	}

	public function testStoreSkipsOtherVerifierAfterCurrentStructuralVerification(): void {
		$context = $this->storeContext([AgentToolResult::success('c1', 'lookup', [], [])], $this->plan());
		$context->setVar(K::RESULT_VERIFICATIONS, [$this->verification(2, true), $this->verification(2, false, 'semantic')]);
		$this->assertTrue($this->stage(new AgentToolCacheCoverageRecordingCache(), AgentToolResultCacheStage::CHECKPOINT_STORE)->supports($context));
	}

	public function testLookupMissLeavesCallPendingAndRecordsPlan(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->stage($cache)->process($this->lookupContext());
		$this->assertSame(['hits' => 0, 'misses' => 1, 'bypassed' => 0, 'errors' => 0], $result->getMetadata()['tool_cache']);
		$this->assertCount(1, $result->getPatch()[K::PENDING_TOOL_CALLS]);
		$this->assertCount(1, $result->getPatch()[K::TOOL_CACHE_PLANS]);
		$this->assertSame(K::PHASE_TOOLS, $result->getPatch()[K::PHASE]);
	}

	public function testLookupMissIncrementsCallCounter(): void {
		$context = $this->lookupContext();
		$context->setVar(K::CALL_INDEX, 7);
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame(8, $result->getPatch()[K::CALL_INDEX]);
		$this->assertSame(8, $result->getPatch()[K::TOOL_CALL_INDEXES]['c1']);
	}

	public function testLookupSkipsUnknownTool(): void {
		$context = $this->lookupContext();
		$context->setVar(K::TOOLS, []);
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['bypassed']);
		$this->assertSame('tool_not_found', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]->toArray()['reason']);
	}

	public function testLookupSkipsNonmatchingRule(): void {
		$context = $this->lookupContext(new AgentToolCacheConfig(true, AgentToolCacheConfig::SCOPE_GLOBAL, '', 'mission', 128, [new AgentToolCacheRule('other', 10)]));
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['bypassed']);
		$this->assertSame('no_matching_rule', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]->toArray()['reason']);
	}

	public function testLookupSkipsUnstructuredValueWithoutAlteringIt(): void {
		$context = $this->lookupContext();
		$context->setVar(K::PENDING_TOOL_CALLS, ['raw-call']);
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame(['raw-call'], $result->getPatch()[K::PENDING_TOOL_CALLS]);
		$this->assertSame(0, $result->getPatch()[K::CALL_INDEX]);
	}

	public function testLookupBackendErrorFailsOpenWithoutInventingResult(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$cache->failGet = true;
		$result = $this->stage($cache)->process($this->lookupContext());
		$this->assertSame(1, $result->getMetadata()['tool_cache']['errors']);
		$this->assertCount(1, $result->getPatch()[K::PENDING_TOOL_CALLS]);
		$this->assertSame([], $result->getPatch()[K::TOOL_RESULTS]);
	}

	public function testLookupDeletesEntryWhoseIdentityDoesNotMatch(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$first = $this->stage($cache)->process($this->lookupContext());
		$plan = $first->getPatch()[K::TOOL_CACHE_PLANS]['c1'];
		$cache->entries[$plan['cache_key']] = new AgentToolCacheEntry('other-tool', $plan['tool'], $plan['arguments_hash'], $plan['scope'], ['bad' => true], '', '');
		$result = $this->stage($cache)->process($this->lookupContext());
		$this->assertSame(1, $result->getMetadata()['tool_cache']['misses']);
		$this->assertSame([$plan['cache_key']], $cache->deletedKeys);
	}

	public function testLookupDeletesEntryWhoseArgumentsHashDoesNotMatch(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$first = $this->stage($cache)->process($this->lookupContext());
		$plan = $first->getPatch()[K::TOOL_CACHE_PLANS]['c1'];
		$cache->entries[$plan['cache_key']] = new AgentToolCacheEntry($plan['tool_identity'], $plan['tool'], 'wrong', $plan['scope'], ['bad' => true], '', '');
		$result = $this->stage($cache)->process($this->lookupContext());
		$this->assertSame(1, $result->getMetadata()['tool_cache']['misses']);
		$this->assertSame([$plan['cache_key']], $cache->deletedKeys);
	}

	public function testLookupUsesMatchingEntryAndStopsExecution(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plan = $this->stage($cache)->process($this->lookupContext())->getPatch()[K::TOOL_CACHE_PLANS]['c1'];
		$cache->entries[$plan['cache_key']] = $this->entryFromPlan($plan, ['found' => 'cached']);
		$result = $this->stage($cache)->process($this->lookupContext());
		$this->assertSame(1, $result->getMetadata()['tool_cache']['hits']);
		$this->assertSame([], $result->getPatch()[K::PENDING_TOOL_CALLS]);
		$this->assertSame(K::PHASE_AFTER_TOOLS, $result->getPatch()[K::PHASE]);
		$this->assertSame(['found' => 'cached'], $result->getPatch()[K::TOOL_RESULTS][0]->getOutput());
	}

	public function testLookupHitReportsCacheMetadataAndExecutionRecord(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plan = $this->stage($cache)->process($this->lookupContext())->getPatch()[K::TOOL_CACHE_PLANS]['c1'];
		$cache->entries[$plan['cache_key']] = $this->entryFromPlan($plan);
		$result = $this->stage($cache)->process($this->lookupContext());
		$this->assertTrue($result->getPatch()[K::TOOL_RESULTS][0]->getMetadata()['cache']['hit']);
		$this->assertTrue($result->getPatch()[K::EXECUTED_TOOL_CALLS][0]['cached']);
		$this->assertSame('cache_hit', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]->toArray()['reason']);
	}

	public function testLookupEmitsStartedAndFinishedEventsOnCacheHit(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plan = $this->stage($cache)->process($this->lookupContext())->getPatch()[K::TOOL_CACHE_PLANS]['c1'];
		$cache->entries[$plan['cache_key']] = $this->entryFromPlan($plan);
		$events = [];
		$context = $this->lookupContext();
		$context->setVar(K::EVENT_CALLBACK, static function(string $event, array $payload) use (&$events): void { $events[] = [$event, $payload]; });
		$this->stage($cache)->process($context);
		$this->assertSame(['tool.started', 'tool.finished'], array_column($events, 0));
		$this->assertSame('c1', $events[0][1]['call_id']);
		$this->assertTrue($events[1][1]['cached']);
	}

	public function testLookupContinuesWhenEventCallbackThrows(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plan = $this->stage($cache)->process($this->lookupContext())->getPatch()[K::TOOL_CACHE_PLANS]['c1'];
		$cache->entries[$plan['cache_key']] = $this->entryFromPlan($plan);
		$context = $this->lookupContext();
		$context->setVar(K::EVENT_CALLBACK, static function(): void { throw new \RuntimeException('UI disconnected'); });
		$result = $this->stage($cache)->process($context);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['hits']);
	}

	public function testStorePersistsSuccessfulResultUsingPlan(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->stage($cache, AgentToolResultCacheStage::CHECKPOINT_STORE)->process($this->storeContext([AgentToolResult::success('c1', 'lookup', [], ['ok' => true])], $this->plan()));
		$this->assertSame(['stored' => 1, 'skipped' => 0, 'errors' => 0], $result->getMetadata()['tool_cache']);
		$this->assertSame(45, $cache->writeTtls['cache-a']);
		$this->assertSame(['ok' => true], $cache->entries['cache-a']->getOutput());
	}

	public function testStoreRejectsOversizedResult(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->stage($cache, AgentToolResultCacheStage::CHECKPOINT_STORE)->process($this->storeContext([AgentToolResult::success('c1', 'lookup', [], str_repeat('x', 80))], $this->plan(), $this->config(maxBytes: 12)));
		$this->assertSame(1, $result->getMetadata()['tool_cache']['skipped']);
		$this->assertSame([], $cache->entries);
		$this->assertSame('entry_too_large', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]->toArray()['reason']);
	}

	public function testStoreSkipsFailedResult(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->stage($cache, AgentToolResultCacheStage::CHECKPOINT_STORE)->process($this->storeContext([AgentToolResult::failure('c1', 'lookup', [], 'error', 'timeout')], $this->plan()));
		$this->assertSame(0, $result->getMetadata()['tool_cache']['stored']);
		$this->assertSame([], $cache->entries);
	}

	public function testStoreSkipsResultWithoutPlan(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->stage($cache, AgentToolResultCacheStage::CHECKPOINT_STORE)->process($this->storeContext([AgentToolResult::success('c1', 'lookup', [], 'value')], []));
		$this->assertSame(0, $result->getMetadata()['tool_cache']['stored']);
	}

	public function testStoreDoesNotStoreCacheHitAgain(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->stage($cache, AgentToolResultCacheStage::CHECKPOINT_STORE)->process($this->storeContext([AgentToolResult::success('c1', 'lookup', [], ['cached' => true], ['cache' => ['hit' => true]])], $this->plan()));
		$this->assertSame(0, $result->getMetadata()['tool_cache']['stored']);
		$this->assertSame([], $cache->entries);
	}

	public function testStoreErrorDoesNotAffectToolResult(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$cache->failPut = true;
		$result = $this->stage($cache, AgentToolResultCacheStage::CHECKPOINT_STORE)->process($this->storeContext([AgentToolResult::success('c1', 'lookup', [], ['ok' => true])], $this->plan()));
		$this->assertSame(1, $result->getMetadata()['tool_cache']['errors']);
		$this->assertSame('Cache backend is read-only', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]->toArray()['reason']);
	}

	public function testStoreRejectsUnserializableOutput(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->stage($cache, AgentToolResultCacheStage::CHECKPOINT_STORE)->process($this->storeContext([AgentToolResult::success('c1', 'lookup', [], ['bad' => "\xB1"])], $this->plan()));
		$this->assertSame(1, $result->getMetadata()['tool_cache']['errors']);
		$this->assertSame([], $cache->entries);
	}
	public function testLookupDifferentCallsHaveDistinctPlansAndIndexes(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->lookupContext();
		$context->setVar(K::PENDING_TOOL_CALLS, [new AiToolCall('first', 'lookup', ['query' => 'a']), new AiToolCall('second', 'lookup', ['query' => 'b'])]);
		$result = $this->stage($cache)->process($context);
		$this->assertSame(2, $result->getMetadata()['tool_cache']['misses']);
		$this->assertSame(['first' => 1, 'second' => 2], $result->getPatch()[K::TOOL_CALL_INDEXES]);
		$this->assertCount(2, $cache->readKeys);
		$this->assertFalse($cache->readKeys[0] === $cache->readKeys[1]);
	}

	public function testLookupMixedKnownAndUnknownToolsNeverCoversUpMissingTool(): void {
		$context = $this->lookupContext();
		$context->setVar(K::PENDING_TOOL_CALLS, [new AiToolCall('first', 'missing', []), new AiToolCall('second', 'lookup', ['query' => 'a'])]);
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['misses']);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['bypassed']);
		$this->assertCount(2, $result->getPatch()[K::PENDING_TOOL_CALLS]);
	}

	public function testLookupCreatesCallIndexForMissingCallId(): void {
		$context = $this->lookupContext();
		$context->setVar(K::PENDING_TOOL_CALLS, [new AiToolCall('', 'lookup', [])]);
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame(1, $result->getPatch()[K::CALL_INDEX]);
		$this->assertCount(1, $result->getPatch()[K::TOOL_CALL_INDEXES]);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['misses']);
	}

	public function testLookupRetainsExistingExecutionInformation(): void {
		$context = $this->lookupContext();
		$previous = AgentToolResult::success('previous', 'lookup', [], ['found' => 'old']);
		$context->setVar(K::TOOL_RESULTS, [$previous]);
		$context->setVar(K::EXECUTED_TOOL_CALLS, [['tool' => 'previous']]);
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame([$previous], $result->getPatch()[K::TOOL_RESULTS]);
		$this->assertSame([['tool' => 'previous']], $result->getPatch()[K::EXECUTED_TOOL_CALLS]);
	}

	public function testLookupDoesNotMutateOriginalPendingCalls(): void {
		$context = $this->lookupContext();
		$calls = $context->getVar(K::PENDING_TOOL_CALLS);
		$this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame($calls, $context->getVar(K::PENDING_TOOL_CALLS));
	}

	public function testLookupConfigurationScopeUsesDeploymentIdentity(): void {
		$context = $this->lookupContext($this->config(AgentToolCacheConfig::SCOPE_CONFIGURATION));
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame('configuration:agent:one', $result->getPatch()[K::TOOL_CACHE_PLANS]['c1']['scope']);
	}

	public function testLookupChatbotScopeUsesChatbotIdentity(): void {
		$context = $this->lookupContext($this->config(AgentToolCacheConfig::SCOPE_CHATBOT));
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame('chatbot:chat-1', $result->getPatch()[K::TOOL_CACHE_PLANS]['c1']['scope']);
	}

	public function testLookupTurnScopeUsesTurnIdentity(): void {
		$context = $this->lookupContext($this->config(AgentToolCacheConfig::SCOPE_TURN));
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame('turn:turn-1', $result->getPatch()[K::TOOL_CACHE_PLANS]['c1']['scope']);
	}

	public function testLookupCustomScopeUsesExplicitNamespace(): void {
		$context = $this->lookupContext($this->config(AgentToolCacheConfig::SCOPE_CUSTOM));
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame('custom:tenant-a', $result->getPatch()[K::TOOL_CACHE_PLANS]['c1']['scope']);
	}

	public function testLookupMissingTraceUsesExplicitUnknownIdentifiers(): void {
		$context = $this->lookupContext($this->config(AgentToolCacheConfig::SCOPE_TURN));
		$context->setVar(K::TRACE, null);
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame('turn:unknown_turn', $result->getPatch()[K::TOOL_CACHE_PLANS]['c1']['scope']);
	}

	public function testLookupInvalidArgumentsRecordErrorAndKeepExecutionPending(): void {
		$context = $this->lookupContext();
		$context->setVar(K::PENDING_TOOL_CALLS, [new AiToolCall('c1', 'lookup', ['broken' => new \stdClass()])]);
		$result = $this->stage(new AgentToolCacheCoverageRecordingCache())->process($context);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['errors']);
		$this->assertCount(1, $result->getPatch()[K::PENDING_TOOL_CALLS]);
	}

	public function testStoreAcceptsMultiplePlannedSuccessfulResults(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plans = $this->plan();
		$plans['c2'] = ['cache_key' => 'cache-b', 'arguments_hash' => 'hash-b', 'tool_identity' => 'lookuptool', 'tool' => 'lookup', 'scope' => 'global', 'ttl_seconds' => 50];
		$context = $this->storeContext([AgentToolResult::success('c1', 'lookup', [], 'first'), AgentToolResult::success('c2', 'lookup', [], 'second')], $plans);
		$result = $this->stage($cache, AgentToolResultCacheStage::CHECKPOINT_STORE)->process($context);
		$this->assertSame(2, $result->getMetadata()['tool_cache']['stored']);
		$this->assertSame('first', $cache->entries['cache-a']->getOutput());
		$this->assertSame('second', $cache->entries['cache-b']->getOutput());
	}

	public function testStoreSkipsNonToolResultWithoutTouchingValidResult(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->storeContext(['not-result', AgentToolResult::success('c1', 'lookup', [], 'actual')], $this->plan());
		$result = $this->stage($cache, AgentToolResultCacheStage::CHECKPOINT_STORE)->process($context);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['stored']);
		$this->assertSame('actual', $cache->entries['cache-a']->getOutput());
	}

	public function testStoreKeepsEarlierCacheRecordsAndAddsNewRecord(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->storeContext([AgentToolResult::success('c1', 'lookup', [], 'valid')], $this->plan());
		$context->setVar(K::TOOL_CACHE_RECORDS, ['prior-record']);
		$result = $this->stage($cache, AgentToolResultCacheStage::CHECKPOINT_STORE)->process($context);
		$this->assertCount(2, $result->getPatch()[K::TOOL_CACHE_RECORDS]);
		$this->assertSame('prior-record', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]);
	}

}

require_once __DIR__ . '/AgentToolCacheCoverageRecordingCache.php';
require_once __DIR__ . '/RecordingEventManager.php';
require_once __DIR__ . '/LookupTool.php';

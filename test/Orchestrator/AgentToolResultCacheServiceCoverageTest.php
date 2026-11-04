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
use MissionBay\Orchestrator\Service\AgentToolResultCacheService;
use MissionBay\Orchestrator\Stage\AgentResultVerificationStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as K;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentToolResultCacheService::class)]
final class AgentToolResultCacheServiceCoverageTest extends TestCase {

	private function service(AgentToolCacheCoverageRecordingCache $cache): AgentToolResultCacheService {
		return new AgentToolResultCacheService($cache, new RecordingEventManager(), new AgentToolCacheKeyBuilder());
	}

	private function config(string $scope = AgentToolCacheConfig::SCOPE_GLOBAL, int $maxBytes = 262144): AgentToolCacheConfig {
		return new AgentToolCacheConfig(true, $scope, $scope === AgentToolCacheConfig::SCOPE_CUSTOM ? 'org-2' : '', 'mission', $maxBytes, [new AgentToolCacheRule('lookup', 60), new AgentToolCacheRule('contract_lookup', 90)]);
	}

	private function context(?AgentToolCacheConfig $config = null): AgentContext {
		return new AgentContext(vars: [
			K::TOOL_CACHE_CONFIG => $config ?? $this->config(),
			K::PENDING_TOOL_CALLS => [new AiToolCall('c1', 'lookup', ['query' => 'x'])],
			K::TOOLS => [new LookupTool()],
			K::TRACE => ['turn_id' => 'turn-42', 'chatbot_key' => 'bot-7', 'config_group' => 'production', 'config_name' => 'agent-1'],
			K::ITERATION => 4
		]);
	}

	private function preparePlan(AgentToolCacheCoverageRecordingCache $cache, ?AgentContext $context = null): array {
		$context ??= $this->context();
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		return $result->getPatch()[K::TOOL_CACHE_PLANS]['c1'];
	}

	private function entry(array $plan, mixed $output = ['found' => 'cached']): AgentToolCacheEntry {
		return new AgentToolCacheEntry($plan['tool_identity'], $plan['tool'], $plan['arguments_hash'], $plan['scope'], $output, '2026-01-01T00:00:00+00:00', '2027-01-01T00:00:00+00:00');
	}

	private function verifiedContext(array $results, array $plans, ?AgentToolCacheConfig $config = null): AgentContext {
		$context = $this->context($config);
		$context->setVar(K::RESULT_VERIFICATIONS, [new AgentResultVerification(4, AgentResultVerificationStage::VERIFIER, AgentResultVerification::VERDICT_VERIFIED, 'ok')]);
		$context->setVar(K::TOOL_CACHE_PLANS, $plans);
		$context->setVar(K::TOOL_RESULTS, $results);
		return $context;
	}

	private function storedPlan(): array {
		return ['c1' => ['cache_key' => 'cache-key', 'tool_identity' => 'lookuptool', 'tool' => 'lookup', 'arguments_hash' => 'aabb', 'scope' => 'global', 'ttl_seconds' => 60]];
	}

	public function testRejectsUnknownCheckpointBeforeAnythingElse(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service(new AgentToolCacheCoverageRecordingCache())->process(new AgentContext(), 'skipped');
	}

	public function testMissingConfigurationDoesNotTouchCache(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->service($cache)->process(new AgentContext(), AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertTrue($result->isEmpty());
		$this->assertSame([], $cache->readKeys);
	}

	public function testDisabledConfigurationDoesNotTouchCache(): void {
		$context = $this->context(AgentToolCacheConfig::disabled());
		$cache = new AgentToolCacheCoverageRecordingCache();
		$this->assertTrue($this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP)->isEmpty());
		$this->assertSame([], $cache->readKeys);
	}

	public function testUnavailableCacheDoesNotTouchBackend(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$cache->available = false;
		$this->assertTrue($this->service($cache)->process($this->context(), AgentToolResultCacheService::CHECKPOINT_LOOKUP)->isEmpty());
		$this->assertSame([], $cache->readKeys);
	}

	public function testStoreRequiresStructuralVerification(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->context();
		$context->setVar(K::TOOL_RESULTS, [AgentToolResult::success('c1', 'lookup', [], 'ok')]);
		$context->setVar(K::TOOL_CACHE_PLANS, $this->storedPlan());
		$this->assertTrue($this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_STORE)->isEmpty());
		$this->assertSame([], $cache->entries);
	}

	public function testStoreRequiresCurrentIteration(): void {
		$context = $this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], 'ok')], $this->storedPlan());
		$context->setVar(K::ITERATION, 5);
		$this->assertTrue($this->service(new AgentToolCacheCoverageRecordingCache())->process($context, AgentToolResultCacheService::CHECKPOINT_STORE)->isEmpty());
	}

	public function testStoreRejectsFailedStructuralVerification(): void {
		$context = $this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], 'ok')], $this->storedPlan());
		$context->setVar(K::RESULT_VERIFICATIONS, [new AgentResultVerification(4, AgentResultVerificationStage::VERIFIER, AgentResultVerification::VERDICT_FAILED, 'invalid')]);
		$this->assertTrue($this->service(new AgentToolCacheCoverageRecordingCache())->process($context, AgentToolResultCacheService::CHECKPOINT_STORE)->isEmpty());
	}

	public function testStoreDoesNotTrustSemanticOnlyVerification(): void {
		$context = $this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], 'ok')], $this->storedPlan());
		$context->setVar(K::RESULT_VERIFICATIONS, [new AgentResultVerification(4, 'semantic', AgentResultVerification::VERDICT_VERIFIED, 'ok')]);
		$this->assertTrue($this->service(new AgentToolCacheCoverageRecordingCache())->process($context, AgentToolResultCacheService::CHECKPOINT_STORE)->isEmpty());
	}

	public function testStoreIgnoresMalformedVerificationItems(): void {
		$context = $this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], 'ok')], $this->storedPlan());
		$context->setVar(K::RESULT_VERIFICATIONS, ['verified']);
		$this->assertTrue($this->service(new AgentToolCacheCoverageRecordingCache())->process($context, AgentToolResultCacheService::CHECKPOINT_STORE)->isEmpty());
	}

	public function testStoreUsesLastCurrentStructuralResult(): void {
		$context = $this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], 'ok')], $this->storedPlan());
		$context->setVar(K::RESULT_VERIFICATIONS, [
			new AgentResultVerification(4, AgentResultVerificationStage::VERIFIER, AgentResultVerification::VERDICT_VERIFIED, 'ok'),
			new AgentResultVerification(4, AgentResultVerificationStage::VERIFIER, AgentResultVerification::VERDICT_FAILED, 'failed')
		]);
		$this->assertTrue($this->service(new AgentToolCacheCoverageRecordingCache())->process($context, AgentToolResultCacheService::CHECKPOINT_STORE)->isEmpty());
	}

	public function testCacheMissCreatesPlanAndLeavesCallPending(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->service($cache)->process($this->context(), AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['misses']);
		$this->assertCount(1, $cache->readKeys);
		$this->assertCount(1, $result->getPatch()[K::PENDING_TOOL_CALLS]);
		$this->assertSame(60, $result->getPatch()[K::TOOL_CACHE_PLANS]['c1']['ttl_seconds']);
	}

	public function testNonToolPayloadIsPreserved(): void {
		$context = $this->context();
		$context->setVar(K::PENDING_TOOL_CALLS, [['id' => 'raw']]);
		$result = $this->service(new AgentToolCacheCoverageRecordingCache())->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame([['id' => 'raw']], $result->getPatch()[K::PENDING_TOOL_CALLS]);
		$this->assertSame(0, $result->getPatch()[K::CALL_INDEX]);
	}

	public function testUnknownToolBypassesLookup(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->context();
		$context->setVar(K::TOOLS, []);
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['bypassed']);
		$this->assertSame('tool_not_found', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]->toArray()['reason']);
		$this->assertSame([], $cache->readKeys);
	}

	public function testUnconfiguredToolBypassesLookup(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->context(new AgentToolCacheConfig(true, AgentToolCacheConfig::SCOPE_GLOBAL, '', 'n', 100, [new AgentToolCacheRule('other', 11)]));
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame('no_matching_rule', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]->toArray()['reason']);
		$this->assertSame([], $cache->readKeys);
	}

	public function testMutationCannotBeReadFromCacheEvenWithMatchingRule(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->context();
		$context->setVar(K::TOOL_DEFINITIONS, [[
			'annotations' => ['readOnlyHint' => false],
			'function' => ['name' => 'lookup', 'parameters' => ['type' => 'object']]
		]]);
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['bypassed']);
		$this->assertSame('mutation_not_cacheable', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]->toArray()['reason']);
		$this->assertSame([], $cache->readKeys);
	}

	public function testExplicitReadOnlyToolRemainsCacheEligible(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->context();
		$context->setVar(K::TOOL_DEFINITIONS, [[
			'annotations' => ['readOnlyHint' => true],
			'function' => ['name' => 'lookup', 'parameters' => ['type' => 'object']]
		]]);
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['misses']);
	}

	public function testCacheReadErrorDoesNotReplaceActualExecution(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$cache->failGet = true;
		$result = $this->service($cache)->process($this->context(), AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['errors']);
		$this->assertSame([], $result->getPatch()[K::TOOL_RESULTS]);
		$this->assertCount(1, $result->getPatch()[K::PENDING_TOOL_CALLS]);
	}

	public function testCachedEntryMustMatchToolIdentity(): void {
		$this->assertMismatchedEntryDeleted('identity');
	}

	public function testCachedEntryMustMatchToolName(): void {
		$this->assertMismatchedEntryDeleted('tool');
	}

	public function testCachedEntryMustMatchArgumentsHash(): void {
		$this->assertMismatchedEntryDeleted('arguments');
	}

	public function testCachedEntryMustMatchScope(): void {
		$this->assertMismatchedEntryDeleted('scope');
	}

	private function assertMismatchedEntryDeleted(string $mismatch): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plan = $this->preparePlan($cache);
		$identity = $mismatch === 'identity' ? 'other-implementation' : $plan['tool_identity'];
		$name = $mismatch === 'tool' ? 'other_name' : $plan['tool'];
		$hash = $mismatch === 'arguments' ? 'wrong-hash' : $plan['arguments_hash'];
		$scope = $mismatch === 'scope' ? 'another-tenant' : $plan['scope'];
		$cache->entries[$plan['cache_key']] = new AgentToolCacheEntry($identity, $name, $hash, $scope, 'stale', '', '');
		$result = $this->service($cache)->process($this->context(), AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['misses']);
		$this->assertSame([$plan['cache_key']], $cache->deletedKeys);
		$this->assertCount(1, $result->getPatch()[K::PENDING_TOOL_CALLS]);
	}

	public function testValidCachedEntrySkipsRealToolExecution(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plan = $this->preparePlan($cache);
		$cache->entries[$plan['cache_key']] = $this->entry($plan);
		$result = $this->service($cache)->process($this->context(), AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['hits']);
		$this->assertSame([], $result->getPatch()[K::PENDING_TOOL_CALLS]);
		$this->assertSame(['found' => 'cached'], $result->getPatch()[K::TOOL_RESULTS][0]->getOutput());
		$this->assertSame(K::PHASE_AFTER_TOOLS, $result->getPatch()[K::PHASE]);
	}

	public function testCacheHitIsRecordedAsContractNotDeclaredWhenNoOutputSchemaExists(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plan = $this->preparePlan($cache);
		$cache->entries[$plan['cache_key']] = $this->entry($plan);
		$result = $this->service($cache)->process($this->context(), AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertCount(1, $result->getPatch()[K::TOOL_CONTRACT_VALIDATIONS]);
		$this->assertTrue($result->getPatch()[K::TOOL_CONTRACT_VALIDATIONS][0]->passes());
		$this->assertSame('not_declared', $result->getPatch()[K::TOOL_CONTRACT_VALIDATIONS][0]->getStatus());
	}

	public function testInvalidCachedOutputIsPurgedAndToolRunsNormally(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->context();
		$context->setVar(K::TOOLS, [new InvalidOutputContractTool()]);
		$context->setVar(K::PENDING_TOOL_CALLS, [new AiToolCall('c1', 'contract_lookup', ['query' => 'x'])]);
		$plan = $this->preparePlan($cache, $context);
		$cache->entries[$plan['cache_key']] = $this->entry($plan, ['found' => 'not_boolean']);
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['errors']);
		$this->assertSame('cache_output_contract_invalid', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]->toArray()['reason']);
		$this->assertSame([$plan['cache_key']], $cache->deletedKeys);
		$this->assertCount(1, $result->getPatch()[K::PENDING_TOOL_CALLS]);
	}

	public function testValidCachedOutputPassesDeclaredSchema(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->context();
		$context->setVar(K::TOOLS, [new InvalidOutputContractTool()]);
		$context->setVar(K::PENDING_TOOL_CALLS, [new AiToolCall('c1', 'contract_lookup', ['query' => 'x'])]);
		$plan = $this->preparePlan($cache, $context);
		$cache->entries[$plan['cache_key']] = $this->entry($plan, ['found' => true]);
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['hits']);
		$this->assertTrue($result->getPatch()[K::TOOL_CONTRACT_VALIDATIONS][0]->isValidated());
	}

	public function testReadOnlyCachedHitEmitsUiEvents(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plan = $this->preparePlan($cache);
		$cache->entries[$plan['cache_key']] = $this->entry($plan);
		$events = [];
		$context = $this->context();
		$context->setVar(K::EVENT_CALLBACK, static function(string $name, array $payload) use (&$events): void { $events[] = [$name, $payload]; });
		$this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(['tool.started', 'tool.finished'], array_column($events, 0));
		$this->assertSame('turn-42', $events[0][1]['turn_id']);
		$this->assertTrue($events[0][1]['cached']);
	}

	public function testEventCallbackFailureDoesNotDiscardValidCacheHit(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plan = $this->preparePlan($cache);
		$cache->entries[$plan['cache_key']] = $this->entry($plan);
		$context = $this->context();
		$context->setVar(K::EVENT_CALLBACK, static function(): void { throw new \RuntimeException('UI event failed'); });
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['hits']);
	}

	public function testConfigurationScopeIncludesGroupAndName(): void {
		$this->assertSame('configuration:production:agent-1', $this->scopeFor(AgentToolCacheConfig::SCOPE_CONFIGURATION));
	}

	public function testChatbotScopeIsBoundToBot(): void {
		$this->assertSame('chatbot:bot-7', $this->scopeFor(AgentToolCacheConfig::SCOPE_CHATBOT));
	}

	public function testTurnScopeIsBoundToTurn(): void {
		$this->assertSame('turn:turn-42', $this->scopeFor(AgentToolCacheConfig::SCOPE_TURN));
	}

	public function testCustomScopeIsBoundToNamedTenant(): void {
		$this->assertSame('custom:org-2', $this->scopeFor(AgentToolCacheConfig::SCOPE_CUSTOM));
	}

	public function testGlobalScopeSharesDeclaredKey(): void {
		$this->assertSame('global', $this->scopeFor(AgentToolCacheConfig::SCOPE_GLOBAL));
	}

	private function scopeFor(string $scope): string {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->service($cache)->process($this->context($this->config($scope)), AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		return $result->getPatch()[K::TOOL_CACHE_PLANS]['c1']['scope'];
	}

	public function testStorePersistsValidatedSuccessWithTtl(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->service($cache)->process($this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], ['ok' => true])], $this->storedPlan()), AgentToolResultCacheService::CHECKPOINT_STORE);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['stored']);
		$this->assertSame(60, $cache->writeTtls['cache-key']);
		$this->assertSame('aabb', $cache->entries['cache-key']->getArgumentsHash());
	}

	public function testStoreDoesNotCacheFailedToolResult(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->service($cache)->process($this->verifiedContext([AgentToolResult::failure('c1', 'lookup', [], 'timeout', 'unavailable')], $this->storedPlan()), AgentToolResultCacheService::CHECKPOINT_STORE);
		$this->assertSame(0, $result->getMetadata()['tool_cache']['stored']);
		$this->assertSame([], $cache->entries);
	}

	public function testStoreDoesNotCacheHitAgain(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->service($cache)->process($this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], 'already cached', ['cache' => ['hit' => true]])], $this->storedPlan()), AgentToolResultCacheService::CHECKPOINT_STORE);
		$this->assertSame(0, $result->getMetadata()['tool_cache']['stored']);
	}

	public function testStoreDoesNotCacheResultWithoutPlan(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->service($cache)->process($this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], 'value')], []), AgentToolResultCacheService::CHECKPOINT_STORE);
		$this->assertSame(0, $result->getMetadata()['tool_cache']['stored']);
	}

	public function testStoreRejectsTooLargeOutput(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->service($cache)->process($this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], str_repeat('x', 100))], $this->storedPlan(), $this->config(maxBytes: 16)), AgentToolResultCacheService::CHECKPOINT_STORE);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['skipped']);
		$this->assertSame('entry_too_large', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]->toArray()['reason']);
	}

	public function testStoreDoesNotTreatSerializationFailureAsSuccessful(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$result = $this->service($cache)->process($this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], ['bad' => "\xB1"])], $this->storedPlan()), AgentToolResultCacheService::CHECKPOINT_STORE);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['errors']);
		$this->assertSame([], $cache->entries);
	}

	public function testStoreBackendErrorDoesNotOverrideResult(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$cache->failPut = true;
		$result = $this->service($cache)->process($this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], ['ok' => true])], $this->storedPlan()), AgentToolResultCacheService::CHECKPOINT_STORE);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['errors']);
		$this->assertSame('Cache backend is read-only', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]->toArray()['reason']);
	}

	public function testStoreRecordsOriginalToolCallProvenance(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$this->service($cache)->process($this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], ['ok' => true])], $this->storedPlan()), AgentToolResultCacheService::CHECKPOINT_STORE);
		$this->assertSame('c1', $cache->entries['cache-key']->getMetadata()['call_id']);
		$this->assertSame(4, $cache->entries['cache-key']->getMetadata()['iteration']);
	}
	public function testMultipleReadOnlyCallsCreateIndependentPlans(): void {
		$context = $this->context();
		$context->setVar(K::PENDING_TOOL_CALLS, [new AiToolCall('c1', 'lookup', ['query' => 'one']), new AiToolCall('c2', 'lookup', ['query' => 'two'])]);
		$result = $this->service(new AgentToolCacheCoverageRecordingCache())->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(2, $result->getMetadata()['tool_cache']['misses']);
		$this->assertCount(2, $result->getPatch()[K::TOOL_CACHE_PLANS]);
		$this->assertFalse($result->getPatch()[K::TOOL_CACHE_PLANS]['c1']['cache_key'] === $result->getPatch()[K::TOOL_CACHE_PLANS]['c2']['cache_key']);
	}

	public function testReadOnlyAndMutatingCallsAreKeptSeparate(): void {
		$context = $this->context();
		$context->setVar(K::PENDING_TOOL_CALLS, [new AiToolCall('c1', 'lookup', ['query' => 'a']), new AiToolCall('c2', 'contract_lookup', ['query' => 'b'])]);
		$context->setVar(K::TOOLS, [new LookupTool(), new InvalidOutputContractTool()]);
		$context->setVar(K::TOOL_DEFINITIONS, [['function' => ['name' => 'lookup'], 'annotations' => ['readOnlyHint' => true]], ['function' => ['name' => 'contract_lookup'], 'annotations' => ['readOnlyHint' => false]]]);
		$result = $this->service(new AgentToolCacheCoverageRecordingCache())->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['misses']);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['bypassed']);
		$this->assertSame('mutation_not_cacheable', $result->getPatch()[K::TOOL_CACHE_RECORDS][1]->toArray()['reason']);
	}

	public function testLookupMissingCallIdIsAssignedInternalIndex(): void {
		$context = $this->context();
		$context->setVar(K::PENDING_TOOL_CALLS, [new AiToolCall('', 'lookup', [])]);
		$result = $this->service(new AgentToolCacheCoverageRecordingCache())->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame(1, $result->getPatch()[K::CALL_INDEX]);
		$this->assertCount(1, $result->getPatch()[K::TOOL_CALL_INDEXES]);
	}

	public function testCacheHitKeepsPriorContractValidations(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plan = $this->preparePlan($cache);
		$cache->entries[$plan['cache_key']] = $this->entry($plan);
		$context = $this->context();
		$context->setVar(K::TOOL_CONTRACT_VALIDATIONS, ['previous']);
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame('previous', $result->getPatch()[K::TOOL_CONTRACT_VALIDATIONS][0]);
		$this->assertCount(2, $result->getPatch()[K::TOOL_CONTRACT_VALIDATIONS]);
	}

	public function testLookupExistingToolResultsAreNotDiscardedOnCacheMiss(): void {
		$context = $this->context();
		$previous = AgentToolResult::success('old', 'lookup', [], ['answer' => 1]);
		$context->setVar(K::TOOL_RESULTS, [$previous]);
		$result = $this->service(new AgentToolCacheCoverageRecordingCache())->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame([$previous], $result->getPatch()[K::TOOL_RESULTS]);
	}

	public function testMissingTraceDataCannotBorrowRealUserScope(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->context($this->config(AgentToolCacheConfig::SCOPE_CHATBOT));
		$context->setVar(K::TRACE, []);
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		$this->assertSame('chatbot:unknown_chatbot', $result->getPatch()[K::TOOL_CACHE_PLANS]['c1']['scope']);
	}

	public function testStoreMultipleToolResultsOnlyStoresPlannedSuccesses(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$plans = $this->storedPlan();
		$plans['c2'] = ['cache_key' => 'other-key', 'tool' => 'lookup', 'tool_identity' => 'lookuptool', 'arguments_hash' => 'bb', 'scope' => 'global', 'ttl_seconds' => 20];
		$context = $this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], 'one'), AgentToolResult::failure('c2', 'lookup', [], 'error', 'not found')], $plans);
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_STORE);
		$this->assertSame(1, $result->getMetadata()['tool_cache']['stored']);
		$this->assertSame('one', $cache->entries['cache-key']->getOutput());
		$this->assertSame([], array_intersect(['other-key'], array_keys($cache->entries)));
	}

	public function testStorePreservesPreviousAuditRecords(): void {
		$cache = new AgentToolCacheCoverageRecordingCache();
		$context = $this->verifiedContext([AgentToolResult::success('c1', 'lookup', [], 'ok')], $this->storedPlan());
		$context->setVar(K::TOOL_CACHE_RECORDS, ['prior']);
		$result = $this->service($cache)->process($context, AgentToolResultCacheService::CHECKPOINT_STORE);
		$this->assertSame('prior', $result->getPatch()[K::TOOL_CACHE_RECORDS][0]);
		$this->assertCount(2, $result->getPatch()[K::TOOL_CACHE_RECORDS]);
	}

}

require_once __DIR__ . '/AgentToolCacheCoverageRecordingCache.php';
require_once __DIR__ . '/RecordingEventManager.php';
require_once __DIR__ . '/LookupTool.php';
require_once __DIR__ . '/InvalidOutputContractTool.php';

<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentActionResumeService;
use MissionBay\Orchestrator\Service\AgentActionReviewService;
use MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use MissionBay\Orchestrator\Service\AgentInteractionResponseResolver;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Validation\JsonSchemaValidator;
use MissionBay\Service\AgentComponentPresetToolTestService;
use MissionBay\Service\RetrievalSearchService;
use MissionBay\Test\Profile\ProfileToolsTestAgentTool;
use PHPUnit\Framework\TestCase;

final class RetrievalSearchServiceBehaviorTest extends TestCase {

	public function testEmptyRepositoryHasNoSearchPresets(): void {
		$this->assertSame([], $this->service([])->getSearchPresets());
	}

	public function testBlankPresetIdentifiersAreIgnored(): void {
		$service = $this->service([' ' => ['label' => 'Ignored'], '' => []]);
		$this->assertSame([], $service->getSearchPresets());
	}

	public function testSearchPresetsAreSortedCaseInsensitivelyByLabel(): void {
		$service = $this->service([
			'z' => ['label' => 'Zebra'],
			'a' => ['label' => 'apple'],
			'b' => ['label' => 'Banana']
		], [
			'z' => $this->materialization($this->tool([$this->definition('retrieval_search')])),
			'a' => $this->materialization($this->tool([$this->definition('retrieval_search')])),
			'b' => $this->materialization($this->tool([$this->definition('retrieval_search')]))
		]);
		$this->assertSame(['a', 'b', 'z'], array_column($service->getSearchPresets(), 'id'));
	}

	public function testMissingLabelFallsBackToPresetId(): void {
		$service = $this->singlePreset(['label' => '  '], [$this->definition('retrieval_search')]);
		$this->assertSame('example', $service->getSearchPresets()[0]['label']);
	}

	public function testLabelIsTrimmedWithoutChangingCase(): void {
		$service = $this->singlePreset(['label' => '  Team Search  '], [$this->definition('retrieval_search')]);
		$this->assertSame('Team Search', $service->getSearchPresets()[0]['label']);
	}

	public function testDescriptionPrefersMetadataOverFallback(): void {
		$service = $this->singlePreset(['meta' => ['description' => '  Metadata  '], 'description' => 'Old'], [$this->definition('retrieval_search')]);
		$this->assertSame('Metadata', $service->getSearchPresets()[0]['description']);
	}

	public function testDescriptionFallsBackToTopLevelField(): void {
		$service = $this->singlePreset(['description' => '  Top level  '], [$this->definition('retrieval_search')]);
		$this->assertSame('Top level', $service->getSearchPresets()[0]['description']);
	}

	public function testMissingDescriptionIsEmpty(): void {
		$service = $this->singlePreset([], [$this->definition('retrieval_search')]);
		$this->assertSame('', $service->getSearchPresets()[0]['description']);
	}

	public function testContextAvailabilityReflectsExposedFunction(): void {
		$service = $this->singlePreset([], [
			$this->definition('retrieval_search'),
			$this->definition('retrieval_context')
		]);
		$this->assertTrue($service->getSearchPresets()[0]['has_context']);
	}

	public function testMissingContextFunctionIsNotAdvertised(): void {
		$service = $this->singlePreset([], [$this->definition('retrieval_search')]);
		$this->assertFalse($service->getSearchPresets()[0]['has_context']);
	}

	public function testNamespacedSearchAndContextFunctionsAreRecognized(): void {
		$service = $this->singlePreset([], [
			$this->definition('catalog__retrieval_search'),
			$this->definition('catalog__retrieval_context')
		]);
		$this->assertTrue($service->getSearchPresets()[0]['has_context']);
		$this->assertSame('catalog__retrieval_search', $service->getSearchPresets()[0]['search_definition']['function']['name']);
	}

	public function testArbitraryPrefixesDoNotCountAsRetrievalSearch(): void {
		$service = $this->singlePreset([], [$this->definition('catalog_retrieval_search')]);
		$this->assertSame([], $service->getSearchPresets());
	}

	public function testContextOnlyPresetsAreNotExposedAsSearchPresets(): void {
		$service = $this->singlePreset([], [$this->definition('retrieval_context')]);
		$this->assertSame([], $service->getSearchPresets());
	}

	public function testMaterializationWithoutToolIsNotListed(): void {
		$service = $this->service(['example' => []], ['example' => $this->materialization(null)]);
		$this->assertSame([], $service->getSearchPresets());
	}

	public function testBrokenPresetIsSkippedWithoutHidingHealthyPresets(): void {
		$service = $this->service(['broken' => [], 'healthy' => ['label' => 'Available']], [
			'broken' => new \RuntimeException('Unavailable profile'),
			'healthy' => $this->materialization($this->tool([$this->definition('retrieval_search')]))
		]);
		$presets = $service->getSearchPresets();
		$this->assertCount(1, $presets);
		$this->assertSame('healthy', $presets[0]['id']);
	}

	public function testMaterializationWarningsAreReturnedToCatalog(): void {
		$service = $this->service(['example' => []], [
			'example' => $this->materialization($this->tool([$this->definition('retrieval_search')]), ['Missing optional dock'])
		]);
		$this->assertSame(['Missing optional dock'], $service->getSearchPresets()[0]['warnings']);
	}

	public function testFlatFunctionDefinitionIsRecognized(): void {
		$definition = ['name' => 'retrieval_search', 'parameters' => ['type' => 'object']];
		$service = $this->singlePreset([], [$definition]);
		$this->assertSame($definition, $service->getSearchPresets()[0]['search_definition']);
	}

	public function testMalformedDefinitionsAreIgnoredDuringCatalogDiscovery(): void {
		$service = $this->singlePreset([], [[], ['name' => 'other'], $this->definition('retrieval_search')]);
		$this->assertCount(1, $service->getSearchPresets());
	}

	public function testNonArrayToolDefinitionsAreIgnoredDuringDiscovery(): void {
		$service = $this->singlePreset([], [null, 'broken', $this->definition('retrieval_search')]);
		$this->assertCount(1, $service->getSearchPresets());
	}

	public function testFunctionDefinitionWithInvalidNestedShapeIsIgnored(): void {
		$service = $this->singlePreset([], [['function' => 'invalid'], $this->definition('retrieval_search')]);
		$this->assertSame('retrieval_search', $service->getSearchPresets()[0]['search_definition']['function']['name']);
	}

	public function testCatalogContextContainsSourceAndPresetId(): void {
		$seen = [];
		$service = $this->service(['example' => []], [
			'example' => $this->materialization($this->tool([$this->definition('retrieval_search')]))
		], $seen);
		$service->getSearchPresets(['source' => '  admin-panel  ', 'user' => 'alice', 'component_preset_id' => 'wrong']);
		$this->assertSame('admin-panel', $seen[0]['source']);
		$this->assertSame('example', $seen[0]['component_preset_id']);
		$this->assertSame('alice', $seen[0]['user']);
	}

	public function testEmptyCatalogContextSourceUsesRetrievalDefault(): void {
		$seen = [];
		$service = $this->service(['example' => []], [
			'example' => $this->materialization($this->tool([$this->definition('retrieval_search')]))
		], $seen);
		$service->getSearchPresets(['source' => '   ']);
		$this->assertSame('retrieval-search', $seen[0]['source']);
	}

	public function testEmptySearchPresetIdIsRejectedBeforeMaterialization(): void {
		$this->assertRejected(fn(): mixed => $this->service([])->search('  ', []), \InvalidArgumentException::class, 'Missing preset_id.');
	}

	public function testEmptyContextPresetIdIsRejectedBeforeMaterialization(): void {
		$this->assertRejected(fn(): mixed => $this->service([])->context('', []), \InvalidArgumentException::class, 'Missing preset_id.');
	}

	public function testSearchRequiresSearchFunction(): void {
		$this->assertRejected(fn(): mixed => $this->singlePreset([], [$this->definition('retrieval_context')])->search('example', []), \RuntimeException::class, 'Selected preset does not expose retrieval_search.');
	}

	public function testContextRequiresContextFunction(): void {
		$this->assertRejected(fn(): mixed => $this->singlePreset([], [$this->definition('retrieval_search')])->context('example', []), \RuntimeException::class, 'Selected preset does not expose retrieval_context.');
	}

	public function testSearchWithoutMaterializedToolRaisesExplicitError(): void {
		$this->assertRejected(fn(): mixed => $this->service(['example' => []], ['example' => $this->materialization(null)])->search('example', []), \RuntimeException::class, 'Preset does not expose an agent tool: example');
	}

	public function testMissingMaterializationFailureIsNotSuppressedOnInvocation(): void {
		$this->assertRejected(fn(): mixed => $this->service(['example' => []], ['example' => new \RuntimeException('Materialization failed')])->search('example', []), \RuntimeException::class, 'Materialization failed');
	}

	public function testSearchUsesQualifiedToolFunctionNameAndDropsUnknownArguments(): void {
		$service = $this->singlePreset([], [
			$this->definition('catalog__retrieval_search', ['query', 'limit'])
		]);
		$result = $service->search(' example ', ['query' => 'test', 'limit' => 2, 'token' => 'do-not-forward']);
		$this->assertSame('example', $result['preset_id']);
		$this->assertSame(['query' => 'test', 'limit' => 2], $result['arguments']);
		$this->assertSame('catalog__retrieval_search', $result['output']['name'] ?? null);
		$this->assertSame(['query' => 'test', 'limit' => 2], $result['output']['arguments'] ?? null);
	}

	public function testContextInvokesDedicatedContextFunction(): void {
		$service = $this->singlePreset([], [
			$this->definition('retrieval_search'),
			$this->definition('retrieval_context', ['query'])
		]);
		$result = $service->context('example', ['query' => 'source', 'ignored' => 'x']);
		$this->assertSame('retrieval_context', $result['output']['name'] ?? null);
		$this->assertSame(['query' => 'source'], $result['arguments']);
	}

	public function testEmptyParameterPropertiesPassesArgumentsThrough(): void {
		$service = $this->singlePreset([], [$this->definition('retrieval_search')]);
		$result = $service->search('example', ['query' => 'source', 'custom' => 3]);
		$this->assertSame(['query' => 'source', 'custom' => 3], $result['arguments']);
	}

	public function testArgumentsAreOrderedAsDeclaredInFunctionSchema(): void {
		$service = $this->singlePreset([], [$this->definition('retrieval_search', ['limit', 'query'])]);
		$result = $service->search('example', ['query' => 'source', 'limit' => 2]);
		$this->assertSame(['limit', 'query'], array_keys($result['arguments']));
	}

	public function testMissingOptionalArgumentsAreNotInvented(): void {
		$service = $this->singlePreset([], [$this->definition('retrieval_search', ['query', 'limit'])]);
		$result = $service->search('example', ['query' => 'source']);
		$this->assertSame(['query' => 'source'], $result['arguments']);
	}

	public function testMaterializationWarningsAreIncludedInExecution(): void {
		$service = $this->service(['example' => []], [
			'example' => $this->materialization($this->tool([$this->definition('retrieval_search')]), ['Context missing optional metadata'])
		]);
		$result = $service->search('example', ['query' => 'source']);
		$this->assertSame(['Context missing optional metadata'], $result['warnings']);
	}

	public function testSourceDefaultsForSearchExecution(): void {
		$seen = [];
		$service = $this->service(['example' => []], [
			'example' => $this->materialization($this->tool([$this->definition('retrieval_search')]))
		], $seen);
		$service->search('example', [], ['source' => ' ', 'trace_id' => 'trace-1']);
		$this->assertSame('retrieval-search', $seen[0]['source']);
		$this->assertSame('trace-1', $seen[0]['trace_id']);
	}

	public function testExecutionResponseRetainsRawExecutionStatus(): void {
		$service = $this->singlePreset([], [$this->definition('retrieval_search')]);
		$result = $service->search('example', []);
		$this->assertSame((bool)($result['execution']['ok'] ?? false), $result['ok']);
		$this->assertArrayHasKey('output', $result);
		$this->assertArrayHasKey('warnings', $result);
	}


	private function assertRejected(callable $invocation, string $type, string $message): void {
		try {
			$invocation();
		}
		catch(\Throwable $exception) {
			$this->assertTrue($exception instanceof $type);
			$this->assertStringContainsString($message, $exception->getMessage());
			return;
		}
		$this->fail('Expected ' . $type . ': ' . $message);
	}

	/** @param array<string,mixed> $preset @param array<int,array<string,mixed>> $definitions */
	private function singlePreset(array $preset, array $definitions): RetrievalSearchService {
		return $this->service(['example' => $preset], [
			'example' => $this->materialization($this->tool($definitions))
		]);
	}

	/** @param array<int,array<string,mixed>> $definitions */
	private function tool(array $definitions): IAgentTool {
		return new ProfileToolsTestAgentTool($definitions);
	}

	/** @param array<int,string> $properties @return array<string,mixed> */
	private function definition(string $name, array $properties = []): array {
		$schema = [];
		foreach($properties as $key) {
			$schema[$key] = ['type' => $key === 'limit' ? 'integer' : 'string'];
		}
		return [
			'type' => 'function',
			'readOnlyHint' => true,
			'function' => [
				'name' => $name,
				'parameters' => ['type' => 'object', 'properties' => $schema]
			]
		];
	}

	/** @param array<int,string> $warnings */
	private function materialization(?IAgentTool $tool, array $warnings = []): AgentComponentPresetMaterialization {
		return new AgentComponentPresetMaterialization('example', [], null, $tool, null, null, [], $warnings);
	}

	/**
	 * @param array<string,array<string,mixed>> $presets
	 * @param array<string,AgentComponentPresetMaterialization|\Throwable> $materializations
	 * @param array<int,array<string,mixed>> $contextVars
	 */
	private function service(array $presets, array $materializations = [], array &$contextVars = []): RetrievalSearchService {
		$repository = $this->createStub(IAgentComponentPresetRepository::class);
		$repository->method('getPresets')->willReturn($presets);
		$materializer = $this->createStub(IAgentComponentPresetMaterializer::class);
		$materializer->method('createContext')->willReturnCallback(static function(array $vars) use (&$contextVars): IAgentContext {
			$contextVars[] = $vars;
			return new AgentContext();
		});
		$materializer->method('materialize')->willReturnCallback(static function(string $id) use ($materializations): AgentComponentPresetMaterialization {
			$value = $materializations[$id] ?? new \RuntimeException('Unknown preset: ' . $id);
			if($value instanceof \Throwable) {
				throw $value;
			}
			return $value;
		});
		return new RetrievalSearchService($repository, $materializer, $this->tester());
	}

	private function tester(): AgentComponentPresetToolTestService {
		$events = new ComponentPresetTestEventManager();
		$fingerprint = new AgentActionFingerprint();
		$semantics = new AgentToolDefinitionSemantics();
		$commit = new AgentMutationCommitGuardService($fingerprint, $events, $semantics);
		$validation = new AgentToolContractValidationService(new JsonSchemaValidator());
		$suspensions = new ComponentPresetTestSuspensionRepository();
		return new AgentComponentPresetToolTestService(
			new ComponentPresetTestActionPolicyResolver(),
			$fingerprint,
			new AgentActionReviewService($fingerprint, $suspensions, 900, $commit, $events),
			new AgentActionResumeService($fingerprint, $suspensions, $events, new AgentInteractionResponseResolver()),
			$validation,
			new AgentCapabilitySelectionGuardService(),
			$commit,
			$events,
			$semantics
		);
	}
}

require_once __DIR__ . '/ComponentPresetTestActionPolicyResolver.php';
require_once __DIR__ . '/ComponentPresetTestSuspensionRepository.php';
require_once __DIR__ . '/ComponentPresetTestEventManager.php';
require_once dirname(__DIR__) . '/Profile/ProfileToolsTestAgentTool.php';

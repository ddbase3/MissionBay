<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IPhoneticEncoder;
use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use AssistantFoundation\Api\IRetrievalFilterProvider;
use AssistantFoundation\Api\IRetrievalIndex;
use AssistantFoundation\Dto\RetrievalSearchRequest;
use AssistantFoundation\Dto\RetrievalSearchResult;
use Base3\Api\IClassMap;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IRetrievalCollectionConfigRepository;
use MissionBay\Resource\ConfiguredChatModelAgentResource;
use MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use MissionBay\Resource\ConfiguredVectorStoreAgentResource;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use MissionBay\Resource\EmbeddingCacheAgentResource;
use MissionBay\Resource\RetrievalAgentTool;
use MissionBay\Resource\RoutingChatModelAgentResource;
use MissionBay\Retrieval\DefaultRetrievalCollectionDefinition;
use MissionBay\Retrieval\PhoneticTextMaterializer;
use Base3\State\Api\IStateStore;
use MissionBay\Service\AiProviderFileReferenceStateRepository;
use ResourceFoundation\Api\IManagedFileStorageService;
use PHPUnit\Framework\TestCase;

final class RetrievalCompositionSchemaTest extends TestCase {

	public function testRetrievalPublishesConfigSchemaAndComposableDocks(): void {
		$classMap = $this->createStub(IClassMap::class);
		$definition = $this->defaultDefinition();
		$resource = new RetrievalAgentTool(
			$this->resolver(),
			$definition,
			new PhoneticTextMaterializer($classMap, $definition),
			'retrieval'
		);
		$schema = $resource->getSchema();
		$docks = $this->docksByName($resource->getDockDefinitions());

		$this->assertSame('integer', $schema['properties']['limit']['type']);
		$this->assertSame(5, $schema['properties']['limit']['default']);
		$this->assertSame(1, $schema['properties']['limit']['minimum']);
		$this->assertSame(['number', 'null'], $schema['properties']['minscore']['type']);
		$this->assertNull($schema['properties']['minscore']['default']);
		$this->assertSame(20, $schema['properties']['candidate_limit']['default']);
		$this->assertSame('default', $schema['properties']['collectionkey']['default']);

		$this->assertSame(IAiEmbeddingModel::class, $docks['embedding']->interface);
		$this->assertSame(1, $docks['embedding']->maxConnections);
		$this->assertTrue($docks['embedding']->required);
		$this->assertSame(IRetrievalIndex::class, $docks['vectorstore']->interface);
		$this->assertSame(1, $docks['vectorstore']->maxConnections);
		$this->assertTrue($docks['vectorstore']->required);
		$this->assertSame(IRetrievalFilterProvider::class, $docks['filters']->interface);
		$this->assertNull($docks['filters']->maxConnections);
		$this->assertFalse($docks['filters']->required);
		$this->assertSame(ILogger::class, $docks['logger']->interface);
		$this->assertSame(1, $docks['logger']->maxConnections);
		$this->assertFalse($docks['logger']->required);
	}


	public function testRetrievalToolDefinitionsMakeSearchModeAndContextReferenceExplicit(): void {
		$classMap = $this->createStub(IClassMap::class);
		$definition = $this->defaultDefinition();
		$resource = new RetrievalAgentTool(
			$this->resolver(),
			$definition,
			new PhoneticTextMaterializer($classMap, $definition),
			'retrieval'
		);

		$definitions = [];
		foreach($resource->getToolDefinitions() as $tool) {
			$name = $tool['function']['name'] ?? null;
			if(is_string($name)) {
				$definitions[$name] = $tool['function'];
			}
		}

		$this->assertStringContainsString(
			'explicitly requests semantic, lexical/BM25/full-text, phonetic, or exact search',
			$definitions['retrieval_search']['description']
		);
		$this->assertArrayNotHasKey(
			'phonetic_phrases',
			$definitions['retrieval_search']['parameters']['properties']
		);
		$this->assertStringContainsString(
			'In mode=phonetic each phrase is phonetic-normalized',
			$definitions['retrieval_search']['parameters']['properties']['phrases']['description']
		);
		$this->assertStringContainsString(
			'required_terms for required terms',
			$definitions['retrieval_search']['description']
		);
		$this->assertStringContainsString(
			'do not claim it was applied',
			$definitions['retrieval_search']['description']
		);
		$this->assertStringContainsString(
			'call retrieval_filter_help before searching',
			$definitions['retrieval_search']['description']
		);
		$this->assertStringContainsString(
			'factual content questions, explanations, passage discovery, and grounded summaries',
			$definitions['retrieval_search']['description']
		);
		$this->assertStringContainsString(
			'call retrieval_context with selected hits',
			$definitions['retrieval_search']['description']
		);
		$this->assertStringContainsString(
			'Never invent unavailable filter fields',
			$definitions['retrieval_search']['parameters']['properties']['filters']['description']
		);
		$this->assertStringContainsString(
			'use required_terms instead',
			$definitions['retrieval_search']['parameters']['properties']['phrases']['description']
		);
		$this->assertStringContainsString(
			'retrieval_ref value verbatim',
			$definitions['retrieval_context']['description']
		);
		$this->assertStringContainsString(
			'Do not derive or reconstruct',
			$definitions['retrieval_context']['parameters']['properties']['retrieval_ref']['description']
		);
		$this->assertStringContainsString(
			'content questions or producing summaries',
			$definitions['retrieval_context']['description']
		);
		$this->assertStringContainsString(
			'preceding chunks from the same content sequence',
			$definitions['retrieval_context']['parameters']['properties']['before']['description']
		);
		$this->assertStringContainsString(
			'following chunks from the same content sequence',
			$definitions['retrieval_context']['parameters']['properties']['after']['description']
		);
		$this->assertStringContainsString(
			'agent-approved metadata filters',
			$definitions['retrieval_filter_help']['description']
		);
		$this->assertFalse(
			$definitions['retrieval_filter_help']['parameters']['additionalProperties']
		);
		$this->assertInstanceOf(
			\stdClass::class,
			$definitions['retrieval_filter_help']['parameters']['properties']
		);
		$this->assertStringContainsString(
			'"properties":{}',
			json_encode($definitions['retrieval_filter_help']['parameters'], JSON_THROW_ON_ERROR)
		);
	}

	public function testRetrievalFilterHelpReturnsDomainOwnedDescriptionsAndExamples(): void {
		$classMap = $this->createStub(IClassMap::class);
		$definition = $this->defaultDefinition();
		$resource = new RetrievalAgentTool(
			$this->resolver(),
			$definition,
			new PhoneticTextMaterializer($classMap, $definition),
			'retrieval'
		);

		$help = $resource->callTool(
			'retrieval_filter_help',
			[],
			$this->createStub(IAgentContext::class)
		);

		$this->assertSame('default', $help['collection_key']);
		$this->assertCount(1, $help['filters']);
		$this->assertSame('content_uuid', $help['filters'][0]['field']);
		$this->assertSame('keyword', $help['filters'][0]['type']);
		$this->assertSame(['eq', 'in'], $help['filters'][0]['operators']);
		$this->assertStringContainsString('Stable identifier', $help['filters'][0]['description']);
		$this->assertSame([
			'field' => 'content_uuid',
			'operator' => 'eq',
			'value' => '0123456789abcdef0123456789abcdef'
		], $help['filters'][0]['examples'][0]);
		$this->assertNotEmpty($help['guidance']);
	}


	public function testPhoneticModeRoutesPhrasesToPhoneticPhraseConstraints(): void {
		$encoder = new RetrievalCompositionSchemaTestDouble1();

		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstanceByInterfaceName')
			->with(IPhoneticEncoder::class, 'testphoneticencoder')
			->willReturn($encoder);

		$definition = $this->createStub(IRetrievalCollectionDefinition::class);
		$definition->method('getBackendCollectionName')->with('default')->willReturn('content_v2');
		$definition->method('getPhoneticEncoderNames')->with('default', [])->willReturn(['testphoneticencoder']);
		$definition->method('getAgentFilterSchema')->with('default')->willReturn([]);

		$index = $this->createMock(IRetrievalIndex::class);
		$index->expects($this->once())
			->method('search')
			->with($this->callback(function(RetrievalSearchRequest $request): bool {
				$this->assertSame(RetrievalSearchRequest::MODE_PHONETIC, $request->mode);
				$this->assertSame([], $request->phrases);
				$this->assertSame(['phtestv1xa phtestv1xb'], $request->phoneticPhrases);
				return true;
			}))
			->willReturn(new RetrievalSearchResult([], ['phonetic']));

		$resource = new RetrievalAgentTool(
			$this->resolver(),
			$definition,
			new PhoneticTextMaterializer($classMap, $definition),
			'retrieval'
		);
		$context = $this->createStub(IAgentContext::class);
		$resource->init(['vectorstore' => [$index]], $context);

		$result = $resource->callTool('retrieval_search', [
			'query' => 'alpha beta',
			'mode' => 'phonetic',
			'phrases' => ['alpha beta']
		], $context);

		$this->assertSame(['phonetic'], $result['channels']);
	}

	public function testConfiguredLeafResourcesExposeEnabledServiceIds(): void {
		$settingsStore = $this->settingsStore();
		$classMap = $this->createStub(IClassMap::class);
		$resolver = $this->resolver();

		$runtimeResolver = new ConfiguredServiceRuntimeResolver($settingsStore, $classMap, $resolver);
		$stateStore = $this->createStub(IStateStore::class);
		$managedFileStorage = $this->createStub(IManagedFileStorageService::class);
		$chat = new ConfiguredChatModelAgentResource(
			$resolver, $settingsStore, $runtimeResolver,
			$stateStore, $managedFileStorage,
			new AiProviderFileReferenceStateRepository($stateStore), 'chat'
		);
		$embedding = new ConfiguredEmbeddingModelAgentResource($resolver, $settingsStore, $runtimeResolver, 'embedding');
		$vectorStore = new ConfiguredVectorStoreAgentResource($resolver, $settingsStore, $runtimeResolver, 'vector');

		$this->assertSame(['chat-main'], $chat->getSchema()['properties']['service']['enum']);
		$this->assertSame(['embedding-main'], $embedding->getSchema()['properties']['service']['enum']);
		$this->assertSame(['vector-main'], $vectorStore->getSchema()['properties']['service']['enum']);
		$this->assertSame(['service'], $chat->getSchema()['required']);
		$this->assertSame(['service'], $embedding->getSchema()['required']);
		$this->assertSame(['service'], $vectorStore->getSchema()['required']);
	}

	public function testRouterPublishesScalarConfigAndChatModelTargetDock(): void {
		$resource = new RoutingChatModelAgentResource($this->resolver(), 'router');
		$schema = $resource->getSchema();
		$docks = $this->docksByName($resource->getDockDefinitions());

		$this->assertSame(['failover', 'roundrobin'], $schema['properties']['strategy']['enum']);
		$this->assertSame(['global', 'per_op'], $schema['properties']['stickymode']['enum']);
		$this->assertSame(1, $schema['properties']['maxfailures']['minimum']);
		$this->assertSame(0, $schema['properties']['cooldownsec']['minimum']);
		$this->assertArrayNotHasKey('targets', $schema['properties']);
		$this->assertSame(IAiChatModel::class, $docks['targets']->interface);
		$this->assertTrue($docks['targets']->required);
		$this->assertSame(99, $docks['targets']->maxConnections);
	}

	public function testEmbeddingCacheRemainsComposableInFrontOfConfiguredEmbedding(): void {
		$database = $this->createStub(\Base3\Database\Api\IDatabase::class);
		$resource = new EmbeddingCacheAgentResource($database, $this->resolver(), 'cache');
		$docks = $this->docksByName($resource->getDockDefinitions());

		$this->assertSame(IAiEmbeddingModel::class, $docks['embedding']->interface);
		$this->assertTrue($docks['embedding']->required);
		$this->assertSame(1, $docks['embedding']->maxConnections);
		$this->assertArrayHasKey('table', $resource->getSchema()['properties']);
	}

	private function resolver(): IAgentConfigValueResolver {
		return new RetrievalCompositionSchemaTestDouble2();
	}

	private function settingsStore(): ISettingsStore {
		return new RetrievalCompositionSchemaTestDouble3();
	}

	/**
	 * @param array<int,\MissionBay\Agent\AgentNodeDock> $docks
	 * @return array<string,\MissionBay\Agent\AgentNodeDock>
	 */
	private function docksByName(array $docks): array {
		$result = [];

		foreach($docks as $dock) {
			$result[$dock->name] = $dock;
		}

		return $result;
	}
	private function defaultDefinition(): DefaultRetrievalCollectionDefinition {
		$repository = new RetrievalCompositionSchemaTestDouble4();

		return new DefaultRetrievalCollectionDefinition($repository);
	}

}

require_once __DIR__ . '/RetrievalCompositionSchemaTestDouble1.php';

require_once __DIR__ . '/RetrievalCompositionSchemaTestDouble2.php';

require_once __DIR__ . '/RetrievalCompositionSchemaTestDouble3.php';

require_once __DIR__ . '/RetrievalCompositionSchemaTestDouble4.php';

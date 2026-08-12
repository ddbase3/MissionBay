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
use MissionBay\Resource\ConfiguredChatModelAgentResource;
use MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use MissionBay\Resource\ConfiguredVectorStoreAgentResource;
use MissionBay\Resource\EmbeddingCacheAgentResource;
use MissionBay\Resource\RetrievalAgentTool;
use MissionBay\Resource\RoutingChatModelAgentResource;
use MissionBay\Retrieval\DefaultRetrievalCollectionDefinition;
use MissionBay\Retrieval\PhoneticTextMaterializer;
use PHPUnit\Framework\TestCase;

final class RetrievalCompositionSchemaTest extends TestCase {

	public function testRetrievalPublishesConfigSchemaAndComposableDocks(): void {
		$classMap = $this->createMock(IClassMap::class);
		$definition = new DefaultRetrievalCollectionDefinition();
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
		$classMap = $this->createMock(IClassMap::class);
		$definition = new DefaultRetrievalCollectionDefinition();
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
	}


	public function testPhoneticModeRoutesPhrasesToPhoneticPhraseConstraints(): void {
		$encoder = new class implements IPhoneticEncoder {
			public static function getName(): string {
				return 'testphoneticencoder';
			}

			public function getAlgorithm(): string {
				return 'test';
			}

			public function getVersion(): string {
				return 'v1';
			}

			public function encode(string $token): string {
				return mb_substr(mb_strtolower(trim($token)), 0, 1);
			}
		};

		$classMap = $this->createMock(IClassMap::class);
		$classMap->method('getInstanceByInterfaceName')
			->with(IPhoneticEncoder::class, 'testphoneticencoder')
			->willReturn($encoder);

		$definition = $this->createMock(IRetrievalCollectionDefinition::class);
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
		$context = $this->createMock(IAgentContext::class);
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
		$classMap = $this->createMock(IClassMap::class);
		$resolver = $this->resolver();

		$chat = new ConfiguredChatModelAgentResource($resolver, $settingsStore, $classMap, 'chat');
		$embedding = new ConfiguredEmbeddingModelAgentResource($resolver, $settingsStore, $classMap, 'embedding');
		$vectorStore = new ConfiguredVectorStoreAgentResource($resolver, $settingsStore, $classMap, 'vector');

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
		$database = $this->createMock(\Base3\Database\Api\IDatabase::class);
		$resource = new EmbeddingCacheAgentResource($database, $this->resolver(), 'cache');
		$docks = $this->docksByName($resource->getDockDefinitions());

		$this->assertSame(IAiEmbeddingModel::class, $docks['embedding']->interface);
		$this->assertTrue($docks['embedding']->required);
		$this->assertSame(1, $docks['embedding']->maxConnections);
		$this->assertArrayHasKey('table', $resource->getSchema()['properties']);
	}

	private function resolver(): IAgentConfigValueResolver {
		return new class implements IAgentConfigValueResolver {
			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				if(is_array($config) && ($config['mode'] ?? null) === 'fixed') {
					return $config['value'] ?? null;
				}

				return $config;
			}
		};
	}

	private function settingsStore(): ISettingsStore {
		return new class implements ISettingsStore {
			private array $groups = [
				'service-llm' => [
					'chat-main' => ['id' => 'chat-main', 'serviceType' => 'llm', 'enabled' => true],
					'chat-disabled' => ['id' => 'chat-disabled', 'serviceType' => 'llm', 'enabled' => false],
					'wrong-type' => ['id' => 'wrong-type', 'serviceType' => 'embedding', 'enabled' => true]
				],
				'service-embedding' => [
					'embedding-main' => ['id' => 'embedding-main', 'serviceType' => 'embedding', 'enabled' => true]
				],
				'service-vectorstore' => [
					'vector-main' => ['id' => 'vector-main', 'serviceType' => 'vectorstore', 'enabled' => true]
				]
			];

			public function get(string $group, string $name, array $default = []): array {
				return $this->groups[$group][$name] ?? $default;
			}

			public function set(string $group, string $name, array $settings): void {
				$this->groups[$group][$name] = $settings;
			}

			public function has(string $group, string $name): bool {
				return isset($this->groups[$group][$name]);
			}

			public function remove(string $group, string $name): void {
				unset($this->groups[$group][$name]);
			}

			public function getGroup(string $group): array {
				return $this->groups[$group] ?? [];
			}

			public function save(): void {}
			public function reload(): void {}
		};
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
}

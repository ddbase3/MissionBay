<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Service\AgentComponentFlowBuilder;
use MissionBay\Service\AgentComponentPresetFlowExpander;
use PHPUnit\Framework\TestCase;

final class AgentComponentFlowBuilderDeduplicationTest extends TestCase {

	public function testToolAndContextAttachmentsShareOnePresetResource(): void {
		$builder = $this->builder();
		$flow = $builder->build($this->baseFlow(), [[
			'preset' => 'user-prefs',
			'attach_as' => ['tool', 'context']
		]]);

		$baseResources = array_values(array_filter(
			$flow['resources'],
			static fn(array $resource): bool => str_starts_with((string)$resource['id'], 'preset_user_prefs')
		));
		$toolWrappers = array_values(array_filter(
			$flow['resources'],
			static fn(array $resource): bool => (string)($resource['type'] ?? '') === 'configuredagenttoolresource'
		));
		$memoryWrappers = array_values(array_filter(
			$flow['resources'],
			static fn(array $resource): bool => (string)($resource['type'] ?? '') === 'configuredagentmemoryresource'
		));

		$this->assertCount(1, $baseResources);
		$this->assertCount(1, $toolWrappers);
		$this->assertCount(0, $memoryWrappers);
		$this->assertSame('user_prefs', $toolWrappers[0]['config']['namespace'] ?? null);
		$this->assertSame($baseResources[0]['id'], $toolWrappers[0]['docks']['tool'][0]);
		$this->assertSame([$toolWrappers[0]['id']], $flow['nodes'][0]['docks']['tools']);
		$this->assertSame([$baseResources[0]['id']], $flow['nodes'][0]['docks']['contextcontributors']);
		$this->assertArrayNotHasKey('memory', $flow['nodes'][0]['docks']);
	}

	public function testExplicitToolNamespaceStillOverridesPresetId(): void {
		$builder = $this->builder();
		$flow = $builder->build($this->baseFlow(), [[
			'preset' => 'user-prefs',
			'attach_as' => ['tool'],
			'tool_config' => [
				'namespace' => 'prefs'
			]
		]]);

		$toolWrappers = array_values(array_filter(
			$flow['resources'],
			static fn(array $resource): bool => (string)($resource['type'] ?? '') === 'configuredagenttoolresource'
		));

		$this->assertCount(1, $toolWrappers);
		$this->assertSame('prefs', $toolWrappers[0]['config']['namespace'] ?? null);
	}

	public function testConversationMemoryUsesDedicatedMemoryWrapperOnly(): void {
		$builder = $this->builder();
		$flow = $builder->build($this->baseFlow(), [[
			'preset' => 'session-main',
			'attach_as' => ['memory']
		]]);

		$memoryWrappers = array_values(array_filter(
			$flow['resources'],
			static fn(array $resource): bool => (string)($resource['type'] ?? '') === 'configuredagentmemoryresource'
		));

		$this->assertCount(1, $memoryWrappers);
		$this->assertSame([$memoryWrappers[0]['id']], $flow['nodes'][0]['docks']['memory']);
		$this->assertArrayNotHasKey('contextcontributors', $flow['nodes'][0]['docks']);
	}


	public function testChatModelAttachmentMaterializesComposedRouterGraph(): void {
		$builder = $this->builder();
		$flow = $builder->build($this->baseFlow(), [[
			'preset' => 'chat-router',
			'attach_as' => ['chatmodel']
		]]);

		$resources = [];
		foreach($flow['resources'] as $resource) {
			$resources[(string)$resource['id']] = $resource;
		}

		$this->assertSame(['preset_chat_router'], $flow['nodes'][0]['docks']['chatmodel']);
		$this->assertSame('routingchatmodelagentresource', $resources['preset_chat_router']['type']);
		$this->assertSame(
			['preset_chat_primary', 'preset_chat_secondary'],
			$resources['preset_chat_router']['docks']['targets']
		);
		$this->assertSame('configuredchatmodelagentresource', $resources['preset_chat_primary']['type']);
		$this->assertSame('llm-primary', $resources['preset_chat_primary']['config']['service']['value']);
		$this->assertSame('configuredchatmodelagentresource', $resources['preset_chat_secondary']['type']);
		$this->assertSame('llm-secondary', $resources['preset_chat_secondary']['config']['service']['value']);
	}

	public function testRetrievalPresetMaterializesEmbeddingVectorStoreAndFilterDocks(): void {
		$builder = $this->builder();
		$flow = $builder->build($this->baseFlow(), [[
			'preset' => 'retrieval-main',
			'attach_as' => ['tool']
		]]);

		$resources = [];
		foreach($flow['resources'] as $resource) {
			$resources[(string)$resource['id']] = $resource;
		}

		$this->assertSame('retrievalagenttool', $resources['preset_retrieval_main']['type']);
		$this->assertSame(['preset_embedding_cache'], $resources['preset_retrieval_main']['docks']['embedding']);
		$this->assertSame(['preset_vector_main'], $resources['preset_retrieval_main']['docks']['vectorstore']);
		$this->assertSame(['preset_filter_kind'], $resources['preset_retrieval_main']['docks']['filters']);
		$this->assertSame(['preset_embedding_main'], $resources['preset_embedding_cache']['docks']['embedding']);
		$this->assertSame('configuredembeddingmodelagentresource', $resources['preset_embedding_main']['type']);
		$this->assertSame('configuredvectorstoreagentresource', $resources['preset_vector_main']['type']);
	}

	/** @return array<string,mixed> */
	private function baseFlow(): array {
		return [
			'nodes' => [[
				'id' => 'assistant',
				'type' => 'aiassistantnode'
			]],
			'resources' => [],
			'connections' => []
		];
	}

	private function repository(): IAgentComponentPresetRepository {
		return new AgentComponentFlowBuilderDeduplicationTestDouble1();
	}
	private function builder(): AgentComponentFlowBuilder {
		$repository = $this->repository();
		return new AgentComponentFlowBuilder($repository, new AgentComponentPresetFlowExpander($repository));
	}

}

require_once __DIR__ . '/AgentComponentFlowBuilderDeduplicationTestDouble1.php';

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
use PHPUnit\Framework\TestCase;

final class RetrievalCompositionSchemaTestDouble3 implements ISettingsStore  {
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
		}

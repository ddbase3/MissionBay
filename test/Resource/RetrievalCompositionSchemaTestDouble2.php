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

final class RetrievalCompositionSchemaTestDouble2 implements IAgentConfigValueResolver  {
			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				if(is_array($config) && ($config['mode'] ?? null) === 'fixed') {
					return $config['value'] ?? null;
				}

				return $config;
			}
		}

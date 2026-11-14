<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiEmbeddingModel;
use Base3\AssistantFoundation\Api\IPhoneticEncoder;
use Base3\AssistantFoundation\Api\IRetrievalCollectionDefinition;
use Base3\AssistantFoundation\Api\IRetrievalFilterProvider;
use Base3\AssistantFoundation\Api\IRetrievalIndex;
use Base3\AssistantFoundation\Dto\RetrievalSearchRequest;
use Base3\AssistantFoundation\Dto\RetrievalSearchResult;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IRetrievalCollectionConfigRepository;
use Base3\MissionBay\Resource\ConfiguredChatModelAgentResource;
use Base3\MissionBay\Resource\ConfiguredEmbeddingModelAgentResource;
use Base3\MissionBay\Resource\ConfiguredVectorStoreAgentResource;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use Base3\MissionBay\Resource\EmbeddingCacheAgentResource;
use Base3\MissionBay\Resource\RetrievalAgentTool;
use Base3\MissionBay\Resource\RoutingChatModelAgentResource;
use Base3\MissionBay\Retrieval\DefaultRetrievalCollectionDefinition;
use Base3\MissionBay\Retrieval\PhoneticTextMaterializer;
use PHPUnit\Framework\TestCase;

final class RetrievalCompositionSchemaTestDouble2 implements IAgentConfigValueResolver  {
			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				if(is_array($config) && ($config['mode'] ?? null) === 'fixed') {
					return $config['value'] ?? null;
				}

				return $config;
			}
		}

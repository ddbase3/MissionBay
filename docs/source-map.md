# MissionBay Source Map

## Purpose

This reference inventories the current PHP source files by package area. It is intended as a navigation aid and complements the thematic documentation.

## `(root)`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `MissionBayPlugin` | `missionbayplugin` | `src/MissionBayPlugin.php` |

## `Agent`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentConfigValueResolver` |  | `src/Agent/AgentConfigValueResolver.php` |
| `AgentContextFactory` |  | `src/Agent/AgentContextFactory.php` |
| `AgentFlowFactory` |  | `src/Agent/AgentFlowFactory.php` |
| `AgentKnowledgeService` |  | `src/Agent/AgentKnowledgeService.php` |
| `AgentNodeDock` |  | `src/Agent/AgentNodeDock.php` |
| `AgentNodeFactory` |  | `src/Agent/AgentNodeFactory.php` |
| `AgentNodePort` |  | `src/Agent/AgentNodePort.php` |
| `AgentResourceFactory` |  | `src/Agent/AgentResourceFactory.php` |
| `GlobalAgentKnowledgeService` |  | `src/Agent/GlobalAgentKnowledgeService.php` |

## `Ai`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentChatMessageAdapter` |  | `src/Ai/AgentChatMessageAdapter.php` |
| `AiProviderRequestEventDispatcher` |  | `src/Ai/AiProviderRequestEventDispatcher.php` |
| `AiResultNormalizer` |  | `src/Ai/AiResultNormalizer.php` |

## `Api`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `IAgent` |  | `src/Api/IAgent.php` |
| `IAgentAssistantContextContributionService` |  | `src/Api/IAgentAssistantContextContributionService.php` |
| `IAgentAssistantFallbackBuilder` |  | `src/Api/IAgentAssistantFallbackBuilder.php` |
| `IAgentAssistantFinalResponseService` |  | `src/Api/IAgentAssistantFinalResponseService.php` |
| `IAgentAssistantMemoryService` |  | `src/Api/IAgentAssistantMemoryService.php` |
| `IAgentAssistantMessageFactory` |  | `src/Api/IAgentAssistantMessageFactory.php` |
| `IAgentAssistantToolSetupFactory` |  | `src/Api/IAgentAssistantToolSetupFactory.php` |
| `IAgentAssistantTurnService` |  | `src/Api/IAgentAssistantTurnService.php` |
| `IAgentBatchTool` |  | `src/Api/IAgentBatchTool.php` |
| `IAgentChunker` |  | `src/Api/IAgentChunker.php` |
| `IAgentComponentFlowBuilder` |  | `src/Api/IAgentComponentFlowBuilder.php` |
| `IAgentComponentPresetCatalog` |  | `src/Api/IAgentComponentPresetCatalog.php` |
| `IAgentComponentPresetFlowExpander` |  | `src/Api/IAgentComponentPresetFlowExpander.php` |
| `IAgentComponentPresetInstaller` |  | `src/Api/IAgentComponentPresetInstaller.php` |
| `IAgentComponentPresetMaterializer` |  | `src/Api/IAgentComponentPresetMaterializer.php` |
| `IAgentComponentPresetRepository` |  | `src/Api/IAgentComponentPresetRepository.php` |
| `IAgentConfigValueResolver` |  | `src/Api/IAgentConfigValueResolver.php` |
| `IAgentContentExtractor` |  | `src/Api/IAgentContentExtractor.php` |
| `IAgentContentParser` |  | `src/Api/IAgentContentParser.php` |
| `IAgentContextFactory` |  | `src/Api/IAgentContextFactory.php` |
| `IAgentFlow` |  | `src/Api/IAgentFlow.php` |
| `IAgentFlowCompiler` |  | `src/Api/IAgentFlowCompiler.php` |
| `IAgentFlowFactory` |  | `src/Api/IAgentFlowFactory.php` |
| `IAgentInfoTopicProvider` |  | `src/Api/IAgentInfoTopicProvider.php` |
| `IAgentKnowledgeService` |  | `src/Api/IAgentKnowledgeService.php` |
| `IAgentMemoryRoleResolver` |  | `src/Api/IAgentMemoryRoleResolver.php` |
| `IAgentModelDecisionStrategy` |  | `src/Api/IAgentModelDecisionStrategy.php` |
| `IAgentModelDecisionStrategyResolver` |  | `src/Api/IAgentModelDecisionStrategyResolver.php` |
| `IAgentMutationGuardedTool` |  | `src/Api/IAgentMutationGuardedTool.php` |
| `IAgentNode` |  | `src/Api/IAgentNode.php` |
| `IAgentNodeFactory` |  | `src/Api/IAgentNodeFactory.php` |
| `IAgentProfileSelector` |  | `src/Api/IAgentProfileSelector.php` |
| `IAgentPromptProvider` |  | `src/Api/IAgentPromptProvider.php` |
| `IAgentResource` |  | `src/Api/IAgentResource.php` |
| `IAgentResourceFactory` |  | `src/Api/IAgentResourceFactory.php` |
| `IAgentResourceProvider` |  | `src/Api/IAgentResourceProvider.php` |
| `IAgentRouterFactory` |  | `src/Api/IAgentRouterFactory.php` |
| `IAgentStateContext` |  | `src/Api/IAgentStateContext.php` |
| `IAgentTool` |  | `src/Api/IAgentTool.php` |
| `IAgentVectorFilter` |  | `src/Api/IAgentVectorFilter.php` |
| `IConfiguredParserServiceResolver` |  | `src/Api/IConfiguredParserServiceResolver.php` |
| `IConfirmableAgentTool` |  | `src/Api/IConfirmableAgentTool.php` |
| `IEmbeddingOrchestratorConfigRepository` |  | `src/Api/IEmbeddingOrchestratorConfigRepository.php` |
| `IMcpAgent` |  | `src/Api/IMcpAgent.php` |
| `IMcpClient` |  | `src/Api/IMcpClient.php` |
| `IMcpClientFactory` |  | `src/Api/IMcpClientFactory.php` |
| `IMcpTransport` |  | `src/Api/IMcpTransport.php` |
| `IParserService` |  | `src/Api/IParserService.php` |
| `IParserServiceTestService` |  | `src/Api/IParserServiceTestService.php` |
| `IRetrievalCollectionConfigRepository` |  | `src/Api/IRetrievalCollectionConfigRepository.php` |
| `IRetrievalSearchService` |  | `src/Api/IRetrievalSearchService.php` |
| `ISearchService` |  | `src/Api/ISearchService.php` |
| `ISpeechToTextDriver` |  | `src/Api/ISpeechToTextDriver.php` |
| `ITextToSpeechDriver` |  | `src/Api/ITextToSpeechDriver.php` |
| `IVectorStoreService` |  | `src/Api/IVectorStoreService.php` |

## `Audit`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentToolAuditContext` |  | `src/Audit/AgentToolAuditContext.php` |

## `Cache`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentToolCacheKeyBuilder` |  | `src/Cache/AgentToolCacheKeyBuilder.php` |
| `NullAgentToolResultCache` |  | `src/Cache/NullAgentToolResultCache.php` |
| `StateStoreAgentToolResultCache` |  | `src/Cache/StateStoreAgentToolResultCache.php` |

## `Capability`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentCapabilityCatalogBuilder` |  | `src/Capability/AgentCapabilityCatalogBuilder.php` |
| `AgentCapabilityDiscoveryService` |  | `src/Capability/AgentCapabilityDiscoveryService.php` |
| `HybridAgentCapabilitySelector` |  | `src/Capability/HybridAgentCapabilitySelector.php` |
| `ProfileAwareAgentCapabilitySelector` |  | `src/Capability/ProfileAwareAgentCapabilitySelector.php` |
| `SemanticAgentCapabilitySelector` |  | `src/Capability/SemanticAgentCapabilitySelector.php` |

## `ChatModel`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AbstractChatCompletionModel` |  | `src/ChatModel/AbstractChatCompletionModel.php` |
| `MistralChatModel` | `mistralchatmodel` | `src/ChatModel/MistralChatModel.php` |
| `NormalizedChatModelTrait` |  | `src/ChatModel/NormalizedChatModelTrait.php` |
| `OpenAiChatModel` | `openaichatmodel` | `src/ChatModel/OpenAiChatModel.php` |
| `OpenAiCompatibleChatModel` | `openaicompatiblechatmodel` | `src/ChatModel/OpenAiCompatibleChatModel.php` |

## `Composition`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentCompositionInspector` |  | `src/Composition/AgentCompositionInspector.php` |

## `Connection`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `ConnectionConfig` |  | `src/Connection/ConnectionConfig.php` |

## `ConnectionDriver`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `HttpConnectionDriverDefinition` | `httpconnectiondriverdefinition` | `src/ConnectionDriver/HttpConnectionDriverDefinition.php` |

## `Content`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentConfigDisplay` | `agentconfigdisplay` | `src/Content/AgentConfigDisplay.php` |
| `AgentNodes` | `agentnodes` | `src/Content/AgentNodes.php` |
| `McpServerTest` | `mcpservertest` | `src/Content/McpServerTest.php` |

## `Context`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentContext` | `agentcontext` | `src/Context/AgentContext.php` |
| `MissionBayContextProfileProvider` | `missionbaycontextprofileprovider` | `src/Context/Profile/MissionBayContextProfileProvider.php` |
| `SubFlowContext` | `subflowcontext` | `src/Context/SubFlowContext.php` |

## `Display`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AbstractServiceConfigDisplay` |  | `src/Display/AbstractServiceConfigDisplay.php` |
| `AgentAdminDisplay` | `agentadmindisplay` | `src/Display/AgentAdminDisplay.php` |
| `AgentComponentPresetAdminDisplay` | `agentcomponentpresetadmindisplay` | `src/Display/AgentComponentPresetAdminDisplay.php` |
| `AgentComponentPresetTestAdminDisplay` | `agentcomponentpresettestadmindisplay` | `src/Display/AgentComponentPresetTestAdminDisplay.php` |
| `AgentCompositionAdminDisplay` | `agentcompositionadmindisplay` | `src/Display/AgentCompositionAdminDisplay.php` |
| `AgentContextProfileAdminDisplay` | `agentcontextprofileadmindisplay` | `src/Display/AgentContextProfileAdminDisplay.php` |
| `AgentInfoTopicProviderTestAdminDisplay` | `agentinfotopicprovidertestadmindisplay` | `src/Display/AgentInfoTopicProviderTestAdminDisplay.php` |
| `AgentMemoryProfileAdminDisplay` | `agentmemoryprofileadmindisplay` | `src/Display/AgentMemoryProfileAdminDisplay.php` |
| `AgentOrchestratorProfileAdminDisplay` | `agentorchestratorprofileadmindisplay` | `src/Display/AgentOrchestratorProfileAdminDisplay.php` |
| `AgentToolLogAdminDisplay` | `agenttoollogadmindisplay` | `src/Display/AgentToolLogAdminDisplay.php` |
| `ConnectionConfigDisplay` | `connectionconfigdisplay` | `src/Display/ConnectionConfigDisplay.php` |
| `EmbeddingConfigDisplay` | `embeddingconfigdisplay` | `src/Display/EmbeddingConfigDisplay.php` |
| `EmbeddingOrchestratorConfigAdminDisplay` | `embeddingorchestratorconfigadmindisplay` | `src/Display/EmbeddingOrchestratorConfigAdminDisplay.php` |
| `ImageConfigDisplay` | `imageconfigdisplay` | `src/Display/ImageConfigDisplay.php` |
| `KnowledgeAgentMemoryAdminDisplay` | `knowledgeagentmemoryadmindisplay` | `src/Display/KnowledgeAgentMemoryAdminDisplay.php` |
| `LlmConfigDisplay` | `llmconfigdisplay` | `src/Display/LlmConfigDisplay.php` |
| `ParserServiceConfigDisplay` | `parserserviceconfigdisplay` | `src/Display/ParserServiceConfigDisplay.php` |
| `RetrievalCollectionAdminDisplay` | `retrievalcollectionadmindisplay` | `src/Display/RetrievalCollectionAdminDisplay.php` |
| `RetrievalSearchAdminDisplay` | `retrievalsearchadmindisplay` | `src/Display/RetrievalSearchAdminDisplay.php` |
| `RetrievalVectorPointsAdminDisplay` | `retrievalvectorpointsadmindisplay` | `src/Display/RetrievalVectorPointsAdminDisplay.php` |
| `SearchConfigDisplay` | `searchconfigdisplay` | `src/Display/SearchConfigDisplay.php` |
| `SpeechToTextConfigDisplay` | `speechtotextconfigdisplay` | `src/Display/SpeechToTextConfigDisplay.php` |
| `TextToSpeechConfigDisplay` | `texttospeechconfigdisplay` | `src/Display/TextToSpeechConfigDisplay.php` |
| `ToolProfileAdminDisplay` | `toolprofileadmindisplay` | `src/Display/ToolProfileAdminDisplay.php` |
| `UserPrefDefAdminDisplay` | `userprefdefadmindisplay` | `src/Display/UserPrefDefAdminDisplay.php` |
| `VectorSearchConfigDisplay` | `vectorsearchconfigdisplay` | `src/Display/VectorSearchConfigDisplay.php` |
| `VectorStoreConfigDisplay` | `vectorstoreconfigdisplay` | `src/Display/VectorStoreConfigDisplay.php` |

## `Dto`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentComponentPresetMaterialization` |  | `src/Dto/AgentComponentPresetMaterialization.php` |
| `AgentContentItem` |  | `src/Dto/AgentContentItem.php` |
| `AgentFlowCompilation` |  | `src/Dto/AgentFlowCompilation.php` |
| `AgentInfoRequest` |  | `src/Dto/AgentInfoRequest.php` |
| `AgentInfoResult` |  | `src/Dto/AgentInfoResult.php` |
| `AgentParsedContent` |  | `src/Dto/AgentParsedContent.php` |
| `AgentAssistantToolSetup` |  | `src/Dto/Assistant/AgentAssistantToolSetup.php` |
| `AgentAssistantTurnOptions` |  | `src/Dto/Assistant/AgentAssistantTurnOptions.php` |
| `AgentAssistantTurnResources` |  | `src/Dto/Assistant/AgentAssistantTurnResources.php` |
| `AgentAssistantTurnResult` |  | `src/Dto/Assistant/AgentAssistantTurnResult.php` |
| `AgentCapabilityDiscoveryResult` |  | `src/Dto/Assistant/AgentCapabilityDiscoveryResult.php` |
| `AgentExecutionLedger` |  | `src/Dto/Assistant/AgentExecutionLedger.php` |
| `AgentInteractionResolution` |  | `src/Dto/Assistant/AgentInteractionResolution.php` |
| `PreparedAgentResume` |  | `src/Dto/Assistant/PreparedAgentResume.php` |
| `McpClientConfig` |  | `src/Dto/Mcp/McpClientConfig.php` |
| `McpHttpRequest` |  | `src/Dto/Mcp/McpHttpRequest.php` |
| `McpHttpResponse` |  | `src/Dto/Mcp/McpHttpResponse.php` |
| `McpProfileAuthorizationResult` |  | `src/Dto/Mcp/McpProfileAuthorizationResult.php` |
| `AgentModelDecisionAssessment` |  | `src/Dto/Orchestrator/AgentModelDecisionAssessment.php` |
| `AgentModelDecisionConfig` |  | `src/Dto/Orchestrator/AgentModelDecisionConfig.php` |

## `EmbeddingModel`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AbstractEmbeddingModel` |  | `src/EmbeddingModel/AbstractEmbeddingModel.php` |
| `DefaultEmbeddingResultTrait` |  | `src/EmbeddingModel/DefaultEmbeddingResultTrait.php` |
| `OpenAiCompatibleEmbeddingModel` | `openaicompatibleembeddingmodel` | `src/EmbeddingModel/OpenAiCompatibleEmbeddingModel.php` |
| `OpenAiEmbeddingModel` | `openaiembeddingmodel` | `src/EmbeddingModel/OpenAiEmbeddingModel.php` |

## `Event`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `MissionBayAgentActionAuditEvent` |  | `src/Event/MissionBayAgentActionAuditEvent.php` |
| `MissionBayToolFailedEvent` |  | `src/Event/MissionBayToolFailedEvent.php` |
| `MissionBayToolFinishedEvent` |  | `src/Event/MissionBayToolFinishedEvent.php` |
| `MissionBayToolStartedEvent` |  | `src/Event/MissionBayToolStartedEvent.php` |

## `Example`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `TimeNowAgent` | `timenowagent` | `src/Example/TimeNowAgent.php` |

## `Flow`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AbstractFlow` |  | `src/Flow/AbstractFlow.php` |
| `DynamicAiFlow` | `dynamicaiflow` | `src/Flow/DynamicAiFlow.php` |
| `StrictFlow` | `strictflow` | `src/Flow/StrictFlow.php` |

## `Hook`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `MissionBayAiUsageEventRegistrationHookListener` |  | `src/Hook/MissionBayAiUsageEventRegistrationHookListener.php` |
| `MissionBayToolEventRegistrationHookListener` |  | `src/Hook/MissionBayToolEventRegistrationHookListener.php` |

## `ImageModel`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AbstractImageGenerationModel` |  | `src/ImageModel/AbstractImageGenerationModel.php` |
| `MistralImageModel` | `mistralimagemodel` | `src/ImageModel/MistralImageModel.php` |
| `OpenAiCompatibleImageModel` | `openaicompatibleimagemodel` | `src/ImageModel/OpenAiCompatibleImageModel.php` |
| `OpenAiImageModel` | `openaiimagemodel` | `src/ImageModel/OpenAiImageModel.php` |

## `InfoProvider`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `StaticDemoInfoTopicProvider` | `staticdemoinfotopicprovider` | `src/InfoProvider/StaticDemoInfoTopicProvider.php` |
| `SystemInfoTopicProvider` | `systeminfotopicprovider` | `src/InfoProvider/SystemInfoTopicProvider.php` |

## `Job`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `ScheduledAgentRunnerJob` | `scheduledagentrunnerjob` | `src/Job/ScheduledAgentRunnerJob.php` |

## `Listener`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `MissionBayAiUsageLogListener` |  | `src/Listener/MissionBayAiUsageLogListener.php` |
| `MissionBayToolEventDisplayListener` |  | `src/Listener/MissionBayToolEventDisplayListener.php` |

## `Mcp`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `McpClient` |  | `src/Mcp/Client/McpClient.php` |
| `McpClientException` |  | `src/Mcp/Client/McpClientException.php` |
| `McpClientFactory` | `mcpclientfactory` | `src/Mcp/Client/McpClientFactory.php` |
| `McpHmacRequestSigner` |  | `src/Mcp/Client/McpHmacRequestSigner.php` |
| `McpRemoteToolDefinitionMapper` |  | `src/Mcp/Client/McpRemoteToolDefinitionMapper.php` |
| `McpRemoteToolResultMapper` |  | `src/Mcp/Client/McpRemoteToolResultMapper.php` |
| `McpStreamableHttpTransport` | `mcpstreamablehttptransport` | `src/Mcp/Client/McpStreamableHttpTransport.php` |
| `Mcp` | `mcp` | `src/Mcp/Mcp.php` |
| `McpBearerAuthenticator` | `mcpbearerauthenticator` | `src/Mcp/McpBearerAuthenticator.php` |
| `McpHostProviderRegistry` | `mcphostproviderregistry` | `src/Mcp/McpHostProviderRegistry.php` |
| `McpHttpGuard` | `mcphttpguard` | `src/Mcp/McpHttpGuard.php` |
| `McpJsonRpcHandler` | `mcpjsonrpchandler` | `src/Mcp/McpJsonRpcHandler.php` |
| `McpProfileAuthorizer` |  | `src/Mcp/McpProfileAuthorizer.php` |
| `McpProfileCredentialServiceProvider` | `mcpprofilecredentialserviceprovider` | `src/Mcp/McpProfileCredentialServiceProvider.php` |
| `McpProfileResourceProvider` | `mcpprofileresourceprovider` | `src/Mcp/McpProfileResourceProvider.php` |
| `McpPromptCatalog` | `mcppromptcatalog` | `src/Mcp/McpPromptCatalog.php` |
| `McpResourceCatalog` | `mcpresourcecatalog` | `src/Mcp/McpResourceCatalog.php` |
| `McpToolCatalog` | `mcptoolcatalog` | `src/Mcp/McpToolCatalog.php` |
| `McpToolDefinitionMapper` | `mcptooldefinitionmapper` | `src/Mcp/McpToolDefinitionMapper.php` |
| `McpToolPresetMaterializer` | `mcptoolpresetmaterializer` | `src/Mcp/McpToolPresetMaterializer.php` |
| `McpToolProfileRepository` | `mcptoolprofilerepository` | `src/Mcp/McpToolProfileRepository.php` |
| `McpToolResultMapper` | `mcptoolresultmapper` | `src/Mcp/McpToolResultMapper.php` |

## `Memory`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `NoMemory` | `nomemory` | `src/Memory/NoMemory.php` |

## `Node`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AbstractAgentNode` |  | `src/Node/AbstractAgentNode.php` |
| `AbstractAiAssistantNode` |  | `src/Node/Ai/AbstractAiAssistantNode.php` |
| `AiAssistantNode` | `aiassistantnode` | `src/Node/Ai/AiAssistantNode.php` |
| `AiEmbedTextNode` | `aiembedtextnode` | `src/Node/Ai/AiEmbedTextNode.php` |
| `AiIndexingNode` | `aiindexingnode` | `src/Node/Ai/AiIndexingNode.php` |
| `DeepLTranslateNode` | `deepltranslatenode` | `src/Node/Ai/DeepLTranslateNode.php` |
| `OpenAiResponseNode` | `openairesponsenode` | `src/Node/Ai/OpenAiResponseNode.php` |
| `SimpleLlamaNode` | `simplellamanode` | `src/Node/Ai/SimpleLlamaNode.php` |
| `SimpleOpenAiNode` | `simpleopenainode` | `src/Node/Ai/SimpleOpenAiNode.php` |
| `ConditionalPassNode` | `conditionalpassnode` | `src/Node/Control/ConditionalPassNode.php` |
| `DelayNode` | `delaynode` | `src/Node/Control/DelayNode.php` |
| `ForEachNode` | `foreachnode` | `src/Node/Control/ForEachNode.php` |
| `IfNode` | `ifnode` | `src/Node/Control/IfNode.php` |
| `LoopNode` | `loopnode` | `src/Node/Control/LoopNode.php` |
| `NoActionNode` | `noactionnode` | `src/Node/Control/NoActionNode.php` |
| `SubFlowNode` | `subflownode` | `src/Node/Control/SubFlowNode.php` |
| `SwitchNode` | `switchnode` | `src/Node/Control/SwitchNode.php` |
| `GetConfigurationNode` | `getconfigurationnode` | `src/Node/Core/GetConfigurationNode.php` |
| `GetContextVarNode` | `getcontextvarnode` | `src/Node/Core/GetContextVarNode.php` |
| `SetContextVarNode` | `setcontextvarnode` | `src/Node/Core/SetContextVarNode.php` |
| `TestInputNode` | `testinputnode` | `src/Node/Core/TestInputNode.php` |
| `ArrayGetNode` | `arraygetnode` | `src/Node/Data/ArrayGetNode.php` |
| `ArraySetNode` | `arraysetnode` | `src/Node/Data/ArraySetNode.php` |
| `JsonToArrayNode` | `jsontoarraynode` | `src/Node/Data/JsonToArrayNode.php` |
| `TryArrayGetNode` | `tryarraygetnode` | `src/Node/Data/TryArrayGetNode.php` |
| `HttpGetNode` | `httpgetnode` | `src/Node/Http/HttpGetNode.php` |
| `HttpRequestNode` | `httprequestnode` | `src/Node/Http/HttpRequestNode.php` |
| `LoggerNode` | `loggernode` | `src/Node/Message/LoggerNode.php` |
| `StaticMessageNode` | `staticmessagenode` | `src/Node/Message/StaticMessageNode.php` |
| `StringReverserNode` | `stringreversernode` | `src/Node/Message/StringReverserNode.php` |
| `TelegramSendMessage` | `telegramsendmessage` | `src/Node/Message/TelegramSendMessage.php` |

## `Orchestrator`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentActionFingerprint` |  | `src/Orchestrator/AgentActionFingerprint.php` |
| `AgentStagePipelineResolver` |  | `src/Orchestrator/AgentStagePipelineResolver.php` |
| `AgentStageResultAccumulator` |  | `src/Orchestrator/AgentStageResultAccumulator.php` |
| `AgentStateSynchronizer` |  | `src/Orchestrator/AgentStateSynchronizer.php` |
| `AgentToolOrchestrator` |  | `src/Orchestrator/AgentToolOrchestrator.php` |
| `AgentToolOrchestratorResult` |  | `src/Orchestrator/AgentToolOrchestratorResult.php` |
| `AbstractAgentModelDecisionStrategy` |  | `src/Orchestrator/Decision/AbstractAgentModelDecisionStrategy.php` |
| `AgentModelDecisionStrategyResolver` |  | `src/Orchestrator/Decision/AgentModelDecisionStrategyResolver.php` |
| `AiGuardedAgentModelDecisionStrategy` |  | `src/Orchestrator/Decision/AiGuardedAgentModelDecisionStrategy.php` |
| `NativeAgentModelDecisionStrategy` |  | `src/Orchestrator/Decision/NativeAgentModelDecisionStrategy.php` |
| `SimpleAgentModelDecisionStrategy` |  | `src/Orchestrator/Decision/SimpleAgentModelDecisionStrategy.php` |
| `ComponentAgentActionPolicyResolver` |  | `src/Orchestrator/Policy/ComponentAgentActionPolicyResolver.php` |
| `IAgentActionPolicyResolver` |  | `src/Orchestrator/Policy/IAgentActionPolicyResolver.php` |
| `StaticAgentActionPolicyResolver` |  | `src/Orchestrator/Policy/StaticAgentActionPolicyResolver.php` |
| `AgentOrchestratorProfile` |  | `src/Orchestrator/Profile/AgentOrchestratorProfile.php` |
| `AgentOrchestratorProfileRepository` |  | `src/Orchestrator/Profile/AgentOrchestratorProfileRepository.php` |
| `AgentActionResumeService` |  | `src/Orchestrator/Service/AgentActionResumeService.php` |
| `AgentActionReviewService` |  | `src/Orchestrator/Service/AgentActionReviewService.php` |
| `AgentBatchExecutionService` |  | `src/Orchestrator/Service/AgentBatchExecutionService.php` |
| `AgentBatchResultService` |  | `src/Orchestrator/Service/AgentBatchResultService.php` |
| `AgentBudgetGuardService` |  | `src/Orchestrator/Service/AgentBudgetGuardService.php` |
| `AgentCapabilitySelectionGuardService` |  | `src/Orchestrator/Service/AgentCapabilitySelectionGuardService.php` |
| `AgentContextAssessmentService` |  | `src/Orchestrator/Service/AgentContextAssessmentService.php` |
| `AgentContinuationDecisionService` |  | `src/Orchestrator/Service/AgentContinuationDecisionService.php` |
| `AgentInteractionResponseResolver` |  | `src/Orchestrator/Service/AgentInteractionResponseResolver.php` |
| `AgentLoopProgressService` |  | `src/Orchestrator/Service/AgentLoopProgressService.php` |
| `AgentMutationCommitGuardService` |  | `src/Orchestrator/Service/AgentMutationCommitGuardService.php` |
| `AgentResultVerificationService` |  | `src/Orchestrator/Service/AgentResultVerificationService.php` |
| `AgentSemanticVerificationService` |  | `src/Orchestrator/Service/AgentSemanticVerificationService.php` |
| `AgentToolContractValidationService` |  | `src/Orchestrator/Service/AgentToolContractValidationService.php` |
| `AgentToolDefinitionSemantics` |  | `src/Orchestrator/Service/AgentToolDefinitionSemantics.php` |
| `AgentToolResultCacheService` |  | `src/Orchestrator/Service/AgentToolResultCacheService.php` |
| `AbstractAgentCapabilitySelectionStage` |  | `src/Orchestrator/Stage/AbstractAgentCapabilitySelectionStage.php` |
| `AgentActionPolicyStage` | `agentactionpolicystage` | `src/Orchestrator/Stage/AgentActionPolicyStage.php` |
| `AgentActionResumeStage` | `agentactionresumestage` | `src/Orchestrator/Stage/AgentActionResumeStage.php` |
| `AgentActionReviewStage` | `agentactionreviewstage` | `src/Orchestrator/Stage/AgentActionReviewStage.php` |
| `AgentAiCapabilitySelectionStage` | `agentaicapabilityselectionstage` | `src/Orchestrator/Stage/AgentAiCapabilitySelectionStage.php` |
| `AgentBudgetGuardStage` | `agentbudgetguardstage` | `src/Orchestrator/Stage/AgentBudgetGuardStage.php` |
| `AgentCapabilityDiscoveryStage` | `agentcapabilitydiscoverystage` | `src/Orchestrator/Stage/AgentCapabilityDiscoveryStage.php` |
| `AgentCapabilitySelectionStage` | `agentcapabilityselectionstage` | `src/Orchestrator/Stage/AgentCapabilitySelectionStage.php` |
| `AgentContextAssessmentStage` | `agentcontextassessmentstage` | `src/Orchestrator/Stage/AgentContextAssessmentStage.php` |
| `AgentContextCompactionStage` | `agentcontextcompactionstage` | `src/Orchestrator/Stage/AgentContextCompactionStage.php` |
| `AgentContinuationDecisionStage` | `agentcontinuationdecisionstage` | `src/Orchestrator/Stage/AgentContinuationDecisionStage.php` |
| `AgentFinalAnswerStage` | `agentfinalanswerstage` | `src/Orchestrator/Stage/AgentFinalAnswerStage.php` |
| `AgentLoopProgressStage` | `agentloopprogressstage` | `src/Orchestrator/Stage/AgentLoopProgressStage.php` |
| `AgentModelDecisionStage` | `agentmodeldecisionstage` | `src/Orchestrator/Stage/AgentModelDecisionStage.php` |
| `AgentResultVerificationStage` | `agentresultverificationstage` | `src/Orchestrator/Stage/AgentResultVerificationStage.php` |
| `AgentSemanticVerificationStage` | `agentsemanticverificationstage` | `src/Orchestrator/Stage/AgentSemanticVerificationStage.php` |
| `AgentToolExecutionStage` | `agenttoolexecutionstage` | `src/Orchestrator/Stage/AgentToolExecutionStage.php` |
| `AgentToolLoopContextKeys` |  | `src/Orchestrator/Stage/AgentToolLoopContextKeys.php` |
| `AgentToolObservationStage` | `agenttoolobservationstage` | `src/Orchestrator/Stage/AgentToolObservationStage.php` |
| `AgentToolResultCacheStage` | `agenttoolresultcachestage` | `src/Orchestrator/Stage/AgentToolResultCacheStage.php` |
| `UnavailableAgentSuspensionRepository` |  | `src/Orchestrator/Suspension/UnavailableAgentSuspensionRepository.php` |
| `JsonSchemaValidator` |  | `src/Orchestrator/Validation/JsonSchemaValidator.php` |

## `ParserService`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AbstractParserService` |  | `src/ParserService/AbstractParserService.php` |
| `ConfiguredParserServiceResolver` |  | `src/ParserService/ConfiguredParserServiceResolver.php` |
| `DoclingParserService` | `doclingparserservice` | `src/ParserService/DoclingParserService.php` |
| `ParserServiceTestService` |  | `src/ParserService/ParserServiceTestService.php` |
| `UnstructuredParserService` | `unstructuredparserservice` | `src/ParserService/UnstructuredParserService.php` |

## `Policy`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AllowAllAgentActionPolicy` | `allowallagentactionpolicy` | `src/Policy/AllowAllAgentActionPolicy.php` |
| `MutationApprovalAgentActionPolicy` | `mutationapprovalagentactionpolicy` | `src/Policy/MutationApprovalAgentActionPolicy.php` |

## `Profile`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentAssistantToolSetupFactory` |  | `src/Profile/AgentAssistantToolSetupFactory.php` |
| `AgentContextProfileResolver` |  | `src/Profile/AgentContextProfileResolver.php` |
| `AgentMemoryProfileResolver` |  | `src/Profile/AgentMemoryProfileResolver.php` |
| `AgentToolProfileResolver` |  | `src/Profile/AgentToolProfileResolver.php` |
| `NoOpProfileSelector` | `noopprofileselector` | `src/Profile/NoOpProfileSelector.php` |
| `ProfilePlan` |  | `src/Profile/ProfilePlan.php` |
| `ToolDefFilter` |  | `src/Profile/ToolDefFilter.php` |
| `ToolFilterReport` |  | `src/Profile/ToolFilterReport.php` |
| `ToolGuardAgentTool` |  | `src/Profile/ToolGuardAgentTool.php` |

## `Resource`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AbstractAgentResource` |  | `src/Resource/AbstractAgentResource.php` |
| `AbstractConfiguredServiceAgentResource` |  | `src/Resource/AbstractConfiguredServiceAgentResource.php` |
| `StaticTextContextAgentResource` | `statictextcontextagentresource` | `src/Resource/AgentContext/Text/StaticTextContextAgentResource.php` |
| `TimeMemoryAgentResource` | `timememoryagentresource` | `src/Resource/AgentMemory/Time/TimeMemoryAgentResource.php` |
| `RunAvailableAgentTool` | `runavailableagenttool` | `src/Resource/AgentTool/Agent/RunAvailableAgentTool.php` |
| `RunConfiguredAgentTool` | `runconfiguredagenttool` | `src/Resource/AgentTool/Agent/RunConfiguredAgentTool.php` |
| `BatchAgentTool` | `batchagenttool` | `src/Resource/AgentTool/Batch/BatchAgentTool.php` |
| `CurrencyConvertAgentTool` | `currencyconvertagenttool` | `src/Resource/AgentTool/CurrencyConvert/CurrencyConvertAgentTool.php` |
| `CurrentTimeAgentTool` | `currenttimeagenttool` | `src/Resource/AgentTool/CurrentTime/CurrentTimeAgentTool.php` |
| `LogAgentTool` | `logagenttool` | `src/Resource/AgentTool/Log/LogAgentTool.php` |
| `SystemStatusAgentTool` | `systemstatusagenttool` | `src/Resource/AgentTool/SystemStatus/SystemStatusAgentTool.php` |
| `WeatherAgentTool` | `weatheragenttool` | `src/Resource/AgentTool/Weather/WeatherAgentTool.php` |
| `WebFetchTextAgentTool` | `webfetchtextagenttool` | `src/Resource/AgentTool/WebFetchText/WebFetchTextAgentTool.php` |
| `AnthropicChatModelAgentResource` | `anthropicchatmodelagentresource` | `src/Resource/AnthropicChatModelAgentResource.php` |
| `BlockChatbotAgentTool` | `blockchatbotagenttool` | `src/Resource/BlockChatbotAgentTool.php` |
| `CanvasCloseAgentTool` | `canvascloseagenttool` | `src/Resource/CanvasCloseAgentTool.php` |
| `ConfiguredAgentMemoryResource` | `configuredagentmemoryresource` | `src/Resource/ConfiguredAgentMemoryResource.php` |
| `ConfiguredAgentToolResource` | `configuredagenttoolresource` | `src/Resource/ConfiguredAgentToolResource.php` |
| `ConfiguredChatModelAgentResource` | `configuredchatmodelagentresource` | `src/Resource/ConfiguredChatModelAgentResource.php` |
| `ConfiguredEmbeddingModelAgentResource` | `configuredembeddingmodelagentresource` | `src/Resource/ConfiguredEmbeddingModelAgentResource.php` |
| `ConfiguredImageModelAgentResource` | `configuredimagemodelagentresource` | `src/Resource/ConfiguredImageModelAgentResource.php` |
| `ConfiguredParserServiceAgentResource` | `configuredparserserviceagentresource` | `src/Resource/ConfiguredParserServiceAgentResource.php` |
| `ConfiguredSearchServiceAgentResource` | `configuredsearchserviceagentresource` | `src/Resource/ConfiguredSearchServiceAgentResource.php` |
| `ConfiguredVectorSearchAgentResource` | `configuredvectorsearchagentresource` | `src/Resource/ConfiguredVectorSearchAgentResource.php` |
| `ConfiguredVectorStoreAgentResource` | `configuredvectorstoreagentresource` | `src/Resource/ConfiguredVectorStoreAgentResource.php` |
| `CrmProductXrmExtractorAgentResource` | `crmproductxrmextractoragentresource` | `src/Resource/CrmProductXrmExtractorAgentResource.php` |
| `DatabaseMemoryAgentResource` | `databasememoryagentresource` | `src/Resource/DatabaseMemoryAgentResource.php` |
| `DeepSeekChatModelAgentResource` | `deepseekchatmodelagentresource` | `src/Resource/DeepSeekChatModelAgentResource.php` |
| `DummyEmbeddingModelAgentResource` | `dummyembeddingmodelagentresource` | `src/Resource/DummyEmbeddingModelAgentResource.php` |
| `DummyExtractorAgentResource` | `dummyextractoragentresource` | `src/Resource/DummyExtractorAgentResource.php` |
| `EmbeddingCacheAgentResource` | `embeddingcacheagentresource` | `src/Resource/EmbeddingCacheAgentResource.php` |
| `FireworksChatModelAgentResource` | `fireworkschatmodelagentresource` | `src/Resource/FireworksChatModelAgentResource.php` |
| `FocusAgentResource` | `focusagentresource` | `src/Resource/FocusAgentResource.php` |
| `GeminiChatModelAgentResource` | `geminichatmodelagentresource` | `src/Resource/GeminiChatModelAgentResource.php` |
| `GeneralInfoAgentTool` | `generalinfoagenttool` | `src/Resource/GeneralInfoAgentTool.php` |
| `GenericChatModelAgentResource` | `genericchatmodelagentresource` | `src/Resource/GenericChatModelAgentResource.php` |
| `GrokChatModelAgentResource` | `grokchatmodelagentresource` | `src/Resource/GrokChatModelAgentResource.php` |
| `GroqChatModelAgentResource` | `groqchatmodelagentresource` | `src/Resource/GroqChatModelAgentResource.php` |
| `HelloWorldCanvasAgentTool` | `helloworldcanvasagenttool` | `src/Resource/HelloWorldCanvasAgentTool.php` |
| `KnowledgeAgentResource` | `knowledgeagentresource` | `src/Resource/KnowledgeAgentResource.php` |
| `LoggerResource` | `loggerresource` | `src/Resource/Logger/Logger/LoggerResource.php` |
| `McpClientAgentResource` | `mcpclientagentresource` | `src/Resource/Mcp/McpClientAgentResource.php` |
| `MermaidSyntaxAgentTool` | `mermaidsyntaxagenttool` | `src/Resource/MermaidSyntaxAgentTool.php` |
| `MistralChatModelAgentResource` | `mistralchatmodelagentresource` | `src/Resource/MistralChatModelAgentResource.php` |
| `NoChunkerAgentResource` | `nochunkeragentresource` | `src/Resource/NoChunkerAgentResource.php` |
| `NoEmbeddingModelAgentResource` | `noembeddingmodelagentresource` | `src/Resource/NoEmbeddingModelAgentResource.php` |
| `NoParserAgentResource` | `noparseragentresource` | `src/Resource/NoParserAgentResource.php` |
| `OpenAiChatModelAgentResource` | `openaichatmodelagentresource` | `src/Resource/OpenAiChatModelAgentResource.php` |
| `OpenAiEmbeddingModelAgentResource` | `openaiembeddingmodelagentresource` | `src/Resource/OpenAiEmbeddingModelAgentResource.php` |
| `OpenRouterChatModelAgentResource` | `openrouterchatmodelagentresource` | `src/Resource/OpenRouterChatModelAgentResource.php` |
| `PerplexityChatModelAgentResource` | `perplexitychatmodelagentresource` | `src/Resource/PerplexityChatModelAgentResource.php` |
| `ProductXrmExtractorAgentResource` | `productxrmextractoragentresource` | `src/Resource/ProductXrmExtractorAgentResource.php` |
| `QdrantVectorSearch` | `qdrantvectorsearch` | `src/Resource/QdrantVectorSearch.php` |
| `RagSearchAgentTool` | `ragsearchagenttool` | `src/Resource/RagSearchAgentTool.php` |
| `RetrievalAgentTool` | `retrievalagenttool` | `src/Resource/RetrievalAgentTool.php` |
| `RoutingChatModelAgentResource` | `routingchatmodelagentresource` | `src/Resource/RoutingChatModelAgentResource.php` |
| `SemanticChunkerAgentResource` | `semanticchunkeragentresource` | `src/Resource/SemanticChunkerAgentResource.php` |
| `SessionMemoryAgentResource` | `sessionmemoryagentresource` | `src/Resource/SessionMemoryAgentResource.php` |
| `StructuredObjectParserAgentResource` | `structuredobjectparseragentresource` | `src/Resource/StructuredObjectParserAgentResource.php` |
| `TelegramAgentTool` | `telegramagenttool` | `src/Resource/TelegramAgentTool.php` |
| `ToolProxyAgentTool` | `toolproxyagenttool` | `src/Resource/ToolProxyAgentTool.php` |
| `UploadStreamExtractorAgentResource` | `uploadstreamextractoragentresource` | `src/Resource/UploadStreamExtractorAgentResource.php` |
| `UserPrefsAgentResource` | `userprefsagentresource` | `src/Resource/UserPrefsAgentResource.php` |
| `XrmChunkerAgentResource` | `xrmchunkeragentresource` | `src/Resource/XrmChunkerAgentResource.php` |

## `Retrieval`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `DefaultRetrievalCollectionDefinition` |  | `src/Retrieval/DefaultRetrievalCollectionDefinition.php` |
| `ColognePhoneticEncoder` | `colognephoneticencoder` | `src/Retrieval/Phonetic/ColognePhoneticEncoder.php` |
| `SoundexPhoneticEncoder` | `soundexphoneticencoder` | `src/Retrieval/Phonetic/SoundexPhoneticEncoder.php` |
| `PhoneticTextMaterializer` |  | `src/Retrieval/PhoneticTextMaterializer.php` |

## `SearchService`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AbstractSearchService` |  | `src/SearchService/AbstractSearchService.php` |
| `MistralWebSearchService` | `mistralwebsearchservice` | `src/SearchService/MistralWebSearchService.php` |
| `OpenAiWebSearchService` | `openaiwebsearchservice` | `src/SearchService/OpenAiWebSearchService.php` |

## `Service`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AgentComponentDefaultPresetInstaller` | `agentcomponentdefaultpresetinstaller` | `src/Service/AgentComponentDefaultPresetInstaller.php` |
| `AgentComponentFlowBuilder` | `agentcomponentflowbuilder` | `src/Service/AgentComponentFlowBuilder.php` |
| `AgentComponentPresetCatalog` |  | `src/Service/AgentComponentPresetCatalog.php` |
| `AgentComponentPresetFlowExpander` |  | `src/Service/AgentComponentPresetFlowExpander.php` |
| `AgentComponentPresetMaterializer` | `agentcomponentpresetmaterializer` | `src/Service/AgentComponentPresetMaterializer.php` |
| `AgentComponentPresetRepository` | `agentcomponentpresetrepository` | `src/Service/AgentComponentPresetRepository.php` |
| `AgentComponentPresetToolTestService` |  | `src/Service/AgentComponentPresetToolTestService.php` |
| `AgentConfigFormService` | `missionbayagentconfigformservice` | `src/Service/AgentConfigFormService.php` |
| `AgentConversationService` | `missionbayagentconversationservice` | `src/Service/AgentConversationService.php` |
| `AgentExecutionService` | `agentexecutionservice` | `src/Service/AgentExecutionService.php` |
| `AgentFlowCompiler` | `agentflowcompiler` | `src/Service/AgentFlowCompiler.php` |
| `AgentTextTaskService` | `missionbayagenttexttaskservice` | `src/Service/AgentTextTaskService.php` |
| `AgentAssistantContextContributionService` |  | `src/Service/Assistant/AgentAssistantContextContributionService.php` |
| `AgentAssistantFallbackBuilder` |  | `src/Service/Assistant/AgentAssistantFallbackBuilder.php` |
| `AgentAssistantFinalResponseService` |  | `src/Service/Assistant/AgentAssistantFinalResponseService.php` |
| `AgentAssistantMemoryService` |  | `src/Service/Assistant/AgentAssistantMemoryService.php` |
| `AgentAssistantMessageFactory` |  | `src/Service/Assistant/AgentAssistantMessageFactory.php` |
| `AgentAssistantTurnService` |  | `src/Service/Assistant/AgentAssistantTurnService.php` |
| `AgentFinalResponseGuardService` |  | `src/Service/Assistant/AgentFinalResponseGuardService.php` |
| `ConfiguredAiModelConfigurationProvider` | `configuredaimodelconfigurationprovider` | `src/Service/ConfiguredAiModelConfigurationProvider.php` |
| `ConfiguredServiceRuntimeResolver` |  | `src/Service/ConfiguredServiceRuntimeResolver.php` |
| `ConfiguredServiceTestService` |  | `src/Service/ConfiguredServiceTestService.php` |
| `EmbeddingOrchestratorConfigRepository` |  | `src/Service/EmbeddingOrchestratorConfigRepository.php` |
| `AgentMemoryRoleResolver` |  | `src/Service/Memory/AgentMemoryRoleResolver.php` |
| `MissionBayMcp` | `missionbaymcp` | `src/Service/MissionBayMcp.php` |
| `RetrievalCollectionConfigRepository` |  | `src/Service/RetrievalCollectionConfigRepository.php` |
| `RetrievalSearchService` |  | `src/Service/RetrievalSearchService.php` |
| `ServiceConfig` |  | `src/Service/ServiceConfig.php` |

## `ServiceDriver`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `DoclingParserServiceDriverDefinition` | `doclingparserservicedriverdefinition` | `src/ServiceDriver/DoclingParserServiceDriverDefinition.php` |
| `MistralChatServiceDriverDefinition` | `mistralchatservicedriverdefinition` | `src/ServiceDriver/MistralChatServiceDriverDefinition.php` |
| `MistralImageServiceDriverDefinition` | `mistralimageservicedriverdefinition` | `src/ServiceDriver/MistralImageServiceDriverDefinition.php` |
| `MistralSpeechToTextDriverDefinition` | `mistralspeechtotextdriverdefinition` | `src/ServiceDriver/MistralSpeechToTextDriverDefinition.php` |
| `MistralTextToSpeechDriverDefinition` | `mistraltexttospeechdriverdefinition` | `src/ServiceDriver/MistralTextToSpeechDriverDefinition.php` |
| `MistralWebSearchServiceDriverDefinition` | `mistralwebsearchservicedriverdefinition` | `src/ServiceDriver/MistralWebSearchServiceDriverDefinition.php` |
| `OpenAiChatServiceDriverDefinition` | `openaichatservicedriverdefinition` | `src/ServiceDriver/OpenAiChatServiceDriverDefinition.php` |
| `OpenAiCompatibleChatServiceDriverDefinition` | `openaicompatiblechatservicedriverdefinition` | `src/ServiceDriver/OpenAiCompatibleChatServiceDriverDefinition.php` |
| `OpenAiCompatibleEmbeddingServiceDriverDefinition` | `openaicompatibleembeddingservicedriverdefinition` | `src/ServiceDriver/OpenAiCompatibleEmbeddingServiceDriverDefinition.php` |
| `OpenAiCompatibleImageServiceDriverDefinition` | `openaicompatibleimageservicedriverdefinition` | `src/ServiceDriver/OpenAiCompatibleImageServiceDriverDefinition.php` |
| `OpenAiEmbeddingServiceDriverDefinition` | `openaiembeddingservicedriverdefinition` | `src/ServiceDriver/OpenAiEmbeddingServiceDriverDefinition.php` |
| `OpenAiImageServiceDriverDefinition` | `openaiimageservicedriverdefinition` | `src/ServiceDriver/OpenAiImageServiceDriverDefinition.php` |
| `OpenAiSpeechToTextDriverDefinition` | `openaispeechtotextdriverdefinition` | `src/ServiceDriver/OpenAiSpeechToTextDriverDefinition.php` |
| `OpenAiTextToSpeechDriverDefinition` | `openaitexttospeechdriverdefinition` | `src/ServiceDriver/OpenAiTextToSpeechDriverDefinition.php` |
| `OpenAiWebSearchServiceDriverDefinition` | `openaiwebsearchservicedriverdefinition` | `src/ServiceDriver/OpenAiWebSearchServiceDriverDefinition.php` |
| `QdrantVectorStoreServiceDriverDefinition` | `qdrantvectorstoreservicedriverdefinition` | `src/ServiceDriver/QdrantVectorStoreServiceDriverDefinition.php` |
| `UnstructuredParserServiceDriverDefinition` | `unstructuredparserservicedriverdefinition` | `src/ServiceDriver/UnstructuredParserServiceDriverDefinition.php` |

## `Speech`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `ConfiguredRealtimeSpeechToTextSessionService` |  | `src/Speech/ConfiguredRealtimeSpeechToTextSessionService.php` |
| `ConfiguredSpeechToTextService` |  | `src/Speech/ConfiguredSpeechToTextService.php` |
| `ConfiguredTextToSpeechService` |  | `src/Speech/ConfiguredTextToSpeechService.php` |
| `MistralSpeechToTextDriver` | `mistralspeechtotextdriver` | `src/Speech/MistralSpeechToTextDriver.php` |
| `MistralTextToSpeechDriver` | `mistraltexttospeechdriver` | `src/Speech/MistralTextToSpeechDriver.php` |
| `OpenAiSpeechToTextDriver` | `openaispeechtotextdriver` | `src/Speech/OpenAiSpeechToTextDriver.php` |
| `OpenAiTextToSpeechDriver` | `openaitexttospeechdriver` | `src/Speech/OpenAiTextToSpeechDriver.php` |

## `Tool`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `MissionBayAgentToolProfileProvider` | `missionbayagenttoolprofileprovider` | `src/Tool/Profile/MissionBayAgentToolProfileProvider.php` |
| `MissionBayAgentToolSet` |  | `src/Tool/Profile/MissionBayAgentToolSet.php` |

## `Transport`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `ChatCompletionEndpointResolver` |  | `src/Transport/ChatCompletionEndpointResolver.php` |
| `MistralTransport` | `mistraltransport` | `src/Transport/MistralTransport.php` |
| `OpenAiCompatibleTransport` | `openaicompatibletransport` | `src/Transport/OpenAiCompatibleTransport.php` |
| `OpenAiTransport` | `openaitransport` | `src/Transport/OpenAiTransport.php` |

## `VectorStore`

| Class or interface | Technical name | File |
| --- | --- | --- |
| `AbstractQdrantVectorStoreService` |  | `src/VectorStore/AbstractQdrantVectorStoreService.php` |
| `QdrantVectorStoreService` | `qdrantvectorstoreservice` | `src/VectorStore/QdrantVectorStoreService.php` |

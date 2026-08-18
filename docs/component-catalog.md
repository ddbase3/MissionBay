# MissionBay Component Catalog

## Purpose

This catalog is derived from the current source tree. It lists discoverable technical names where a class declares a literal `getName()` value. Abstract/helper classes without a public technical name are omitted from the name column.

## Flows

| Technical name | Class | File |
| --- | --- | --- |
| `dynamicaiflow` | `DynamicAiFlow` | `src/Flow/DynamicAiFlow.php` |
| `strictflow` | `StrictFlow` | `src/Flow/StrictFlow.php` |

## Nodes

| Technical name | Class | File |
| --- | --- | --- |
| `aiassistantnode` | `AiAssistantNode` | `src/Node/Ai/AiAssistantNode.php` |
| `aiembedtextnode` | `AiEmbedTextNode` | `src/Node/Ai/AiEmbedTextNode.php` |
| `aiindexingnode` | `AiIndexingNode` | `src/Node/Ai/AiIndexingNode.php` |
| `deepltranslatenode` | `DeepLTranslateNode` | `src/Node/Ai/DeepLTranslateNode.php` |
| `openairesponsenode` | `OpenAiResponseNode` | `src/Node/Ai/OpenAiResponseNode.php` |
| `simplellamanode` | `SimpleLlamaNode` | `src/Node/Ai/SimpleLlamaNode.php` |
| `simpleopenainode` | `SimpleOpenAiNode` | `src/Node/Ai/SimpleOpenAiNode.php` |
| `conditionalpassnode` | `ConditionalPassNode` | `src/Node/Control/ConditionalPassNode.php` |
| `delaynode` | `DelayNode` | `src/Node/Control/DelayNode.php` |
| `foreachnode` | `ForEachNode` | `src/Node/Control/ForEachNode.php` |
| `ifnode` | `IfNode` | `src/Node/Control/IfNode.php` |
| `loopnode` | `LoopNode` | `src/Node/Control/LoopNode.php` |
| `noactionnode` | `NoActionNode` | `src/Node/Control/NoActionNode.php` |
| `subflownode` | `SubFlowNode` | `src/Node/Control/SubFlowNode.php` |
| `switchnode` | `SwitchNode` | `src/Node/Control/SwitchNode.php` |
| `getconfigurationnode` | `GetConfigurationNode` | `src/Node/Core/GetConfigurationNode.php` |
| `getcontextvarnode` | `GetContextVarNode` | `src/Node/Core/GetContextVarNode.php` |
| `setcontextvarnode` | `SetContextVarNode` | `src/Node/Core/SetContextVarNode.php` |
| `testinputnode` | `TestInputNode` | `src/Node/Core/TestInputNode.php` |
| `arraygetnode` | `ArrayGetNode` | `src/Node/Data/ArrayGetNode.php` |
| `arraysetnode` | `ArraySetNode` | `src/Node/Data/ArraySetNode.php` |
| `jsontoarraynode` | `JsonToArrayNode` | `src/Node/Data/JsonToArrayNode.php` |
| `tryarraygetnode` | `TryArrayGetNode` | `src/Node/Data/TryArrayGetNode.php` |
| `httpgetnode` | `HttpGetNode` | `src/Node/Http/HttpGetNode.php` |
| `httprequestnode` | `HttpRequestNode` | `src/Node/Http/HttpRequestNode.php` |
| `loggernode` | `LoggerNode` | `src/Node/Message/LoggerNode.php` |
| `staticmessagenode` | `StaticMessageNode` | `src/Node/Message/StaticMessageNode.php` |
| `stringreversernode` | `StringReverserNode` | `src/Node/Message/StringReverserNode.php` |
| `telegramsendmessage` | `TelegramSendMessage` | `src/Node/Message/TelegramSendMessage.php` |

## Resources and tools

| Technical name | Class | File |
| --- | --- | --- |
| `statictextcontextagentresource` | `StaticTextContextAgentResource` | `src/Resource/AgentContext/Text/StaticTextContextAgentResource.php` |
| `timememoryagentresource` | `TimeMemoryAgentResource` | `src/Resource/AgentMemory/Time/TimeMemoryAgentResource.php` |
| `runavailableagenttool` | `RunAvailableAgentTool` | `src/Resource/AgentTool/Agent/RunAvailableAgentTool.php` |
| `runconfiguredagenttool` | `RunConfiguredAgentTool` | `src/Resource/AgentTool/Agent/RunConfiguredAgentTool.php` |
| `batchagenttool` | `BatchAgentTool` | `src/Resource/AgentTool/Batch/BatchAgentTool.php` |
| `currencyconvertagenttool` | `CurrencyConvertAgentTool` | `src/Resource/AgentTool/CurrencyConvert/CurrencyConvertAgentTool.php` |
| `currenttimeagenttool` | `CurrentTimeAgentTool` | `src/Resource/AgentTool/CurrentTime/CurrentTimeAgentTool.php` |
| `logagenttool` | `LogAgentTool` | `src/Resource/AgentTool/Log/LogAgentTool.php` |
| `systemstatusagenttool` | `SystemStatusAgentTool` | `src/Resource/AgentTool/SystemStatus/SystemStatusAgentTool.php` |
| `weatheragenttool` | `WeatherAgentTool` | `src/Resource/AgentTool/Weather/WeatherAgentTool.php` |
| `webfetchtextagenttool` | `WebFetchTextAgentTool` | `src/Resource/AgentTool/WebFetchText/WebFetchTextAgentTool.php` |
| `anthropicchatmodelagentresource` | `AnthropicChatModelAgentResource` | `src/Resource/AnthropicChatModelAgentResource.php` |
| `blockchatbotagenttool` | `BlockChatbotAgentTool` | `src/Resource/BlockChatbotAgentTool.php` |
| `canvascloseagenttool` | `CanvasCloseAgentTool` | `src/Resource/CanvasCloseAgentTool.php` |
| `configuredagentmemoryresource` | `ConfiguredAgentMemoryResource` | `src/Resource/ConfiguredAgentMemoryResource.php` |
| `configuredagenttoolresource` | `ConfiguredAgentToolResource` | `src/Resource/ConfiguredAgentToolResource.php` |
| `configuredchatmodelagentresource` | `ConfiguredChatModelAgentResource` | `src/Resource/ConfiguredChatModelAgentResource.php` |
| `configuredembeddingmodelagentresource` | `ConfiguredEmbeddingModelAgentResource` | `src/Resource/ConfiguredEmbeddingModelAgentResource.php` |
| `configuredimagemodelagentresource` | `ConfiguredImageModelAgentResource` | `src/Resource/ConfiguredImageModelAgentResource.php` |
| `configuredparserserviceagentresource` | `ConfiguredParserServiceAgentResource` | `src/Resource/ConfiguredParserServiceAgentResource.php` |
| `configuredsearchserviceagentresource` | `ConfiguredSearchServiceAgentResource` | `src/Resource/ConfiguredSearchServiceAgentResource.php` |
| `configuredvectorsearchagentresource` | `ConfiguredVectorSearchAgentResource` | `src/Resource/ConfiguredVectorSearchAgentResource.php` |
| `configuredvectorstoreagentresource` | `ConfiguredVectorStoreAgentResource` | `src/Resource/ConfiguredVectorStoreAgentResource.php` |
| `crmproductxrmextractoragentresource` | `CrmProductXrmExtractorAgentResource` | `src/Resource/CrmProductXrmExtractorAgentResource.php` |
| `databasememoryagentresource` | `DatabaseMemoryAgentResource` | `src/Resource/DatabaseMemoryAgentResource.php` |
| `deepseekchatmodelagentresource` | `DeepSeekChatModelAgentResource` | `src/Resource/DeepSeekChatModelAgentResource.php` |
| `dummyembeddingmodelagentresource` | `DummyEmbeddingModelAgentResource` | `src/Resource/DummyEmbeddingModelAgentResource.php` |
| `dummyextractoragentresource` | `DummyExtractorAgentResource` | `src/Resource/DummyExtractorAgentResource.php` |
| `embeddingcacheagentresource` | `EmbeddingCacheAgentResource` | `src/Resource/EmbeddingCacheAgentResource.php` |
| `fireworkschatmodelagentresource` | `FireworksChatModelAgentResource` | `src/Resource/FireworksChatModelAgentResource.php` |
| `focusagentresource` | `FocusAgentResource` | `src/Resource/FocusAgentResource.php` |
| `geminichatmodelagentresource` | `GeminiChatModelAgentResource` | `src/Resource/GeminiChatModelAgentResource.php` |
| `generalinfoagenttool` | `GeneralInfoAgentTool` | `src/Resource/GeneralInfoAgentTool.php` |
| `genericchatmodelagentresource` | `GenericChatModelAgentResource` | `src/Resource/GenericChatModelAgentResource.php` |
| `grokchatmodelagentresource` | `GrokChatModelAgentResource` | `src/Resource/GrokChatModelAgentResource.php` |
| `groqchatmodelagentresource` | `GroqChatModelAgentResource` | `src/Resource/GroqChatModelAgentResource.php` |
| `helloworldcanvasagenttool` | `HelloWorldCanvasAgentTool` | `src/Resource/HelloWorldCanvasAgentTool.php` |
| `knowledgeagentresource` | `KnowledgeAgentResource` | `src/Resource/KnowledgeAgentResource.php` |
| `loggerresource` | `LoggerResource` | `src/Resource/Logger/Logger/LoggerResource.php` |
| `mcpclientagentresource` | `McpClientAgentResource` | `src/Resource/Mcp/McpClientAgentResource.php` |
| `mermaidsyntaxagenttool` | `MermaidSyntaxAgentTool` | `src/Resource/MermaidSyntaxAgentTool.php` |
| `mistralchatmodelagentresource` | `MistralChatModelAgentResource` | `src/Resource/MistralChatModelAgentResource.php` |
| `nochunkeragentresource` | `NoChunkerAgentResource` | `src/Resource/NoChunkerAgentResource.php` |
| `noembeddingmodelagentresource` | `NoEmbeddingModelAgentResource` | `src/Resource/NoEmbeddingModelAgentResource.php` |
| `noparseragentresource` | `NoParserAgentResource` | `src/Resource/NoParserAgentResource.php` |
| `openaichatmodelagentresource` | `OpenAiChatModelAgentResource` | `src/Resource/OpenAiChatModelAgentResource.php` |
| `openaiembeddingmodelagentresource` | `OpenAiEmbeddingModelAgentResource` | `src/Resource/OpenAiEmbeddingModelAgentResource.php` |
| `openrouterchatmodelagentresource` | `OpenRouterChatModelAgentResource` | `src/Resource/OpenRouterChatModelAgentResource.php` |
| `perplexitychatmodelagentresource` | `PerplexityChatModelAgentResource` | `src/Resource/PerplexityChatModelAgentResource.php` |
| `productxrmextractoragentresource` | `ProductXrmExtractorAgentResource` | `src/Resource/ProductXrmExtractorAgentResource.php` |
| `qdrantvectorsearch` | `QdrantVectorSearch` | `src/Resource/QdrantVectorSearch.php` |
| `ragsearchagenttool` | `RagSearchAgentTool` | `src/Resource/RagSearchAgentTool.php` |
| `retrievalagenttool` | `RetrievalAgentTool` | `src/Resource/RetrievalAgentTool.php` |
| `routingchatmodelagentresource` | `RoutingChatModelAgentResource` | `src/Resource/RoutingChatModelAgentResource.php` |
| `semanticchunkeragentresource` | `SemanticChunkerAgentResource` | `src/Resource/SemanticChunkerAgentResource.php` |
| `sessionmemoryagentresource` | `SessionMemoryAgentResource` | `src/Resource/SessionMemoryAgentResource.php` |
| `structuredobjectparseragentresource` | `StructuredObjectParserAgentResource` | `src/Resource/StructuredObjectParserAgentResource.php` |
| `telegramagenttool` | `TelegramAgentTool` | `src/Resource/TelegramAgentTool.php` |
| `toolproxyagenttool` | `ToolProxyAgentTool` | `src/Resource/ToolProxyAgentTool.php` |
| `uploadstreamextractoragentresource` | `UploadStreamExtractorAgentResource` | `src/Resource/UploadStreamExtractorAgentResource.php` |
| `userprefsagentresource` | `UserPrefsAgentResource` | `src/Resource/UserPrefsAgentResource.php` |
| `xrmchunkeragentresource` | `XrmChunkerAgentResource` | `src/Resource/XrmChunkerAgentResource.php` |

## Displays

| Technical name | Class | File |
| --- | --- | --- |
| `agentadmindisplay` | `AgentAdminDisplay` | `src/Display/AgentAdminDisplay.php` |
| `agentcomponentpresetadmindisplay` | `AgentComponentPresetAdminDisplay` | `src/Display/AgentComponentPresetAdminDisplay.php` |
| `agentcomponentpresettestadmindisplay` | `AgentComponentPresetTestAdminDisplay` | `src/Display/AgentComponentPresetTestAdminDisplay.php` |
| `agentcompositionadmindisplay` | `AgentCompositionAdminDisplay` | `src/Display/AgentCompositionAdminDisplay.php` |
| `agentcontextprofileadmindisplay` | `AgentContextProfileAdminDisplay` | `src/Display/AgentContextProfileAdminDisplay.php` |
| `agentinfotopicprovidertestadmindisplay` | `AgentInfoTopicProviderTestAdminDisplay` | `src/Display/AgentInfoTopicProviderTestAdminDisplay.php` |
| `agentmemoryprofileadmindisplay` | `AgentMemoryProfileAdminDisplay` | `src/Display/AgentMemoryProfileAdminDisplay.php` |
| `agentorchestratorprofileadmindisplay` | `AgentOrchestratorProfileAdminDisplay` | `src/Display/AgentOrchestratorProfileAdminDisplay.php` |
| `agenttoollogadmindisplay` | `AgentToolLogAdminDisplay` | `src/Display/AgentToolLogAdminDisplay.php` |
| `connectionconfigdisplay` | `ConnectionConfigDisplay` | `src/Display/ConnectionConfigDisplay.php` |
| `embeddingconfigdisplay` | `EmbeddingConfigDisplay` | `src/Display/EmbeddingConfigDisplay.php` |
| `embeddingorchestratorconfigadmindisplay` | `EmbeddingOrchestratorConfigAdminDisplay` | `src/Display/EmbeddingOrchestratorConfigAdminDisplay.php` |
| `imageconfigdisplay` | `ImageConfigDisplay` | `src/Display/ImageConfigDisplay.php` |
| `knowledgeagentmemoryadmindisplay` | `KnowledgeAgentMemoryAdminDisplay` | `src/Display/KnowledgeAgentMemoryAdminDisplay.php` |
| `llmconfigdisplay` | `LlmConfigDisplay` | `src/Display/LlmConfigDisplay.php` |
| `parserserviceconfigdisplay` | `ParserServiceConfigDisplay` | `src/Display/ParserServiceConfigDisplay.php` |
| `retrievalcollectionadmindisplay` | `RetrievalCollectionAdminDisplay` | `src/Display/RetrievalCollectionAdminDisplay.php` |
| `retrievalsearchadmindisplay` | `RetrievalSearchAdminDisplay` | `src/Display/RetrievalSearchAdminDisplay.php` |
| `retrievalvectorpointsadmindisplay` | `RetrievalVectorPointsAdminDisplay` | `src/Display/RetrievalVectorPointsAdminDisplay.php` |
| `searchconfigdisplay` | `SearchConfigDisplay` | `src/Display/SearchConfigDisplay.php` |
| `speechtotextconfigdisplay` | `SpeechToTextConfigDisplay` | `src/Display/SpeechToTextConfigDisplay.php` |
| `texttospeechconfigdisplay` | `TextToSpeechConfigDisplay` | `src/Display/TextToSpeechConfigDisplay.php` |
| `toolprofileadmindisplay` | `ToolProfileAdminDisplay` | `src/Display/ToolProfileAdminDisplay.php` |
| `userprefdefadmindisplay` | `UserPrefDefAdminDisplay` | `src/Display/UserPrefDefAdminDisplay.php` |
| `vectorsearchconfigdisplay` | `VectorSearchConfigDisplay` | `src/Display/VectorSearchConfigDisplay.php` |
| `vectorstoreconfigdisplay` | `VectorStoreConfigDisplay` | `src/Display/VectorStoreConfigDisplay.php` |

## Service driver definitions

| Technical name | Class | File |
| --- | --- | --- |
| `doclingparserservicedriverdefinition` | `DoclingParserServiceDriverDefinition` | `src/ServiceDriver/DoclingParserServiceDriverDefinition.php` |
| `mistralchatservicedriverdefinition` | `MistralChatServiceDriverDefinition` | `src/ServiceDriver/MistralChatServiceDriverDefinition.php` |
| `mistralimageservicedriverdefinition` | `MistralImageServiceDriverDefinition` | `src/ServiceDriver/MistralImageServiceDriverDefinition.php` |
| `mistralspeechtotextdriverdefinition` | `MistralSpeechToTextDriverDefinition` | `src/ServiceDriver/MistralSpeechToTextDriverDefinition.php` |
| `mistraltexttospeechdriverdefinition` | `MistralTextToSpeechDriverDefinition` | `src/ServiceDriver/MistralTextToSpeechDriverDefinition.php` |
| `mistralwebsearchservicedriverdefinition` | `MistralWebSearchServiceDriverDefinition` | `src/ServiceDriver/MistralWebSearchServiceDriverDefinition.php` |
| `openaichatservicedriverdefinition` | `OpenAiChatServiceDriverDefinition` | `src/ServiceDriver/OpenAiChatServiceDriverDefinition.php` |
| `openaicompatiblechatservicedriverdefinition` | `OpenAiCompatibleChatServiceDriverDefinition` | `src/ServiceDriver/OpenAiCompatibleChatServiceDriverDefinition.php` |
| `openaicompatibleembeddingservicedriverdefinition` | `OpenAiCompatibleEmbeddingServiceDriverDefinition` | `src/ServiceDriver/OpenAiCompatibleEmbeddingServiceDriverDefinition.php` |
| `openaicompatibleimageservicedriverdefinition` | `OpenAiCompatibleImageServiceDriverDefinition` | `src/ServiceDriver/OpenAiCompatibleImageServiceDriverDefinition.php` |
| `openaiembeddingservicedriverdefinition` | `OpenAiEmbeddingServiceDriverDefinition` | `src/ServiceDriver/OpenAiEmbeddingServiceDriverDefinition.php` |
| `openaiimageservicedriverdefinition` | `OpenAiImageServiceDriverDefinition` | `src/ServiceDriver/OpenAiImageServiceDriverDefinition.php` |
| `openaispeechtotextdriverdefinition` | `OpenAiSpeechToTextDriverDefinition` | `src/ServiceDriver/OpenAiSpeechToTextDriverDefinition.php` |
| `openaitexttospeechdriverdefinition` | `OpenAiTextToSpeechDriverDefinition` | `src/ServiceDriver/OpenAiTextToSpeechDriverDefinition.php` |
| `openaiwebsearchservicedriverdefinition` | `OpenAiWebSearchServiceDriverDefinition` | `src/ServiceDriver/OpenAiWebSearchServiceDriverDefinition.php` |
| `qdrantvectorstoreservicedriverdefinition` | `QdrantVectorStoreServiceDriverDefinition` | `src/ServiceDriver/QdrantVectorStoreServiceDriverDefinition.php` |
| `unstructuredparserservicedriverdefinition` | `UnstructuredParserServiceDriverDefinition` | `src/ServiceDriver/UnstructuredParserServiceDriverDefinition.php` |

## Chat models

| Technical name | Class | File |
| --- | --- | --- |
| `mistralchatmodel` | `MistralChatModel` | `src/ChatModel/MistralChatModel.php` |
| `openaichatmodel` | `OpenAiChatModel` | `src/ChatModel/OpenAiChatModel.php` |
| `openaicompatiblechatmodel` | `OpenAiCompatibleChatModel` | `src/ChatModel/OpenAiCompatibleChatModel.php` |

## Embedding models

| Technical name | Class | File |
| --- | --- | --- |
| `openaicompatibleembeddingmodel` | `OpenAiCompatibleEmbeddingModel` | `src/EmbeddingModel/OpenAiCompatibleEmbeddingModel.php` |
| `openaiembeddingmodel` | `OpenAiEmbeddingModel` | `src/EmbeddingModel/OpenAiEmbeddingModel.php` |

## Image models

| Technical name | Class | File |
| --- | --- | --- |
| `mistralimagemodel` | `MistralImageModel` | `src/ImageModel/MistralImageModel.php` |
| `openaicompatibleimagemodel` | `OpenAiCompatibleImageModel` | `src/ImageModel/OpenAiCompatibleImageModel.php` |
| `openaiimagemodel` | `OpenAiImageModel` | `src/ImageModel/OpenAiImageModel.php` |

## Search services

| Technical name | Class | File |
| --- | --- | --- |
| `mistralwebsearchservice` | `MistralWebSearchService` | `src/SearchService/MistralWebSearchService.php` |
| `openaiwebsearchservice` | `OpenAiWebSearchService` | `src/SearchService/OpenAiWebSearchService.php` |

## Parser services

| Technical name | Class | File |
| --- | --- | --- |
| `doclingparserservice` | `DoclingParserService` | `src/ParserService/DoclingParserService.php` |
| `unstructuredparserservice` | `UnstructuredParserService` | `src/ParserService/UnstructuredParserService.php` |

## Speech implementations

| Technical name | Class | File |
| --- | --- | --- |
| `mistralspeechtotextdriver` | `MistralSpeechToTextDriver` | `src/Speech/MistralSpeechToTextDriver.php` |
| `mistraltexttospeechdriver` | `MistralTextToSpeechDriver` | `src/Speech/MistralTextToSpeechDriver.php` |
| `openaispeechtotextdriver` | `OpenAiSpeechToTextDriver` | `src/Speech/OpenAiSpeechToTextDriver.php` |
| `openaitexttospeechdriver` | `OpenAiTextToSpeechDriver` | `src/Speech/OpenAiTextToSpeechDriver.php` |

## Policies

| Technical name | Class | File |
| --- | --- | --- |
| `allowallagentactionpolicy` | `AllowAllAgentActionPolicy` | `src/Policy/AllowAllAgentActionPolicy.php` |
| `mutationapprovalagentactionpolicy` | `MutationApprovalAgentActionPolicy` | `src/Policy/MutationApprovalAgentActionPolicy.php` |

## Info providers

| Technical name | Class | File |
| --- | --- | --- |
| `staticdemoinfotopicprovider` | `StaticDemoInfoTopicProvider` | `src/InfoProvider/StaticDemoInfoTopicProvider.php` |
| `systeminfotopicprovider` | `SystemInfoTopicProvider` | `src/InfoProvider/SystemInfoTopicProvider.php` |

## Jobs

| Technical name | Class | File |
| --- | --- | --- |
| `scheduledagentrunnerjob` | `ScheduledAgentRunnerJob` | `src/Job/ScheduledAgentRunnerJob.php` |

## Notes

* A technical name is an implementation/discovery key, not a configured preset ID.
* Component preset IDs live in `agent-component-preset` and may point to the same resource implementation multiple times.
* Container service names are not listed here unless the class is also discoverable by `getName()`.
* Host extension packages contribute additional resources, jobs and collection definitions.

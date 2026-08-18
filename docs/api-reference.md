# MissionBay API Reference

## Purpose

This is a source-derived reference for the current MissionBay-specific contracts under `src/Api/`. AssistantFoundation contracts are documented in AssistantFoundation and are not duplicated here.

## `IAgent`

File: `src/Api/IAgent.php`

```php
interface IAgent extends IBase
public function getId(): string;
public function setId(string $id): void;
public function setContext(AgentContext $context): void;
public function getContext(): ?AgentContext;
public function run(array $inputs = []): array;
public function getFunctionName(): string;
public function getDescription(): string;
public function getInputSpec(): array;
public function getOutputSpec(): array;
public function getDefaultConfig(): array;
public function getCategory(): string;
public function supportsAsync(): bool;
public function getDependencies(): array;
public function getVersion(): string;
public function getTags(): array;
```

## `IAgentAssistantContextContributionService`

File: `src/Api/IAgentAssistantContextContributionService.php`

```php
interface IAgentAssistantContextContributionService
public function buildMessages(array $resources, IAgentContext $context, ?ILogger $logger = null): array;
```

## `IAgentAssistantFallbackBuilder`

File: `src/Api/IAgentAssistantFallbackBuilder.php`

```php
interface IAgentAssistantFallbackBuilder
public function build(AgentToolOrchestratorResult $orchestrationResult): string;
```

## `IAgentAssistantFinalResponseService`

File: `src/Api/IAgentAssistantFinalResponseService.php`

```php
interface IAgentAssistantFinalResponseService
public function createDirectResponse(IAiChatModel $model, AgentAssistantTurnResult $turnResult): string;
public function createStreamingResponse(IAiChatModel $model, AgentAssistantTurnResult $turnResult, callable $onData, ?callable $onMeta = null): string;
public function createAssistantMessage(AgentAssistantTurnResult $turnResult, string $content): array;
```

## `IAgentAssistantMemoryService`

File: `src/Api/IAgentAssistantMemoryService.php`

```php
interface IAgentAssistantMemoryService
public function sortMemories(array $memories): array;
public function buildInitialMessages(string $system, array $memories, string $nodeId, ?ILogger $logger = null): array;
public function appendVisibleMessage(array $memories, string $nodeId, array $message, ?ILogger $logger = null): void;
```

## `IAgentAssistantMessageFactory`

File: `src/Api/IAgentAssistantMessageFactory.php`

```php
interface IAgentAssistantMessageFactory
public function createUserMessage(string $prompt): array;
public function createAssistantMessage(string $assistantMessageId, mixed $content): array;
public function normalizeContent(mixed $content): string;
public function isVisibleHistoryEntry(mixed $entry): bool;
```

## `IAgentAssistantToolSetupFactory`

File: `src/Api/IAgentAssistantToolSetupFactory.php`

```php
interface IAgentAssistantToolSetupFactory
public function create(array $tools, ?IAgentProfileSelector $profileSelector, string $prompt, string $system, IAgentContext $context): AgentAssistantToolSetup;
```

## `IAgentAssistantTurnService`

File: `src/Api/IAgentAssistantTurnService.php`

```php
interface IAgentAssistantTurnService
public function run(AgentAssistantTurnResources $resources, IAgentContext $context, AgentAssistantTurnOptions $options, ?callable $eventCallback = null): AgentAssistantTurnResult;
```

## `IAgentBatchTool`

File: `src/Api/IAgentBatchTool.php`

```php
interface IAgentBatchTool extends IAgentTool, IAgentMutationGuardedTool
public function isBatchFunction(string $name): bool;
public function expandApprovedBatch(AgentAction $action, AgentMutationCommitSnapshot $snapshot, IAgentContext $context, string $interactionRequestId = ''): array;
```

## `IAgentChunker`

File: `src/Api/IAgentChunker.php`

```php
interface IAgentChunker
public function getPriority(): int;
public function supports(AgentParsedContent $parsed): bool;
public function chunk(AgentParsedContent $parsed): array;
```

## `IAgentComponentFlowBuilder`

File: `src/Api/IAgentComponentFlowBuilder.php`

```php
interface IAgentComponentFlowBuilder
public function build(array $baseFlow, array $components, string $assistantNodeId = 'assistant'): array;
public function getWarnings(): array;
```

## `IAgentComponentPresetCatalog`

File: `src/Api/IAgentComponentPresetCatalog.php`

```php
interface IAgentComponentPresetCatalog
public function getPresetOptionsByInterface(string $interfaceName): array;
public function presetImplements(string $presetId, string $interfaceName): bool;
```

## `IAgentComponentPresetFlowExpander`

File: `src/Api/IAgentComponentPresetFlowExpander.php`

```php
interface IAgentComponentPresetFlowExpander
public function expand(array $flow, array $presetIds): array;
```

## `IAgentComponentPresetInstaller`

File: `src/Api/IAgentComponentPresetInstaller.php`

```php
interface IAgentComponentPresetInstaller
public function installDefaults(bool $overwrite = false): array;
public function getDefaultPresets(): array;
public function getDefaultAgentComponents(): array;
```

## `IAgentComponentPresetMaterializer`

File: `src/Api/IAgentComponentPresetMaterializer.php`

```php
interface IAgentComponentPresetMaterializer
public function createContext(array $vars = []): IAgentContext;
public function materialize(string $presetId, IAgentContext $context): AgentComponentPresetMaterialization;
```

## `IAgentComponentPresetRepository`

File: `src/Api/IAgentComponentPresetRepository.php`

```php
interface IAgentComponentPresetRepository
public function getPresets(): array;
public function getPreset(string $id, array $default = []): array;
public function hasPreset(string $id): bool;
public function savePreset(string $id, array $preset): void;
public function removePreset(string $id): void;
```

## `IAgentConfigValueResolver`

File: `src/Api/IAgentConfigValueResolver.php`

```php
interface IAgentConfigValueResolver
public function resolveValue(array|string|int|float|bool|null $config): mixed;
```

## `IAgentContentExtractor`

File: `src/Api/IAgentContentExtractor.php`

```php
interface IAgentContentExtractor
public function extract(IAgentContext $context): array;
public function ack(AgentContentItem $item, array $result = []): void;
public function fail(AgentContentItem $item, string $errorMessage, bool $retryHint = true): void;
```

## `IAgentContentParser`

File: `src/Api/IAgentContentParser.php`

```php
interface IAgentContentParser
public function getPriority(): int;
public function supports(AgentContentItem $item): bool;
public function parse(AgentContentItem $item): AgentParsedContent;
```

## `for`

File: `src/Api/IAgentContextFactory.php`

```php
interface for creating agent context instances. */ interface IAgentContextFactory
public function createContext(string $type = 'agentcontext', ?IAgentMemory $memory = null, array $vars = []): IAgentContext;
```

## `IAgentFlow`

File: `src/Api/IAgentFlow.php`

```php
interface IAgentFlow extends IBase
public function setContext(IAgentContext $context): void;
public function run(array $inputs): array;
public function addNode(IAgentNode $node): void;
public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput): void;
public function addInitialInput(string $nodeId, string $key, mixed $value): void;
public function getInitialInputs(): array;
public function getConnections(): array;
public function getNextNode(string $currentNodeId, array $output): ?string;
public function mapInputs(string $fromNodeId, string $toNodeId, array $output): array;
public function isReady(string $nodeId, array $currentInputs): bool;
public function addResource(IAgentResource $resource): void;
public function getResources(): array;
public function addDockConnection(string $nodeId, string $dockName, string $resourceId): void;
public function getAllDockConnections(): array;
public function getDockConnections(string $nodeId): array;
```

## `IAgentFlowCompiler`

File: `src/Api/IAgentFlowCompiler.php`

```php
interface IAgentFlowCompiler
public function compile(array $agentSettings): AgentFlowCompilation;
```

## `for`

File: `src/Api/IAgentFlowFactory.php`

```php
interface for creating agent flows from definitions or templates. */ interface IAgentFlowFactory
public function createFromArray(string $type, array $data, IAgentContext $context): IAgentFlow;
public function createEmpty(string $type, ?IAgentContext $context = null): IAgentFlow;
```

## `map`

File: `src/Api/IAgentInfoTopicProvider.php`

```php
class map by this interface. * The central info tool stays generic and delegates all domain-specific lookup * logic to these providers. */ interface IAgentInfoTopicProvider extends IBase
public function getTopic(): string;
public function getTopicAliases(): array;
public function getTitle(): string;
public function getDescription(): string;
public function getPriority(): int;
public function supports(string $topic): bool;
public function handle(AgentInfoRequest $request): AgentInfoResult;
```

## `is`

File: `src/Api/IAgentKnowledgeService.php`

```php
interface is intended for persistent, queryable knowledge records. */ interface IAgentKnowledgeService
public function getMemoryTypes(): array;
public function getAllowedStatuses(string $memoryType): array;
public function createEntry(array $data): int;
public function updateEntry(int $id, array $data): bool;
public function deleteEntry(int $id, ?string $deletedBy = null): bool;
public function getEntryById(int $id, bool $includeDeleted = false): ?array;
public function findEntries(array $filters = [], int $limit = 50, int $offset = 0): array;
public function searchEntries(string $query, array $options = [], int $limit = 20, int $offset = 0): array;
public function buildPromptExtract(string $query, array $options = [], int $limit = 10): string;
public function touchEntry(int $id): bool;
public function isValidStatusForType(string $memoryType, ?string $status): bool;
public function isMutableByLlm(array $entry): bool;
public function isDeletableByLlm(array $entry): bool;
public function isEntryValidAt(array $entry, ?string $at = null): bool;
public function isEntryExpired(array $entry, ?string $at = null): bool;
```

## `IAgentMemoryRoleResolver`

File: `src/Api/IAgentMemoryRoleResolver.php`

```php
interface IAgentMemoryRoleResolver
public function isConversationMemory(IAgentMemory $memory): bool;
public function isContextContributor(IAgentMemory $memory): bool;
public function getRoles(IAgentMemory $memory): array;
```

## `IAgentModelDecisionStrategy`

File: `src/Api/IAgentModelDecisionStrategy.php`

```php
interface IAgentModelDecisionStrategy extends IBase
public function decide(IAgentContext $context, AgentModelDecisionConfig $config): AgentStageResult;
```

## `IAgentModelDecisionStrategyResolver`

File: `src/Api/IAgentModelDecisionStrategyResolver.php`

```php
interface IAgentModelDecisionStrategyResolver
public function resolve(string $name): IAgentModelDecisionStrategy;
```

## `IAgentMutationGuardedTool`

File: `src/Api/IAgentMutationGuardedTool.php`

```php
interface IAgentMutationGuardedTool
public function captureMutationCommitSnapshot(AgentAction $action, string $actionFingerprint, IAgentContext $context): AgentMutationCommitSnapshot;
public function getActionReview(AgentAction $action, AgentMutationCommitSnapshot $snapshot, IAgentContext $context): AgentActionReview;
public function validateMutationCommit(AgentAction $action, AgentMutationCommitSnapshot $snapshot, IAgentContext $context): AgentMutationCommitDecision;
```

## `IAgentNode`

File: `src/Api/IAgentNode.php`

```php
interface IAgentNode extends IBase
public function getId(): string;
public function setId(string $id): void;
public function getDescription(): string;
public function getInputDefinitions(): array;
public function getOutputDefinitions(): array;
public function getDockDefinitions(): array;
public function getConfig(): array;
public function setConfig(array $config): void;
public function execute(array $inputs, array $resources, IAgentContext $context): array;
```

## `for`

File: `src/Api/IAgentNodeFactory.php`

```php
interface for instantiating agent nodes by type. */ interface IAgentNodeFactory
public function createNode(string $type): ?IAgentNode;
```

## `IAgentProfileSelector`

File: `src/Api/IAgentProfileSelector.php`

```php
interface IAgentProfileSelector
public function selectPlans(string $userPrompt, string $systemPrompt, IAgentContext $context): array;
```

## `IAgentPromptProvider`

File: `src/Api/IAgentPromptProvider.php`

```php
interface IAgentPromptProvider extends IBase
public function getPromptDefinitions(IAgentContext $context): array;
public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array;
```

## `IAgentResource`

File: `src/Api/IAgentResource.php`

```php
interface IAgentResource extends IBase
public function getId(): string;
public function setId(string $id): void;
public function getDescription(): string;
public function getDockDefinitions(): array;
public function getConfig(): array;
public function setConfig(array $config): void;
public function init(array $resources, IAgentContext $context): void;
```

## `for`

File: `src/Api/IAgentResourceFactory.php`

```php
interface for instantiating agent resources by type. */ interface IAgentResourceFactory
public function createResource(string $type): ?IAgentResource;
```

## `IAgentResourceProvider`

File: `src/Api/IAgentResourceProvider.php`

```php
interface IAgentResourceProvider extends IBase
public function getResourceDefinitions(IAgentContext $context): array;
public function readResource(string $uri, IAgentContext $context): ?array;
```

## `for`

File: `src/Api/IAgentRouterFactory.php`

```php
interface for creating router instances used by agent contexts. */ interface IAgentRouterFactory
public function createRouter(string $type = 'strictconnectionrouter'): IAgentRouter;
```

## `IAgentStateContext`

File: `src/Api/IAgentStateContext.php`

```php
interface IAgentStateContext extends IAgentContext
public function getState(): AgentState;
public function setState(AgentState $state): void;
public function isFinished(): bool;
public function finish(AgentResult $result): void;
public function getResult(): ?AgentResult;
```

## `may`

File: `src/Api/IAgentTool.php`

```php
class may publish one or many function definitions, and may * mix read-only functions with mutating functions. * * Each function definition is the authoritative input contract for that * operation. Besides the OpenAI-style function schema, MissionBay reads * top-level semantic annotations such as readOnlyHint, mutation, * requiresApproval, commitGuardRequired, sideEffectHint and destructiveHint. * A guarded single-item operation may additionally declare batchable=true, * batchIndependent=true and maxBatchSize when the generic batch coordinator * may repeat it without changing the tool's normal input schema or callTool(). * These annotations are evaluated per function; implementing IAgentTool does * not make the complete class read-only or mutating. * * Mutating functions with commitGuardRequired=true must be provided by a tool * that also implements IAgentMutationGuardedTool. That capability captures the * state shown to the user, creates the user-facing AgentActionReview, and * revalidates the state immediately before callTool() may perform the write. * * IConfirmableAgentTool is a separate compatibility capability for direct * in-process callers. It must not be confused with the policy-controlled * guarded mutation lifecycle or with MCP client-side approval. * * Tool implementations should remain transport-neutral. They describe and * execute operations; Chatbot, MCP and administration UIs decide how the * definitions, reviews and results are rendered. */ interface IAgentTool extends IBase
public function getToolDefinitions(): array;
public function callTool(string $name, array $arguments, IAgentContext $context): mixed;
```

## `IAgentVectorFilter`

File: `src/Api/IAgentVectorFilter.php`

```php
interface IAgentVectorFilter
public function getFilterSpec(): ?array;
```

## `IConfiguredParserServiceResolver`

File: `src/Api/IConfiguredParserServiceResolver.php`

```php
interface IConfiguredParserServiceResolver
public function listServiceIds(): array;
public function getPriority(string $serviceId): int;
public function describe(string $serviceId): ParserServiceDefinition;
public function resolve(string $serviceId, array $optionOverrides = []): IParserService;
public function resolveSettings(string $serviceId, array $settings, array $optionOverrides = []): IParserService;
```

## `does`

File: `src/Api/IConfirmableAgentTool.php`

```php
interface does not mark a function as mutating and does not * replace mutation, requiresApproval or commitGuardRequired annotations in * getToolDefinitions(). Wrappers exposing this capability under configured * names must translate the effective function name before delegation. */ interface IConfirmableAgentTool
public function getConfirmationRequest(string $name, array $arguments, IAgentContext $context): ?array;
```

## `IEmbeddingOrchestratorConfigRepository`

File: `src/Api/IEmbeddingOrchestratorConfigRepository.php`

```php
interface IEmbeddingOrchestratorConfigRepository
public function getConfig(): array;
public function saveConfig(string $embeddingPreset, string $vectorStorePreset, string $collectionKey): void;
```

## `IMcpAgent`

File: `src/Api/IMcpAgent.php`

```php
interface IMcpAgent extends IAgent
```

## `IMcpClient`

File: `src/Api/IMcpClient.php`

```php
interface IMcpClient
public function initialize(): array;
public function getInitializeResult(): array;
public function listTools(): array;
public function callTool(string $name, array $arguments = []): array;
public function listResources(): array;
public function listResourceTemplates(): array;
public function readResource(string $uri): array;
public function listPrompts(): array;
public function getPrompt(string $name, array $arguments = []): array;
public function getProtocolVersion(): string;
public function getSessionId(): string;
```

## `IMcpClientFactory`

File: `src/Api/IMcpClientFactory.php`

```php
interface IMcpClientFactory
public function create(McpClientConfig $config): IMcpClient;
```

## `IMcpTransport`

File: `src/Api/IMcpTransport.php`

```php
interface IMcpTransport
public function send(McpHttpRequest $request): McpHttpResponse;
```

## `IParserService`

File: `src/Api/IParserService.php`

```php
interface IParserService extends IFileParserService
public function supports(AgentContentItem $item): bool;
public function parse(AgentContentItem $item): AgentParsedContent;
```

## `IParserServiceTestService`

File: `src/Api/IParserServiceTestService.php`

```php
interface IParserServiceTestService
public function test(IParserService $service): array;
```

## `IRetrievalCollectionConfigRepository`

File: `src/Api/IRetrievalCollectionConfigRepository.php`

```php
interface IRetrievalCollectionConfigRepository
public function getCollections(): array;
public function hasCollection(string $collectionKey): bool;
public function getBackendCollectionName(string $collectionKey): string;
public function saveCollection(string $collectionKey, string $backendCollection): void;
public function removeCollection(string $collectionKey): void;
```

## `IRetrievalSearchService`

File: `src/Api/IRetrievalSearchService.php`

```php
interface IRetrievalSearchService
public function getSearchPresets(array $contextMetadata = []): array;
public function search(string $presetId, array $arguments, array $contextMetadata = []): array;
public function context(string $presetId, array $arguments, array $contextMetadata = []): array;
```

## `ISearchService`

File: `src/Api/ISearchService.php`

```php
interface ISearchService extends IBase
public function searchResult(string $query, array $options = []): AiSearchResult;
public function setOptions(array $options): void;
public function getOptions(): array;
public function search(string $query, array $options = []): array;
```

## `ISpeechToTextDriver`

File: `src/Api/ISpeechToTextDriver.php`

```php
interface ISpeechToTextDriver extends IBase
public function getDriver(): string;
public function transcribe(ServiceConfig $serviceConfig, ConnectionConfig $connectionConfig, string $secret, SpeechToTextRequest $request): SpeechToTextResult;
public function createSession(ServiceConfig $serviceConfig, ConnectionConfig $connectionConfig, string $secret, RealtimeSpeechToTextSessionRequest $request): RealtimeSpeechToTextSession;
```

## `ITextToSpeechDriver`

File: `src/Api/ITextToSpeechDriver.php`

```php
interface ITextToSpeechDriver extends IBase
public function getDriver(): string;
public function synthesize(ServiceConfig $serviceConfig, ConnectionConfig $connectionConfig, string $secret, TextToSpeechRequest $request): TextToSpeechResult;
public function stream(ServiceConfig $serviceConfig, ConnectionConfig $connectionConfig, string $secret, TextToSpeechRequest $request, ITextToSpeechStream $stream): TextToSpeechResult;
```

## `IVectorStoreService`

File: `src/Api/IVectorStoreService.php`

```php
interface IVectorStoreService extends IRetrievalIndex, IRetrievalIndexInspector, IBase
public function setOptions(array $options): void;
public function getOptions(): array;
```

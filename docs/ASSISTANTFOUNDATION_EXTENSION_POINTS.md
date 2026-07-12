# AssistantFoundation Extension Points

## Purpose

`AssistantFoundation` is the shared plugin-to-plugin contract layer for AI, agent, memory, provider, and execution integrations. An interface remains in this foundation only when at least one of the following is true:

1. another plugin is expected to provide an implementation;
2. a project plugin is expected to replace the default service;
3. several plugins exchange values through the contract without depending on MissionBay;
4. the contract represents a stable provider-neutral adapter boundary.

MissionBay-only orchestration helpers, factories, profile resolvers, UI services, and runtime internals belong in `MissionBay/Api` instead.

This document is normative for every interface currently stored in `AssistantFoundation/src/Api`. Adding another interface to that directory requires adding a corresponding section here, including an implementation example and registration instructions.

## Registration models

AssistantFoundation extension points use three registration models.

### Discoverable configured components

Interfaces extending `Base3\Api\IComponent` are resolved as configured instances through `IComponentResolver`. The implementation class is discoverable through `IClassMap`; a project or implementation plugin contributes a `ComponentDefinition` for each configured instance.

```php
<?php declare(strict_types=1);

use Base3\Core\ComponentDefinition;
use AssistantFoundation\Api\IAgentStage;

$definition = new ComponentDefinition(
    id: 'project-stage',
    interfaceName: IAgentStage::class,
    implementationName: ProjectStage::getName(),
    arguments: [
        'id' => 'project-stage'
    ]
);
```

The concrete bootstrap or plugin decides how that definition is placed in the container. Reusable implementation plugins should not select final project composition.

### Replaceable container services

Service interfaces that do not extend `IComponent` are replaced through the BASE3 container. A project plugin should register the final implementation with `IContainer::NOOVERWRITE` semantics chosen according to the intended precedence.

```php
<?php declare(strict_types=1);

use Base3\Api\IContainer;
use AssistantFoundation\Api\IAgentCapabilitySelector;

$container->set(
    IAgentCapabilitySelector::class,
    fn() => new ProjectCapabilitySelector(),
    IContainer::SHARED
);
```

### Direct adapter contracts

Model, provider, result, memory, and vector contracts are normally implemented by a resource or service class and then injected into the consumer that needs them. Some implementations are also discoverable MissionBay resources, but discovery is not part of the AssistantFoundation contract itself.

## Ownership audit

| Interface | Foundation reason | Extension mechanism | Reference implementation |
|---|---|---|---|
| `IAgentActionPolicy` | project plugins may add semantic action policies | configured component | `MissionBay\Policy\MutationApprovalAgentActionPolicy` |
| `IAgentCapabilityProvider` | plugins may contribute capability bundles | configured component | test example in `AgentCapabilityDiscoveryServiceTest` |
| `IAgentCapabilitySelector` | projects may replace tool-selection strategy | container service | `MissionBay\Capability\HybridAgentCapabilitySelector` |
| `IAgentContext` | shared run context used by external stages, modules, memories, and tools | direct contract/factory | `MissionBay\Agent\AgentContext` |
| `IAgentContextContributor` | plugins may add system-context sources | configured component | `MissionBay\Resource\AgentMemory\Time\TimeMemoryAgentResource` |
| `IAgentConversationMemory` | plugins may add conversation-history backends | resource/direct contract | `MissionBay\Resource\SessionMemoryAgentResource` |
| `IAgentExecutionService` | other plugins execute configured agents without depending on MissionBay internals | container service | `MissionBay\Service\AgentExecutionService` |
| `IAgentMemory` | stable legacy/base memory contract shared by existing plugins | direct contract | `MissionBay\Memory\VolatileMemory` |
| `IAgentModule` | plugins may activate run-local instruction/capability bundles | configured component | test example in `AgentCapabilityDiscoveryServiceTest` |
| `IAgentStage` | plugins may add semantic pipeline stages | configured component | `MissionBay\Orchestrator\Stage\AgentCapabilityDiscoveryStage` |
| `IAgentSuspensionRepository` | projects may replace durable suspension storage | container service | `MissionBay\Orchestrator\Suspension\StateStoreAgentSuspensionRepository` |
| `IAgentToolResultCache` | projects may replace tool-result cache storage | container service | `MissionBay\Cache\StateStoreAgentToolResultCache` |
| `IAiChatModel` | plugins may provide chat-model adapters | direct adapter/resource | `MissionBay\Resource\ConfiguredChatModelAgentResource` |
| `IAiEmbeddingModel` | plugins may provide embedding adapters | direct adapter/resource | `MissionBay\Resource\ConfiguredEmbeddingModelAgentResource` |
| `IAiProvider` | plugins may provide transport/provider implementations | direct adapter | `MissionBay\Transport\OpenAiCompatibleTransport` |
| `IAiResult` | plugins may add provider-neutral result DTOs | result contract | `AssistantFoundation\Dto\AiChatResult` |
| `IAiServiceTester` | plugins may add service health tests | class-map discovery | `AssistantFoundation\Display\AiServiceDashboardDisplay` consumer |
| `IAiTaskService` | other plugins may invoke or replace simple AI-task execution | container service | `MissionBay\Service\MissionBayAiTaskService` |
| `IVectorSearch` | plugins may provide vector-search backends | direct adapter/resource | `MissionBay\Resource\QdrantVectorSearch` |

No MissionBay-only interface remains in AssistantFoundation after this audit. `MissionBay\Api\IAgentStateContext` is the explicit example of a contract that was moved because its lifecycle is owned only by MissionBay.

## `IAgentActionPolicy`

### Use case

Implement this interface when another plugin must allow, deny, require approval for, or otherwise classify semantic agent actions before execution.

### Requirements

- stable lowercase `getName()` for implementation discovery;
- unique configured `id()`;
- deterministic `name()` used by profile configuration;
- truthful `getAiUsage()` declaration;
- provider-neutral `AgentActionDecision` result;
- no direct tool execution inside the policy.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAgent\Policy;

use AssistantFoundation\Api\IAgentActionPolicy;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;

final class ReadOnlyActionPolicy implements IAgentActionPolicy {

    public function __construct(private readonly string $id) {}

    public static function getName(): string {
        return 'readonlyactionpolicy';
    }

    public function id(): string {
        return $this->id;
    }

    public function name(): string {
        return 'read-only-actions';
    }

    public function getDescription(): string {
        return 'Allows read-only actions and denies mutations.';
    }

    public function getAiUsage(): string {
        return self::AI_USAGE_NONE;
    }

    public function evaluate(AgentAction $action, IAgentContext $context): AgentActionDecision {
        $isMutation = (bool)($action->getMetadata()['mutation'] ?? false);

        return $isMutation
            ? AgentActionDecision::deny($action->getId(), 'Mutations are disabled for this agent.')
            : AgentActionDecision::allow($action->getId(), 'Read-only action.');
    }
}
```

Register the implementation in the class map and contribute a `ComponentDefinition` for `IAgentActionPolicy::class`. The orchestrator profile then references the configured component id.

See also `AGENT_ACTION_APPROVAL_AND_RESUME.md` and `AGENT_MUTATION_COMMIT_GUARD.md`.

## `IAgentCapabilityProvider`

### Use case

Implement this interface when a plugin contributes a configured bundle of tools, resource providers, and prompt providers. Typical examples are an MCP server adapter or a project-specific capability package.

### Requirements

- return only capabilities valid for the current `IAgentContext`;
- keep returned objects provider-neutral;
- do not mutate the global class map during a run;
- return empty iterables for unsupported capability kinds;
- use a configured component id so several provider instances can coexist.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAgent\Capability;

use AssistantFoundation\Api\IAgentCapabilityProvider;
use AssistantFoundation\Api\IAgentContext;

final class SupportCapabilityProvider implements IAgentCapabilityProvider {

    public function __construct(
        private readonly string $id,
        private readonly array $tools
    ) {}

    public static function getName(): string {
        return 'supportcapabilityprovider';
    }

    public function id(): string {
        return $this->id;
    }

    public function name(): string {
        return 'support-capabilities';
    }

    public function tools(IAgentContext $context): iterable {
        return $this->tools;
    }

    public function resourceProviders(IAgentContext $context): iterable {
        return [];
    }

    public function promptProviders(IAgentContext $context): iterable {
        return [];
    }
}
```

Register it as a configured component for `IAgentCapabilityProvider::class`. Agent capability-source settings reference the configured id, not the implementation class.

See `AGENT_CAPABILITY_PROVIDERS_AND_MODULES.md`.

## `IAgentCapabilitySelector`

### Use case

Replace this service when a project needs a different deterministic strategy for choosing the bounded tool subset exposed to a model call.

### Requirements

- honor hard include/exclude filters from `AgentCapabilitySelectionRequest`;
- preserve mandatory and always-available tools;
- never select capabilities outside the supplied catalog;
- return explainable scores/reasons where applicable;
- avoid hidden network or model calls unless the project explicitly accepts that cost.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAgent\Capability;

use AssistantFoundation\Api\IAgentCapabilitySelector;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelection;
use AssistantFoundation\Dto\AgentCapabilitySelectionRequest;

final class AllEligibleCapabilitySelector implements IAgentCapabilitySelector {

    public function select(
        AgentCapabilityCatalog $catalog,
        AgentCapabilitySelectionRequest $request
    ): AgentCapabilitySelection {
        $maxTools = $request->getConfig()->getMaxTools();
        $required = array_fill_keys($request->getRequiredToolNames(), true);
        foreach ($request->getConfig()->getAlwaysAvailable() as $name) {
            $required[$name] = true;
        }

        $selected = [];
        foreach ($catalog->all() as $capability) {
            if (isset($required[$capability->getName()]) || $capability->isAlwaysAvailable()) {
                $selected[$capability->getName()] = $capability;
            }
        }
        foreach ($catalog->all() as $capability) {
            if (count($selected) >= $maxTools) {
                break;
            }
            $selected[$capability->getName()] = $capability;
        }

        return new AgentCapabilitySelection(
            iteration: $request->getIteration(),
            strategy: 'all-eligible',
            catalogSize: count($catalog),
            eligibleSize: count($catalog),
            capabilities: array_values($selected),
            scores: [],
            reasons: []
        );
    }
}
```

Register the implementation in the container under `IAgentCapabilitySelector::class` before MissionBay composition is finalized.

See `AGENT_CAPABILITY_CATALOG_AND_SELECTION.md`.

## `IAgentContext`

### Use case

Implement this interface only when a plugin needs a complete alternative execution context. Most extensions should receive the existing context through dependency/method injection rather than replacing it.

### Requirements

- preserve all run-scoped variables until explicitly forgotten;
- return variable keys from `listVars()`;
- provide an `IAgentMemory` compatibility object;
- use a globally unique lowercase `getName()`;
- do not persist secrets in diagnostic variables unless the caller explicitly owns that policy.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAgent\Runtime;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;

final class ProjectAgentContext implements IAgentContext {

    private array $vars = [];

    public function __construct(private IAgentMemory $memory) {}

    public static function getName(): string {
        return 'projectagentcontext';
    }

    public function getMemory(): IAgentMemory {
        return $this->memory;
    }

    public function setMemory(IAgentMemory $memory): void {
        $this->memory = $memory;
    }

    public function setVar(string $key, mixed $value): void {
        $this->vars[$key] = $value;
    }

    public function getVar(string $key): mixed {
        return $this->vars[$key] ?? null;
    }

    public function forgetVar(string $key): void {
        unset($this->vars[$key]);
    }

    public function listVars(): array {
        return array_keys($this->vars);
    }
}
```

A project that replaces the context should also replace the MissionBay context factory in its final project composition. Typed MissionBay-only state access uses `MissionBay\Api\IAgentStateContext`, not another Foundation interface.

## `IAgentContextContributor`

### Use case

Implement this interface for run-local system context such as current time, user preferences, page metadata, account state, or project instructions.

It is not conversation history and must not receive user/assistant message writes.

### Requirements

- return `AgentInstructionBlock` objects only;
- use stable block ids and sources;
- keep content bounded and relevant;
- use `getPriority()` only for deterministic ordering;
- avoid storing hidden reasoning or unrelated historical data.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAgent\Context;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Dto\AgentInstructionBlock;

final class TenantContextContributor implements IAgentContextContributor {

    public function __construct(
        private readonly string $id,
        private readonly string $tenantName
    ) {}

    public static function getName(): string {
        return 'tenantcontextcontributor';
    }

    public function id(): string {
        return $this->id;
    }

    public function getPriority(): int {
        return 30;
    }

    public function contribute(IAgentContext $context): iterable {
        yield new AgentInstructionBlock(
            id: 'tenant-context',
            content: 'Current tenant: ' . $this->tenantName,
            priority: 30,
            source: $this->id
        );
    }
}
```

Register a concrete Component Preset for the implementation and select that preset in a Context Profile.

See `AGENT_MEMORY_AND_CONTEXT.md` and `AGENT_MEMORY_CONTEXT_PROFILES.md`.

## `IAgentConversationMemory`

### Use case

Implement this marker interface for a store that loads and writes visible conversation history. New conversation-memory implementations should use this explicit contract instead of implementing only `IAgentMemory`.

### Requirements

- preserve message order;
- store complete message arrays without rewriting roles/content;
- apply a bounded retention window;
- isolate conversations by the runtime identity supplied to the implementation;
- support reset and feedback consistently;
- do not inject system prompt content.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAgent\Memory;

use AssistantFoundation\Api\IAgentConversationMemory;

final class ArrayConversationMemory implements IAgentConversationMemory {

    private array $messages = [];

    public static function getName(): string {
        return 'arrayconversationmemory';
    }

    public function loadNodeHistory(string $nodeId): array {
        return $this->messages[$nodeId] ?? [];
    }

    public function appendNodeHistory(string $nodeId, array $message): void {
        $this->messages[$nodeId][] = $message;
        $this->messages[$nodeId] = array_slice($this->messages[$nodeId], -20);
    }

    public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
        if (!isset($this->messages[$nodeId])) {
            return false;
        }
        foreach ($this->messages[$nodeId] as &$message) {
            if (($message['id'] ?? null) === $messageId) {
                $message['feedback'] = $feedback;
                return true;
            }
        }
        return false;
    }

    public function resetNodeHistory(string $nodeId): void {
        unset($this->messages[$nodeId]);
    }

    public function getPriority(): int {
        return 80;
    }
}
```

Expose configured implementations as Component Presets and select them through a Memory Profile. Context Contributors belong in a separate Context Profile.

## `IAgentExecutionService`

### Use case

Consume this interface from another plugin when it needs to execute stored agent settings without depending on MissionBay factories, nodes, profile resolvers, or orchestration internals. Replace it only when the project provides a complete alternate execution runtime.

### Example consumer

```php
<?php declare(strict_types=1);

namespace ProjectAgent\Job;

use AssistantFoundation\Api\IAgentExecutionService;

final class ScheduledAgentRunner {

    public function __construct(private readonly IAgentExecutionService $agents) {}

    public function run(array $settings): string {
        $result = $this->agents->run(
            $settings,
            ['prompt' => 'Create the scheduled summary.'],
            ['job_id' => 'nightly-summary']
        );

        $output = $result->getOutput();

        return (string)($output['assistant']['content'] ?? $output['content'] ?? '');
    }
}
```

### Replacement implementation

A replacement must implement all four operations: effective-flow building, buffered execution, streaming execution, and non-fatal warning reporting. Register it in the container under `IAgentExecutionService::class` in the project plugin.

## `IAgentMemory`

### Use case

This is the stable base and compatibility contract for conversation history. Existing plugins may still implement it directly. New conversation stores should implement `IAgentConversationMemory`.

### Rules

- direct `IAgentMemory` implementations are treated as legacy conversation memory;
- Context Contributors must not implement this interface merely to inject system prompt text;
- `getPriority()` orders several conversation stores when a project intentionally configures more than one;
- message arrays must remain provider-neutral.

### Example

`MissionBay\Memory\VolatileMemory` is the minimal reference implementation. The complete implementation is equivalent to the `IAgentConversationMemory` example above but may omit the marker for backward compatibility.

## `IAgentModule`

### Use case

Implement an Agent Module when a plugin needs one activatable run-local bundle containing instructions, tools, resource providers, prompt providers, and optional semantic stage mounts.

### Requirements

- `manifest()` describes stable module identity and operator-facing metadata;
- `activate()` must return a self-contained `AgentModuleActivation` for the current run;
- do not mutate global container or class-map state from `activate()`;
- mounted stages must use supported semantic slots.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAgent\Module;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentModule;
use AssistantFoundation\Dto\AgentModuleActivation;
use AssistantFoundation\Dto\AgentModuleManifest;

final class CodingRulesModule implements IAgentModule {

    public function __construct(private readonly string $id) {}

    public static function getName(): string {
        return 'codingrulesmodule';
    }

    public function id(): string {
        return $this->id;
    }

    public function manifest(): AgentModuleManifest {
        return new AgentModuleManifest(
            name: 'coding-rules',
            title: 'Coding Rules',
            description: 'Adds project coding instructions.'
        );
    }

    public function activate(IAgentContext $context): AgentModuleActivation {
        return new AgentModuleActivation(
            instructions: ['Follow the configured project coding conventions.']
        );
    }
}
```

Register it as a configured component for `IAgentModule::class` and reference the component id in agent capability sources.

See `AGENT_CAPABILITY_PROVIDERS_AND_MODULES.md`.

## `IAgentStage`

### Use case

Implement a stage when a plugin adds a genuine semantic step to the orchestrator pipeline. Do not introduce a stage for a small helper, validation detail, or service that can be called inside an existing semantic stage.

### Requirements

- stable lowercase implementation name and configured id;
- factual description and truthful AI-usage declaration;
- deterministic `supports()` decision based on context state;
- immutable `AgentStageResult` patch;
- no phrase-specific intent regex or language-specific routing;
- mount through a supported core profile position or module stage slot.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAgent\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentStageResult;

final class EvidenceMarkerStage implements IAgentStage {

    public function __construct(private readonly string $id) {}

    public static function getName(): string {
        return 'evidencemarkerstage';
    }

    public function id(): string {
        return $this->id;
    }

    public function name(): string {
        return 'evidence-marker';
    }

    public function getDescription(): string {
        return 'Marks whether the current run has external evidence.';
    }

    public function getAiUsage(): string {
        return self::AI_USAGE_NONE;
    }

    public function supports(IAgentContext $context): bool {
        return $context->getVar('evidence') !== null;
    }

    public function process(IAgentContext $context): AgentStageResult {
        return AgentStageResult::patch(['has_evidence' => true]);
    }
}
```

Register a `ComponentDefinition` for `IAgentStage::class`. The component being available does not automatically activate it; an orchestrator profile or module mount must reference the configured stage id.

See `AGENT_STAGE_PIPELINE.md`.

## `IAgentSuspensionRepository`

### Use case

Replace this repository when a project needs a durable backend for approval/input suspensions other than the default `IStateStore` implementation.

### Requirements

- `create()` returns an opaque, unguessable resume handle;
- `claim()` provides an exclusive short-lived claim or throws the typed repository exception;
- `release()` makes a non-consumed claim available again;
- `consume()` atomically makes the suspension unusable;
- enforce TTL and one-time consumption;
- never expose serialized suspension data in the handle.

### Example skeleton

```php
<?php declare(strict_types=1);

namespace ProjectAgent\Suspension;

use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;

final class DatabaseSuspensionRepository implements IAgentSuspensionRepository {

    public function create(AgentSuspension $suspension, int $ttlSeconds): string {
        $handle = bin2hex(random_bytes(32));
        // Persist suspension, expiry, and unclaimed state transactionally.
        return $handle;
    }

    public function claim(string $resumeHandle): AgentSuspensionClaim {
        // Atomically acquire a short claim lease or throw.
        return $this->loadClaim($resumeHandle);
    }

    public function release(AgentSuspensionClaim $claim): void {
        // Release only when the claim token still matches.
    }

    public function consume(AgentSuspensionClaim $claim): void {
        // Atomically mark consumed and reject future claims.
    }

    private function loadClaim(string $resumeHandle): AgentSuspensionClaim {
        throw new \LogicException('Implement storage-specific claim loading.');
    }
}
```

Register it under `IAgentSuspensionRepository::class` in the project container.

See `AGENT_DURABLE_SUSPENSIONS.md`.

## `IAgentToolResultCache`

### Use case

Replace this service when a project needs a cache backend other than the default state-store adapter or wants to disable caching explicitly.

### Requirements

- return `false` from `isAvailable()` when the backend cannot safely operate;
- preserve the complete `AgentToolCacheEntry` including provenance and expiry;
- treat keys as opaque;
- never cache mutation results;
- make `delete()` idempotent where possible.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAgent\Cache;

use AssistantFoundation\Api\IAgentToolResultCache;
use AssistantFoundation\Dto\AgentToolCacheEntry;

final class ArrayToolResultCache implements IAgentToolResultCache {

    private array $entries = [];

    public function isAvailable(): bool {
        return true;
    }

    public function get(string $key): ?AgentToolCacheEntry {
        return $this->entries[$key] ?? null;
    }

    public function put(string $key, AgentToolCacheEntry $entry, int $ttlSeconds): void {
        $this->entries[$key] = $entry;
    }

    public function delete(string $key): bool {
        $exists = array_key_exists($key, $this->entries);
        unset($this->entries[$key]);
        return $exists;
    }
}
```

Register it under `IAgentToolResultCache::class`.

## `IAiChatModel`

### Use case

Implement this interface for a provider-neutral chat completion adapter. The model may be wrapped as a MissionBay resource, supplied by another plugin, or injected directly into a consumer.

### Requirements

- accept provider-neutral message and tool arrays;
- return `AiChatResult` for non-streaming calls;
- normalize tool calls into `AiToolCall` DTOs;
- expose provider metadata through `AiResultMetadata`;
- honor per-instance options without global mutation;
- stream text/tool deltas through the supplied callbacks.

### Example skeleton

```php
<?php declare(strict_types=1);

namespace ProjectAi\Model;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;

final class ProjectChatModel implements IAiChatModel {

    private array $options = [];

    public function complete(array $messages, array $tools = []): AiChatResult {
        $raw = $this->request($messages, $tools);

        return new AiChatResult(
            content: (string)($raw['content'] ?? ''),
            toolCalls: [],
            metadata: new AiResultMetadata(operation: 'chat', provider: 'project'),
            raw: $raw
        );
    }

    public function chat(array $messages): string {
        return $this->complete($messages)->getContent();
    }

    public function raw(array $messages, array $tools = []): mixed {
        return $this->request($messages, $tools);
    }

    public function streamResult(array $messages, array $tools, callable $onData, callable $onMeta = null): AiChatResult {
        $content = '';
        $this->stream(
            $messages,
            $tools,
            static function(string $delta) use (&$content, $onData): void {
                $content .= $delta;
                $onData($delta);
            },
            $onMeta
        );

        return new AiChatResult(
            content: $content,
            toolCalls: [],
            metadata: new AiResultMetadata(operation: 'chat-stream', provider: 'project')
        );
    }

    public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
        // Convert provider stream events to the declared callbacks.
    }

    public function setOptions(array $options): void {
        $this->options = $options;
    }

    public function getOptions(): array {
        return $this->options;
    }

    private function request(array $messages, array $tools): array {
        return ['content' => ''];
    }
}
```

Prefer extending an existing MissionBay abstract model when the provider is OpenAI-compatible instead of duplicating transport and normalization logic.

## `IAiEmbeddingModel`

### Use case

Implement this interface for an embedding provider or proxy.

### Requirements

- preserve input ordering;
- return one vector per input text;
- use floats in provider-neutral arrays;
- include provider/model metadata in `AiEmbeddingResult`;
- validate dimensions when the provider declares them;
- keep options instance-local.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAi\Model;

use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Dto\AiEmbeddingResult;
use AssistantFoundation\Dto\AiResultMetadata;

final class ProjectEmbeddingModel implements IAiEmbeddingModel {

    private array $options = [];

    public function embedResult(array $texts): AiEmbeddingResult {
        $vectors = array_map(static fn(string $text): array => [0.0, 0.0, 0.0], $texts);

        return new AiEmbeddingResult(
            embeddings: $vectors,
            metadata: new AiResultMetadata(operation: 'embedding', provider: 'project')
        );
    }

    public function embed(array $texts): array {
        return $this->embedResult($texts)->getEmbeddings();
    }

    public function setOptions(array $options): void {
        $this->options = $options;
    }

    public function getOptions(): array {
        return $this->options;
    }
}
```

## `IAiProvider`

### Use case

Implement this interface for a reusable low-level provider transport shared by chat, embedding, image, search, or future model adapters.

### Requirements

- stable lowercase `getName()`;
- instance-local options;
- normalized array response for `request()`;
- streaming callback for `stream()`;
- transport-level authentication and retries only;
- no model-specific prompt policy in the provider.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAi\Transport;

use AssistantFoundation\Api\IAiProvider;

final class ProjectHttpProvider implements IAiProvider {

    private array $options = [];

    public static function getName(): string {
        return 'projecthttpprovider';
    }

    public function setOptions(array $options): void {
        $this->options = $options;
    }

    public function getOptions(): array {
        return $this->options;
    }

    public function request(string $path, array $payload, array $options = []): array {
        // Execute HTTP request and decode the provider response.
        return [];
    }

    public function stream(string $path, array $payload, callable $onChunk, array $options = []): void {
        // Parse provider stream frames and invoke $onChunk.
    }
}
```

Implementations are commonly class-map discoverable and selected by model resources through a provider name.

## `IAiResult`

### Use case

Implement this interface when a plugin introduces another provider-neutral AI result type, for example audio transcription or document generation, while retaining the shared metadata/raw-response boundary.

### Requirements

- `getMetadata()` returns `AiResultMetadata`;
- `getRaw()` may expose provider data to trusted callers;
- `toArray(false)` must omit raw provider data;
- stable provider-neutral fields only in the default array representation.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAi\Dto;

use AssistantFoundation\Api\IAiResult;
use AssistantFoundation\Dto\AiResultMetadata;

final class AiAudioResult implements IAiResult {

    public function __construct(
        private readonly string $transcript,
        private readonly AiResultMetadata $metadata,
        private readonly mixed $raw = null
    ) {}

    public function getMetadata(): AiResultMetadata {
        return $this->metadata;
    }

    public function getRaw(): mixed {
        return $this->raw;
    }

    public function toArray(bool $includeRaw = false): array {
        $result = [
            'transcript' => $this->transcript,
            'metadata' => $this->metadata->toArray()
        ];
        if ($includeRaw) {
            $result['raw'] = $this->raw;
        }
        return $result;
    }
}
```

Result DTOs belong in the foundation only when several plugins exchange them. Provider-private response classes stay in the implementation plugin.

## `IAiServiceTester`

### Use case

Implement this interface when a plugin contributes a quick administrative health test for a configured AI-related service.

### Requirements

- `getType()` identifies the supported service type;
- return an array with at least `ok` and `message`;
- keep the test bounded and non-destructive;
- redact credentials and raw authorization headers;
- do not perform expensive production workloads.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAi\Test;

use AssistantFoundation\Api\IAiServiceTester;

final class ProjectAiServiceTester implements IAiServiceTester {

    public static function getType(): string {
        return 'project-ai';
    }

    public function test(array $config): array {
        return [
            'ok' => isset($config['endpoint']),
            'message' => isset($config['endpoint'])
                ? 'Endpoint configuration is available.'
                : 'Endpoint is missing.',
            'details' => []
        ];
    }
}
```

Register the implementation through the class map under `IAiServiceTester::class`; the AssistantFoundation dashboard discovers testers by interface and type.

## `IAiTaskService`

### Use case

Consume this service from plugins that need one simple system-prompt/user-prompt task without importing MissionBay runtime internals. Replace it when the project uses another execution backend for the same public contract.

### Example implementation

```php
<?php declare(strict_types=1);

namespace ProjectAi\Service;

use AssistantFoundation\Api\IAiTaskService;
use AssistantFoundation\Api\IAiChatModel;

final class DirectAiTaskService implements IAiTaskService {

    public function __construct(private readonly IAiChatModel $model) {}

    public function run(string $systemPrompt, string $userPrompt, array $agentFlow): string {
        return $this->model->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ]);
    }
}
```

Register the project implementation under `IAiTaskService::class`. The `agentFlow` argument must remain accepted even if a replacement intentionally ignores it.

## `IVectorSearch`

### Use case

Implement this interface for a read-only similarity-search backend used by plugins that should not depend on a specific vector database.

### Requirements

- accept a float vector in provider-neutral form;
- return ordered result arrays containing score and payload data;
- honor the result limit and optional minimum score;
- do not embed text inside this contract;
- keep write/index management in a separate implementation API.

### Example

```php
<?php declare(strict_types=1);

namespace ProjectAi\Vector;

use AssistantFoundation\Api\IVectorSearch;

final class ArrayVectorSearch implements IVectorSearch {

    public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
        $results = [
            ['score' => 0.9, 'payload' => ['id' => 'example']]
        ];

        return array_slice(array_values(array_filter(
            $results,
            static fn(array $row): bool => $minScore === null || (float)$row['score'] >= $minScore
        )), 0, $limit);
    }
}
```

Inject the implementation directly or expose it as a configured MissionBay resource. `IAiEmbeddingModel` remains a separate concern.

## Change rule

A future AssistantFoundation interface change requires all of the following in the same patch:

1. exact interface/API change;
2. corresponding section update in this document;
3. example implementation update;
4. migration/compatibility note when signatures change;
5. updated `CHECK_ASSISTANTFOUNDATION_DOCS.sh` report;
6. explicit statement why the contract is plugin-to-plugin rather than MissionBay-internal.

An interface without a concrete extension or replacement scenario belongs in `MissionBay/Api` or should remain a concrete/internal class.

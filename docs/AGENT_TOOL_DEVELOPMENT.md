# MissionBay Agent Tool Development

## Purpose

This document explains how to implement MissionBay agent tools that are safe,
discoverable, understandable to the model, and suitable for direct use in the
Chatbot and component-preset test UI.

It covers:

- the `IAgentTool` contract;
- function definitions and input schemas;
- read-only and mutating annotations;
- approval-bound guarded mutations;
- user-facing mutation reviews;
- technical details and audit binding;
- configured tool wrappers;
- output schemas;
- tests expected from tool implementations.

## 1. Tool class structure

A normal tool implements:

```php
MissionBay\Api\IAgentTool
```

A tool resource commonly extends:

```php
MissionBay\Resource\AbstractAgentResource
```

Minimal example:

```php
<?php declare(strict_types=1);

namespace ExamplePlugin\Tool;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\AbstractAgentResource;

final class ExampleLookupTool extends AbstractAgentResource implements IAgentTool {

	public static function getName(): string {
		return 'examplelookuptool';
	}

	public function getDescription(): string {
		return 'Looks up example records.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Find Example Record',
			'readOnlyHint' => true,
			'mutation' => false,
			'requiresApproval' => false,
			'function' => [
				'name' => 'find_example_record',
				'description' => 'Find one example record by its exact id.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'id' => [
							'type' => 'integer',
							'description' => 'Exact record id.'
						]
					],
					'required' => ['id'],
					'additionalProperties' => false
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		if ($name !== 'find_example_record') {
			throw new \InvalidArgumentException('Unsupported tool: ' . $name);
		}

		return ['id' => (int)$arguments['id']];
	}
}
```

Use stable lowercase technical function names. Human-readable labels belong in
`label`, `description`, review text, or language services.

## 2. Function definitions are the input contract

`getToolDefinitions()` describes every callable function separately. A single
PHP tool class may contain both read-only and mutating functions.

The input schema belongs in:

```text
function.parameters
```

Use it to describe:

- required parameters;
- scalar and structured types;
- enums;
- bounds and formats;
- whether additional properties are allowed;
- exact technical identifiers expected by the function.

Do not use `ISchemaProvider` for function arguments. `ISchemaProvider` describes
the configuration of a resource or component instance.

Do not use `IOutputSchemaProvider` for function arguments.
`IOutputSchemaProvider` describes tool return values indexed by operation name.

## 3. Read-only annotations

A read-only function should explicitly declare:

```php
[
	'readOnlyHint' => true,
	'mutation' => false,
	'requiresApproval' => false
]
```

These annotations are used by capability selection, action policies, caching,
tests, and diagnostics. MissionBay does not infer safety from a function name.

## 4. Mutation annotations

A mutation that requires explicit user approval and final commit validation
should declare:

```php
[
	'readOnlyHint' => false,
	'mutation' => true,
	'requiresApproval' => true,
	'commitGuardRequired' => true,
	'sideEffectHint' => true
]
```

Use:

```php
'destructiveHint' => true
```

for actions such as deletion, uninstallation, irreversible replacement, or
other operations that may remove data. Destructive actions are shown with a
higher risk level.

A mutation with `commitGuardRequired=true` must be provided by a tool that
implements:

```php
MissionBay\Api\IAgentMutationGuardedTool
```

## 5. Complete guarded mutation lifecycle

`IAgentMutationGuardedTool` has three methods:

```text
captureMutationCommitSnapshot()
getActionReview()
validateMutationCommit()
```

They are three phases of the same mutation.

### 5.1 Capture the snapshot

`captureMutationCommitSnapshot()` runs after policy evaluation and before the
approval request is returned.

It should capture:

- the component/resource identity;
- the effective authorization subject;
- the exact mutation plan;
- current resource versions, revisions, hashes, or ETags;
- domain data needed to build the user-facing review.

It may read state. It must not write state.

Example:

```php
public function captureMutationCommitSnapshot(
	AgentAction $action,
	string $actionFingerprint,
	IAgentContext $context
): AgentMutationCommitSnapshot {
	$mutation = $this->resolveMutation($action);
	$record = $this->repository->get($mutation['id']);

	return new AgentMutationCommitSnapshot(
		$action->getId(),
		$actionFingerprint,
		[
			'resource_id' => $this->id(),
			'user_id' => $this->currentUserId()
		],
		[
			'plan' => $this->hashData($mutation),
			'record' => (string)$record->getVersion()
		],
		metadata: [
			'operation' => $mutation['operation'],
			'review' => [
				'record_id' => $record->getId(),
				'record_name' => $record->getName(),
				'current_status' => $record->getStatus(),
				'target_status' => $mutation['status']
			]
		]
	);
}
```

The snapshot is server-owned and stored in the durable suspension. It is not
sent to the browser.

### 5.2 Build the user-facing review

`getActionReview()` receives the exact snapshot captured for the mutation and
returns:

```php
AssistantFoundation\Dto\AgentActionReview
```

The DTO contains only:

```text
title
message
summary
```

Example:

```php
public function getActionReview(
	AgentAction $action,
	AgentMutationCommitSnapshot $snapshot,
	IAgentContext $context
): AgentActionReview {
	$metadata = $snapshot->getMetadata();
	$review = is_array($metadata['review'] ?? null)
		? $metadata['review']
		: null;

	if ($review === null) {
		throw new \RuntimeException('Mutation snapshot has no review data.');
	}

	return new AgentActionReview(
		'Change record status',
		'The status of "' . $review['record_name'] . '" will be changed.',
		[
			'Record' => $review['record_name'],
			'Current status' => $review['current_status'],
			'New status' => $review['target_status']
		]
	);
}
```

Review rules:

- describe what will happen, not which PHP method will run;
- resolve IDs to names when the domain provides a readable name;
- show relevant current and target values;
- use a small directly renderable summary, normally `array<string,string>`;
- do not repeat the entire raw argument object in the summary;
- do not expose snapshot authorization or version hashes;
- do not execute the mutation;
- do not silently invent missing domain data.

The Chatbot and component-preset test UI render `summary` as user-facing
key/value rows. They render the exact function name and original input from
`AgentAction` separately under technical details. Therefore technical IDs do
not need to be duplicated in the primary summary unless they help the user.

A guarded tool must always return a trustworthy review. If review generation
fails, MissionBay fails closed and does not create an approval request.

Localization is intentionally not part of the interface. Tool implementations
may inject their normal language services and create localized review text.

### 5.3 Validate immediately before commit

`validateMutationCommit()` runs inside the execution boundary immediately
before `callTool()`.

It should deny when:

- the snapshot belongs to another action or component instance;
- the authorization identity changed;
- the action plan changed;
- the target version changed;
- the operation is no longer allowed.

Example:

```php
public function validateMutationCommit(
	AgentAction $action,
	AgentMutationCommitSnapshot $snapshot,
	IAgentContext $context
): AgentMutationCommitDecision {
	if ($snapshot->getActionId() !== $action->getId()) {
		return AgentMutationCommitDecision::deny(
			AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
			'Mutation snapshot belongs to another action.'
		);
	}

	$current = $this->repository->get((int)$action->getInput()['id']);
	$expected = (string)($snapshot->getResourceVersions()['record'] ?? '');
	if ($expected === '' || $expected !== (string)$current->getVersion()) {
		return AgentMutationCommitDecision::deny(
			AgentMutationCommitDecision::CODE_STALE,
			'The record changed after approval.'
		);
	}

	return AgentMutationCommitDecision::allow(
		'Authorization and resource version are unchanged.'
	);
}
```

The backend write should still use an atomic version condition when the storage
backend supports it. The harness check does not replace database-level
optimistic locking.

## 6. Mixed read/write tools

The guard interface belongs to the tool class, but it is invoked only for
functions whose definitions declare a guarded mutation.

A mixed tool may therefore expose:

```text
get_settings              read-only
update_settings           guarded mutation
```

`resolveMutation()` and `getActionReview()` should reject unsupported function
names explicitly.

## 7. Configured tool wrappers

`ConfiguredAgentToolResource` may namespace function names. It preserves the
optional mutation capability and delegates all three guard methods.

The wrapper translates only the externally visible function name back to the
original name. It preserves:

- action id;
- action input;
- action metadata;
- outer action fingerprint;
- server-owned snapshot.

Custom wrappers must follow the same rule. A wrapper must not hide or partially
implement optional tool capabilities.

## 8. Output schemas

Implement `Base3\Api\IOutputSchemaProvider` when consumers need a formal schema
for returned tool data.

```php
public function getOutputSchemas(): array {
	return [
		'find_example_record' => [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'name' => ['type' => 'string']
			],
			'required' => ['id', 'name']
		]
	];
}
```

Input and output schemas are separate contracts.

## 9. Error behavior

A tool may throw exceptions or return its established normalized error
structure. Keep behavior consistent within the owning plugin.

Guard and review failures are different from normal tool-result failures:

- snapshot failure: no approval request is created;
- review failure: no approval request is created;
- commit denial: `callTool()` is not invoked;
- normal execution failure: a tool result is produced after approval and guard
  validation.

## 10. Testing checklist

A guarded mutation tool should test at least:

1. all mutation definitions declare approval and commit-guard annotations;
2. read-only definitions do not declare mutation;
3. snapshot action id and fingerprint are preserved;
4. review title, message, and summary are user-facing;
5. technical IDs are resolved where meaningful;
6. the review is built from the captured snapshot state;
7. changed authorization is denied;
8. changed target state/version is denied;
9. approved unchanged state executes exactly once;
10. configured/namespaced wrappers delegate capture, review, and validation;
11. destructive operations set `destructiveHint=true`;
12. the component-preset test UI displays the expected review before execution.

## 11. Reference implementations

Current examples cover different review patterns:

```text
MissionBay\Resource\UserPrefsAgentResource
  preference label, scope, current value, new value

Base3IliasLab\MissionBay\Tools\IliasCronJobAdministrationAgentTool
  technical job id resolved to title, component, and status

Base3IliasLab\MissionBay\Tools\IliasPluginAdministrationAgentTool
  plugin id resolved to name, status, activation state, and versions

Base3IliasLab\MissionBay\Tools\IliasWebDavAdministrationAgentTool
  global before/after values shown as understandable changes
```

See also:

- [AGENT_ACTION_APPROVAL_AND_RESUME.md](AGENT_ACTION_APPROVAL_AND_RESUME.md)
- [AGENT_MUTATION_COMMIT_GUARD.md](AGENT_MUTATION_COMMIT_GUARD.md)
- [AGENT_TOOL_CONTRACT_VALIDATION.md](AGENT_TOOL_CONTRACT_VALIDATION.md)

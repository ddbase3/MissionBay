# MissionBay Agent Tool Contract Validation

## Purpose

MissionBay validates model-generated tool arguments before action policy evaluation and validates successful tool output before it is accepted as an observation or stored in the tool cache.

This is an execution-boundary service, not a configurable stage.

```text
capability-discovery
  -> capability-selection
  -> model-decision
  -> input contract validation
  -> action-policy
  -> cache lookup / final mutation guard / callTool
  -> output contract validation
  -> structural result verification
  -> tool-observation
```

## Why validation happens at two boundaries

Input validation protects both policy evaluation and execution. An action policy should not approve malformed data, and a mutation tool must never receive arguments that do not satisfy its declared contract.

Output validation protects the model context and cache. A tool invocation can return normally while still violating the contract promised to consumers. Such output is converted into a structured failed tool result rather than being exposed as a successful observation.

## Input schema sources

For one matching tool function, `AgentToolContractValidationService` resolves the first declared schema in this order:

```text
function.parameters
function.inputSchema
inputSchema
parameters
```

The standard OpenAI-compatible location remains:

```php
[
	'type' => 'function',
	'function' => [
		'name' => 'update_record',
		'parameters' => [
			'type' => 'object',
			'required' => ['id', 'title'],
			'properties' => [
				'id' => ['type' => 'integer'],
				'title' => ['type' => 'string', 'minLength' => 1]
			],
			'additionalProperties' => false
		]
	]
]
```

Invalid input produces `tool_input_contract_violation`. An invalid declared schema produces `tool_input_schema_invalid`. In both cases MissionBay:

- creates a structured failed `AgentToolResult`;
- skips action-policy evaluation for that malformed action;
- prevents approval, review, cache lookup, and tool execution;
- returns the validation issues to the model as a tool observation so it can correct the call.

## Output schema sources

Output schemas are resolved in this order:

```text
outputSchema
function.outputSchema
IOutputSchemaProvider::getOutputSchemas()[toolName]
```

A tool may declare an output schema directly:

```php
[
	'type' => 'function',
	'outputSchema' => [
		'type' => 'object',
		'required' => ['updated', 'version'],
		'properties' => [
			'updated' => ['type' => 'boolean'],
			'version' => ['type' => 'string']
		]
	],
	'function' => [
		'name' => 'update_record',
		'parameters' => [
			// input schema
		]
	]
]
```

Or it may implement the existing BASE3 contract:

```php
use Base3\Api\IOutputSchemaProvider;

final class RecordAgentTool implements IAgentTool, IOutputSchemaProvider {

	public function getOutputSchemas(): array {
		return [
			'update_record' => [
				'type' => 'object',
				'required' => ['updated', 'version'],
				'properties' => [
					'updated' => ['type' => 'boolean'],
					'version' => ['type' => 'string']
				]
			]
		];
	}
}
```

`ToolGuardAgentTool` and `ConfiguredAgentToolResource` preserve these output contracts while filtering or renaming tool functions.

## Output violation semantics

Invalid output produces `tool_output_contract_violation`. An invalid declared output schema produces `tool_output_schema_invalid`.

MissionBay then:

- emits `tool.error` and a typed failed-tool event;
- returns a failed tool observation containing paths, keywords, codes, and expected types;
- does not expose the invalid raw output to the model;
- does not store the result in the read-only tool cache.

A mutating tool may already have committed its side effect before its returned value is validated. Output validation is therefore not a rollback mechanism. The mutation audit record explicitly states that execution completed but the output contract failed.

## Cache behavior

A read-only cache hit is validated against the current output schema before it is accepted. If a cached value violates the current contract, MissionBay deletes that entry and executes the tool normally. This prevents stale results written under an older schema from bypassing current validation.

Mutation calls continue to bypass the result cache entirely.

## Missing contracts

A missing input or output schema is recorded as `not_declared` and does not block execution. This preserves compatibility with existing tools while making declaration coverage visible in diagnostics.

Projects can later tighten this into a profile policy if they require every tool to expose both contracts.

## Supported schema subset

`JsonSchemaValidator` implements the deterministic subset used by MissionBay tool contracts:

- boolean schemas and local JSON Pointer `$ref` values;
- `type`, nullable unions, `const`, and `enum`;
- `allOf`, `anyOf`, `oneOf`, and `not`;
- object `required`, `properties`, `patternProperties`, `additionalProperties`, `dependentRequired`, `minProperties`, and `maxProperties`;
- array `items`, `prefixItems`, `minItems`, `maxItems`, `uniqueItems`, `contains`, `minContains`, and `maxContains`;
- string `minLength`, `maxLength`, and `pattern`;
- numeric bounds and `multipleOf`.

Validation never coerces values. For example, the string `"5"` does not satisfy an `integer` contract.

The issue list intentionally excludes rejected runtime values. It records paths and type information so validation diagnostics do not copy secrets or large payloads into traces.

## Result diagnostics

Every check creates an immutable `AgentToolContractValidation` record. Orchestrator and assistant-turn results expose these records through:

```text
AgentToolOrchestratorResult::getToolContractValidations()
AgentAssistantTurnResult::getToolContractValidations()
```

Serialized turn diagnostics are also available in the context variable:

```text
orchestrator_tool_contract_validations
```

## Architectural boundary

The service is used by:

```text
AgentActionPolicyStage
  input validation before policy evaluation

AgentToolExecutionStage
  output validation immediately after callTool()

AgentToolResultCacheService
  output validation before accepting a cache hit
```

This keeps the public default pipeline compact while enforcing contracts at the points where bypass would be unsafe.

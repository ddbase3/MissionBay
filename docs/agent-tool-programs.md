# Programmable tool execution

Choose the discoverable `program-tool-loop` orchestrator profile in MissionBay. It uses the existing agent-selected native model decision and canonical stage pipeline. The native default and all other profiles remain unchanged. A custom copy may toggle `model_decision.program_enabled` with `native-capability-model-decision`, positive loop limits, no secondary capability-selection or deliberate planning stage, and no semantic verification. No additional registry, endpoint, job, permission, storage backend or stage is required.

## Selection and contracts

The initial model call sees the compact catalog of eligible capability sources, not all full function definitions. Use `missionbay_select_capability_sources` to select all sources required for the next coherent program. The profile permits up to eight sources and 64 active functions (including mandatory functions), a 48,000-character compact catalog and 32 outer model iterations. These are existing selection parameters, not a second selection mechanism. Source sets can be replaced between programs. The native input definitions are sent once; the additional program instruction supplies the corresponding output schemas only for functions that declare supported contracts. Context size still depends on the actual schemas and model; no token or provider-size guarantee is implied.

A function is program-callable only if it has explicitly shaped input and output schemas supported by the existing JSON schema validator. The input is `function.parameters`; output is `outputSchema` or `IOutputSchemaProvider::getOutputSchemas()`. Unsupported schema assertions, recursive or non-local references and generic batch envelopes are not program-callable. Ordinary calls remain available under their original rules. Each tool owner must describe actual return variants, including domain error values. No guessed schema, implicit conversion of arbitrary text to objects or new tool contract is introduced. Configured resources preserve their effective names and declared contracts; MCP resources use the existing declared schemas and result mapper. `get_current_time` declares its stable return shape at the tool itself.

## Language

Submit a single native tool call to `missionbay_execute_program` with `{ "program": "..." }`, separately from source selection or ordinary calls. The interpreter parses a restricted JavaScript subset, never evaluates arbitrary host code. Allowed constructs are `let`, assignment to local variables, scalar/array/object literals, read-only property/index access, `array.length`, `arrayVariable.push(value);`, arithmetic, strict scalar equality, comparisons, boolean operators with boolean conditions, `if`/`else`, `for (let item of array)`, `break`, `continue`, and `return`. Braces and statement semicolons are required. Functions are static `tools.NAME({...})` or `tools["exact-name"]({...})`. No imports, user-defined functions, recursion, arbitrary methods, network, filesystem or process access. All static references, including unreachable branches, are validated against the active contracts before any call is executed.

Example (illustrative domain functions):

```javascript
let records = tools.list_records({});
if (records.status !== "success") {
	return {error: records.error_code};
}
let processed = [];
let failed = [];
for (let record of records.output.items) {
	if (record.needs_review === true) {
		let result = tools.update_record({id: record.id});
		if (result.status === "success") {
			processed.push(record.id);
		} else {
			failed.push(record.id);
		}
	}
}
return {processed: processed, failed: failed};
```

Each concrete invocation returns the normal serialized `AgentToolResult` fields (`call_id`, `tool`, `arguments`, `status`, `output`, `error_code`, `error_message`, `metadata`). Only public correlation metadata (`label`, `iteration`, `call_index`) is exposed inside the VM; internal approval snapshots are not. `status` describes technical success, not necessarily the domain outcome. Read only properties declared by the actual tool; a missing property, invalid operand or unhandled execution error stops the program. The `+` operator concatenates two strings or a string and a finite number in either order (numbers use JSON number notation). Two numbers are added numerically. No other implicit conversion, hidden retry or transaction rollback is provided. Return a compact summary of domain outcomes and any failures. The parent result includes interpreter status, returned value, execution counters and failed child call IDs/error codes. `completed` means the program returned, not that every domain action succeeded.

## Execution, approvals and logging

The existing `AgentToolExecutionStage` drives the VM. Each yielded call runs through the configured `AgentActionPolicyStage` and ordinary tool-execution path, including capability restrictions, input/output validation, existing cache, budget and commit-guard behavior. The model receives one observation matching the original program call. Concrete results remain in the execution ledger, without unmatched provider tool messages. No additional model call is needed between instructions; existing approval mechanisms may still require interaction.

The existing server-side suspension snapshot retains the program, contract fingerprints, pending call identity, VM position, operand stack, local variables and counters. On resume the original selection and contracts are checked against the current runtime; the normal resume and commit checks remain authoritative. Already executed steps are not rerun on normal approval or denial. This does not create atomic transactions across independent tools or guarantee exactly-once delivery across a host crash. A failed or cancelled program can have already committed changes. Review the recorded outcomes before starting another program.

The parent `missionbay_execute_program` is logged via the normal tool-started and tool-finished or tool-failed events. Its `arguments.program` holds the source, its result holds the return value or partial failure summary. Concrete configured-tool events and action audit entries carry `program_call_id` and `program_step` correlation. A suspended program remains pending until resumed; abandonment follows the existing suspension/log lifecycle. No new log table or retention policy is introduced.

## Interpreter limits

| Limit | Value |
|---|---:|
| Source | 64 KiB |
| Syntax nesting | 16 |
| VM instructions per program | 10,000 |
| Concrete requests per program | 100 |
| Serialized VM state | 2 MiB |
| Cumulative VM serialization work | 256 MiB |
| Cumulative serialized tool results accepted by the VM | 8 MiB |
| Return value | 64 KiB |

Limits persist over suspension. Existing turn/agent budgets remain authoritative; the interpreter does not increase their limits. Timeouts and cancellation use the existing execution mechanisms. Large outputs may already have been produced by a tool before the VM rejects them; tool owners must also bound their own output. Treat code and returned data as untrusted. Review the existing privacy policy for logs and approval state.

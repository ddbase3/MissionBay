# Programmable tool execution

Choose the discoverable `program-tool-loop` orchestrator profile in MissionBay. It uses the existing agent-selected native model decision and canonical stage pipeline. The `auto` orchestrator choice continues to select this profile for agents requiring tool orchestration. The native default and all other profiles remain unchanged. A custom copy may toggle `model_decision.program_enabled` with `native-capability-model-decision`, positive loop limits, no secondary capability-selection or deliberate planning stage, and no semantic verification. No additional registry, endpoint, job, permission, storage backend or stage is required.

## Selection and contracts

The main model receives the complete eligible working set immediately when the configured function count, source count and serialized model-contract budget allow it. The count threshold uses `select_all_threshold` when positive, capped by `max_tools`; zero uses `max_tools` directly in this profile. The built-in limits remain 64 functions, eight selected sources and 48,000 bytes for the serialized active definitions plus program instruction (including supported output contracts and a reserve for the exploration instruction). This is a bounded contract-payload estimate, not a token or provider context guarantee. Conversation history, normal decision instructions, model/provider framing and returned results also consume context.

If the eligible catalog does not fit, the initial working set consists only of mandatory functions. The complete filtered catalog remains on the server. The main model uses `missionbay_execute_program` to explore it, rather than receiving a full source listing or making a separate source-selection model call. `catalog.search()` and `catalog.select()` are two restricted interpreter operations, not additional external model functions or stages. `agent-selected-native` without program execution retains its existing source-selection function, and the existing capability-selection stages remain available to other profiles.

### Explore functions

Use a separate program for exploration. `catalog.search({query, limit, offset})` returns the normal `AgentToolResult` envelope with `status: "success"` and an `output` array of compact records with `name`, `title`, `description`, `category`, and `source_id`. The existing hybrid lexical ranker evaluates the eligible catalog; no other model or vector service is called. Query text is nonempty and at most 512 bytes. The optional limit defaults to 8 and must be 1 to 25. Offset defaults to 0 and must be within the catalog size. No unrelated results are padded into the response. A search does not change the active working set or invoke a domain tool.

```javascript
let orders = catalog.search({query: "order details and line items", limit: 5});
let stock = catalog.search({query: "inventory item weight", limit: 5});
let names = [];
for (let entry of orders.output) {
	names.push(entry.name);
}
for (let entry of stock.output) {
	names.push(entry.name);
}
let selection = catalog.select({names: names});
return selection.output;
```

`catalog.select({names: [...]})` accepts a nonempty list of exact eligible names, deduplicates them and retains mandatory functions. The selected functions can be a small subset of one very large source. The usual active source/function limits and the contract-payload budget apply. It may be called once per exploration program; the selection is applied only upon a successful return, never after a failed or cancelled program. Its result uses the same envelope, with `status: "success"` and `output: {selected_tool_names: [...]}`. Catalog operations reuse `AgentToolResult` and the VM result serializer. They have local correlation IDs and do not enter the domain-call ledger, concrete tool count or separate tool-event stream. Invalid catalog operations still abort the program rather than returning a recoverable tool failure; provisional selections are discarded on any program failure or cancellation. There is no legacy bare-result mode. Existing exploration snippets must use `.output`. This return-contract change affects only non-suspending exploration programs, so the version-2 domain continuation format is unchanged. The next model decision receives the selected native definitions and supported output schema map, so the exploration result need not duplicate the full contracts. Search-only programs may return intermediate compact evidence and explore again. Later exploration replaces rather than accumulates the previous working set.

The VM rejects any program containing both catalog operations and domain function calls, including unreachable branches, before execution. The next program uses the existing statically referenced `tools.NAME` functions with the selected contracts. Ordinary selected calls remain available for operations without complete program contracts. The main model should explore only when the current working set is insufficient. The existing active selection, catalog, policy pipeline, observation, logging and suspension remain the sole authorities. Discovery does not grant authorization. There is no separate proxy registry or search index.

A function is program-callable only if it has explicitly shaped input and output schemas supported by the existing JSON schema validator. The input is `function.parameters`; output is `outputSchema` or `IOutputSchemaProvider::getOutputSchemas()`. Unsupported schema assertions, recursive or non-local references and generic batch envelopes are not program-callable. Ordinary calls remain available under their original rules. Each tool owner must describe actual return variants, including domain error values. No guessed schema, implicit conversion of arbitrary text to objects or new tool contract is introduced. Configured resources preserve their effective names and declared contracts; MCP resources use the existing declared schemas and result mapper. `get_current_time` declares its stable return shape at the tool itself.

## Language

Submit a single native tool call to `missionbay_execute_program` with `{ "program": "..." }`, separately from ordinary calls. Exploration programs use only the two catalog operations; domain programs use the existing selected tool functions. The interpreter compiles a restricted JavaScript subset to bounded VM instructions; it never evaluates arbitrary host code.

### Supported constructs

- JSON scalars, arrays and objects with explicit keys; local `let` variables with initializers; local assignment; read-only property/index access; `array.length`.
- Local `++`, `--`, `+=`, `-=`, `*=`, `/=`, `%=` as statements or classic loop updates, not as value-producing expressions. Increment and decrement require finite numbers.
- `if`/`else`, `for (let item of array)`, `for (let i = 0; i < array.length; i++)`, `break`, `continue`, `return expression;`. Classic loops require a local initializer, condition and update. Braces and statement semicolons are required. Use `return null;` for an intentional empty result. Source without a return is rejected before execution; a runtime path that reaches the end without returning fails.
- Numeric arithmetic, string/number concatenation, strict equality and comparisons, `!`, `&&`, `||`, and the ternary expression `condition ? whenTrue : whenFalse`.
- `arrayVariable.push(value);`, `array.join(separator)`, `array.includes(scalar)`, `array.find(x => x.id === id)`, `array.map(x => x.id)`, `array.filter(x => x.active)`, `array.some(x => x.active)`, `array.every(x => x.active)` and `arrayVariable.sort()`.
- `JSON.stringify(value)` with exactly one JSON value, and template literals such as `Count: ${count}` with scalar interpolation and supported string escapes.

Array callbacks are pure expressions with a single item parameter (`x => expression` or `(x) => expression`) or two parameters (`(x, index) => expression`). Parenthesize object literals returned by callbacks. They may read outer variables, but cannot call tools or catalog operations, assign variables, sort arrays, define functions or run statement blocks. They compile to the same VM loop instructions and share its budgets and cancellation checks. `find` returns the first matching value or `null` when no element matches. `includes` compares scalar values strictly. `some` and `every` short-circuit; empty arrays return false and true respectively. `sort()` is limited to a local array of JSON scalars and no comparator; it updates that local variable and returns the sorted array. It orders scalar text lexicographically by UTF-8 bytes (finite numbers use JSON notation), not numeric order or locale-specific collation. Sorting keys and comparisons consume VM steps and work budget. The VM uses JSON value semantics, not general JavaScript object identity or aliasing.

Domain functions are synchronous static `tools.NAME({...})` or `tools["exact-name"]({...})`; do not use `await`. The only catalog operations are static `catalog.search({...})` and `catalog.select({...})`. No imports, arbitrary functions, recursion, arbitrary methods, property writes, network, filesystem or process access. All static references, including unreachable branches and template interpolations, are validated against the active contracts before any call is executed. A parser diagnostic identifies the line, byte column and byte offset; unsupported methods, invalid callbacks and missing returns provide specific correction guidance. A syntax or contract failure is not evidence of a permission denial or global capability unavailability.

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
return {processed: processed, failed: failed, summary: failed.length === 0 ? "All processed" : failed.join(", ")};
```

Each concrete invocation and successful catalog operation returns the normal serialized `AgentToolResult` fields (`call_id`, `tool`, `arguments`, `status`, `output`, `error_code`, `error_message`, `metadata`). Only public correlation metadata (`label`, `iteration`, `call_index`) is exposed inside the VM; internal approval snapshots are not. `status` describes technical success, not necessarily the domain outcome. Read the declared outer result envelope and any nested domain schema; a missing property or invalid operand stops the program. Property diagnostics identify the attempted short identifier-like key or integer index, the actual value type and, for objects, up to twelve available technical keys. They never dump field values; non-identifier keys and long names are omitted. Keys can themselves contain sensitive data and remain subject to the existing log/model-observation policy. Tools that expose a secondary capability catalog must be used according to their own search, description and invocation contracts. Do not derive imaginary function names or a query language from a requested operation.

Conditions use JavaScript truthiness for JSON values: `null`, `false`, numeric zero and the empty string are false; other strings (including `"0"`), numbers, arrays and objects (including empty arrays and objects) are true. `&&` and `||` short-circuit and return the chosen operand, not a forced boolean. The ternary expression binds below binary operators, associates to the right and evaluates only its chosen branch. Strict equality permits scalar comparisons and comparisons between a scalar and an array/object (always unequal); comparison of two composite values is unsupported. Numeric arithmetic remains strict and finite. The `+` operator concatenates strings and finite numbers in either order using JSON number notation. Template interpolation accepts strings, finite numbers, booleans and null; use an explicit field or `JSON.stringify(value)` for arrays/objects. `join` accepts only string/finite-number elements and a string separator (default comma); joined strings are bounded before allocation. Missing properties are errors, not implicit `undefined` or null. The language is deliberately not a general JavaScript runtime.

Return a compact summary of domain outcomes and failures. The parent result includes interpreter status, returned value, execution counters and failed child call IDs/error codes. `completed` means the program returned, not that every domain action succeeded. No implicit retry or transaction rollback is provided.

## Execution, approvals and logging

The existing `AgentToolExecutionStage` drives the VM. Each yielded call runs through the configured `AgentActionPolicyStage` and ordinary tool-execution path, including capability restrictions, input/output validation, existing cache, budget and commit-guard behavior. The model receives one observation matching the original program call. Concrete results remain in the execution ledger, without unmatched provider tool messages. The existing final-response guard receives that same parent observation when checking verification claims, not the internal read results. The mutation ledger remains authoritative for concrete changes. Return sufficient evidence for a claimed post-condition, not just a completion flag. No additional model call is needed between instructions; existing approval mechanisms may still require interaction. The model is instructed to submit requested actions when the required arguments are known. The orchestrator alone handles approval, presents the existing interaction controls and resumes the pending program. The model must not add conversational approval questions, implement approval logic in the program, treat chat text as approval or retry a denied mutation. Genuine missing task input remains a valid reason to ask a question.

The existing server-side suspension snapshot retains the program, contract fingerprints, pending call identity, VM position, operand stack, local variables and counters. On resume the original selection and contracts are checked against the current runtime; the normal resume and commit checks remain authoritative. Already executed steps are not rerun on normal approval or denial. Language version 2 changes instruction positions and operator semantics. Finish or explicitly cancel all pending version-1 programs before deployment; the existing version guard rejects an incompatible continuation before executing its pending call. No second interpreter or continuation conversion is provided. This does not create atomic transactions across independent tools or guarantee exactly-once delivery across a host crash. A failed or cancelled program can have already committed changes. Review the recorded outcomes before starting another program.

The parent `missionbay_execute_program` is logged via the normal tool-started and tool-finished or tool-failed events. Exploration results include a separate `execution.catalog_operations` count and, after successful selection, `selected_tool_names`. Searches do not count as concrete domain calls. Its `arguments.program` holds the source, its result holds the return value or partial failure summary. Concrete configured-tool events and action audit entries carry `program_call_id` and `program_step` correlation. A suspended program remains pending until resumed; abandonment follows the existing suspension/log lifecycle. No new log table or retention policy is introduced.

## Interpreter limits

| Limit | Value |
|---|---:|
| Source | 64 KiB |
| Syntax nesting | 16 |
| VM instructions per program | 10,000 |
| Concrete requests per program | 100 |
| Catalog operations per exploration program | 20 |
| Search matches per operation | 25 |
| Serialized VM state | 2 MiB |
| Cumulative VM processing work | 256 MiB |
| Cumulative serialized tool results accepted by the VM | 8 MiB |
| Return value | 64 KiB |

All loops, expression callbacks and sort key/comparison operations consume the same instruction budget. Sorting additionally charges scalar key bytes and comparison bytes to the existing work budget. Limits persist over suspension. Existing turn/agent budgets remain authoritative; the interpreter does not increase their limits. Timeouts and cancellation use the existing execution mechanisms. Large outputs may already have been produced by a tool before the VM rejects them; tool owners must also bound their own output. Treat code and returned data as untrusted. Review the existing privacy policy for logs and approval state.

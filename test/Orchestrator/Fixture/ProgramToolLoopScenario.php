<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Orchestrator\Fixture;

use Base3\AssistantFoundation\Dto\AgentBudget;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Dto\AgentSuspension;
use Base3\AssistantFoundation\Dto\AgentSuspensionClaim;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Capability\AgentCapabilityCatalogBuilder;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Dto\Assistant\PreparedAgentResume;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use Base3\MissionBay\Dto\Assistant\AgentExecutionLedger;
use Base3\MissionBay\Orchestrator\AgentToolOrchestratorResult;
use Base3\MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use Base3\MissionBay\Event\MissionBayToolStartedEvent;
use Base3\MissionBay\Event\MissionBayToolFinishedEvent;
use Base3\MissionBay\Event\MissionBayToolFailedEvent;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Program\ToolProgram;
use Base3\MissionBay\Orchestrator\Program\ToolProgramDefinitionService;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use Base3\MissionBay\Policy\MutationApprovalAgentActionPolicy;
use Base3\MissionBay\Test\Orchestrator\AgentSelectedNativeQueueModel;
use Base3\MissionBay\Test\Orchestrator\AgentSelectedNativeSourceToolDouble;
use Base3\MissionBay\Resource\AgentTool\CurrentTime\CurrentTimeAgentTool;
use Base3\MissionBay\Listener\MissionBayToolEventDisplayListener;
use Base3\MissionBay\Orchestrator\Program\ToolProgramParser;
use Base3\MissionBay\Orchestrator\Program\ToolProgramInterpreter;
use Base3\MissionBay\Orchestrator\Program\ToolProgramState;
use Base3\MissionBay\Test\Orchestrator\ApprovalMemoryStateStore;
use Base3\MissionBay\Test\Orchestrator\RecordingEventManager;
use Base3\MissionBay\Test\Policy\Fixture\PolicyTestAgentConfigurations;
use Base3\MissionBay\Test\Service\ComponentPresetTestSuspensionRepository;

final class ProgramToolLoopScenario {
	private const SOURCE = 'let rows = tools.read_records({}); let done = []; for (let id of rows.output.items) { let r = tools.update_record({id:id,title:"reviewed"}); if (r.status === "success") { done.push(id); } } return done;';
	private static function check(bool $ok, string $message): void { if (!$ok) { throw new \RuntimeException($message); } }
	private static function response(string $id, string $name, array $args): AiChatResult {
		return new AiChatResult('', [new AiToolCall($id, $name, $args)], new AiResultMetadata('model_decision', 'test', $id));
	}
	private static function harness(bool $approval, int $extraFunctions = 0, int $descriptionPadding = 0): array {
		$tool = new ProgramToolLoopFixtureTool($extraFunctions, $descriptionPadding);
		$repo = new ComponentPresetTestSuspensionRepository();
		$events = new RecordingEventManager();
		$fingerprint = new AgentActionFingerprint();
		$review = new AgentActionReviewService($fingerprint, $repo, 900, eventManager: $events);
		$resume = new AgentActionResumeService($fingerprint, $repo, $events);
		$policies = [new AllowAllAgentActionPolicy()];
		$ids = ['allow-all-actions'];
		if ($approval) {
			array_unshift($policies, new MutationApprovalAgentActionPolicy(new PolicyTestAgentConfigurations(), $fingerprint, new ApprovalMemoryStateStore()));
			array_unshift($ids, 'mutation-approval-actions');
		}
		$orchestrator = new AgentToolOrchestrator(null, null, [new AgentModelDecisionStage(), new AgentActionPolicyStage(new StaticAgentActionPolicyResolver($policies), $fingerprint, $review, new AgentToolContractValidationService(), new AgentCapabilitySelectionGuardService(), policyIds: $ids), new AgentToolExecutionStage($events, $review), new AgentToolObservationStage()], $resume);
		return [$tool, $orchestrator, $resume, $events];
	}
	private static function runTurn($orchestrator, $tool, $model, $prepared = null, ?AgentBudget $budget = null, ?AgentCapabilitySelectionConfig $selection = null, string $profileId = 'program-tool-loop') {
		$defs = $tool->getToolDefinitions();
		$context = new AgentContext();
		$context->setVar(\Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys::ORCHESTRATOR_PROFILE, $profileId);
		return $orchestrator->run($model, [['role' => 'user', 'content' => 'Read and update all records.']], $defs, [$tool], $context, maxLoops: 32, budget: $budget, resume: $prepared, capabilityCatalog: (new AgentCapabilityCatalogBuilder())->build([$tool], $defs), capabilitySelectionConfig: $selection ?? new AgentCapabilitySelectionConfig(), modelDecisionConfig: AgentModelDecisionConfig::nativeCapability(true));
	}
	private static function startModel(string $source, bool $terminal = true): AgentSelectedNativeQueueModel {
		$results = [self::response('program-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => $source])];
		if ($terminal) { $results[] = self::terminal(); }
		return new AgentSelectedNativeQueueModel($results);
	}
	private static function terminal(): AiChatResult { return new AiChatResult('Completed.', [], new AiResultMetadata('model_decision', 'test', 'terminal')); }
	private static function result($run): array {
		foreach ($run->getToolResults() as $result) { if ($result->getCallId() === 'program-1') { return $result->getOutput(); } }
		throw new \RuntimeException('Missing parent program result: ' . $run->getFailureCode() . ' ' . $run->getFailureMessage());
	}
	private static function runLanguage(): void {
		$parser = new ToolProgramParser();
		$vm = new ToolProgramInterpreter();
		foreach ([
			['return [0,-0.0,"","0",[],{},null,false,true,1].map(x => !x);', [true,true,true,false,false,false,true,true,false,false]],
			['return [0 || 5,"0" || 5,null && 5,[] && 5,false && 1 / 0,"yes" || {}.missing];', [5,'0',null,5,false,'yes']],
			['return "0" ? 1 : 2;', 1],
			['if ({}) { return true; } return false;', true],
			['let u = [{id:7},{id:9}].find(x => x.id === 9); return u !== null && u.id === 9;', true],
			['return [].find(x => ({}).missing);', null],
			['return [{id:1}].find(x => x.id === 2) === null;', true],
			['let offset = 10; return [2,4].map((x,i) => x + i + offset);', [12,15]],
			['return [{id:1},{id:2}].map((x,i) => ({id:x.id,index:i}));', [(object)['id'=>1,'index'=>0],(object)['id'=>2,'index'=>1]]],
			['return [[1,2],[3]].map(x => x.map(y => y + 1));', [[2,3],[4]]],
			['return [1,2,3,4].filter(x => x % 2).map(x => x * 2);', [2,6]],
			['return [].map(x => ({}).missing);', []],
			['return [].every(x => ({}).missing);', true],
			['return [].some(x => ({}).missing);', false],
			['return [1,0].some(x => x || {}.missing);', true],
			['return [0,1].every(x => x && {}.missing);', false],
			['return [1,"1",true,null].includes(1.0);', true],
			['return [1].includes("1");', false],
			['return [{}].includes(null);', false],
			['return [null].includes(null);', true],
			['let i = 8; let sum = 0; for (let i = 0; i < 6; i++) { if (i === 1) { continue; } if (i === 4) { break; } sum += i; } return [i,sum];', [8,5]],
			['let a = []; for (let i = 3; i > 0; --i) { for (let x of [1,2]) { if (x === 1) { continue; } a.push(i*x); } } return a;', [6,4,2]],
			['let x = 8; x--; ++x; x += 2; x -= 1; x *= 2; x /= 3; x %= 4; return x;', 2],
			['let total = 0; for (let i = 0; i < 5; i = i + 2) { total += i; } return total;', 6],
			['let x = 4; return `Count: ${x}, ${true}, ${null}, ${x * 2}`;', 'Count: 4, true, null, 8'],
			['return `outer ${`inner ${{x:2}.x}`} end`;', 'outer inner 2 end'],
			['return `literal \${x}, \`quote\`, \\\\slash, \u00e9`;', 'literal ${x}, `quote`, \\slash, é'],
			["return `a\r\nb\rc`;", "a\nb\nc"],
			['return JSON.stringify({id:4,ok:true,items:[1,2]});', '{"id":4,"ok":true,"items":[1,2]}'],
			['let ids = [10,2,1]; let sorted = ids.sort(); return [ids,sorted];', [[1,10,2],[1,10,2]]],
			['let ids = [2,"2",1,null,false]; return JSON.stringify(ids.sort());', '[1,2,"2",false,null]'],
			['let ids = []; return ids.sort();', []],
			['return null === {} || [] === 0;', false]
		] as [$source, $expected]) {
			$result = $vm->advance($parser->parse($source), new ToolProgramState(), 'language', static fn(): bool => false)['result'];
			self::check(json_encode($result, JSON_THROW_ON_ERROR) === json_encode($expected, JSON_THROW_ON_ERROR), 'Language semantics differ: ' . $source);
		}
		foreach ([
			['let f = x => x; return f;', 'Arrow functions are only supported'],
			['return [1].map(x => { return x; });', 'Array callbacks require one expression'],
			['return [1].map(x => tools.read_records({}));', 'Array callbacks must be pure expressions'],
			['return [1].map(x => false ? catalog.search({query:"read"}) : x);', 'Array callbacks must be pure expressions'],
			['let a = [1]; return a.map(x => a.sort());', 'sort mutates a local array'],
			['return [1].sort();', 'sort() requires a local array variable'],
			['let a = [1]; return a.sort((a,b) => a-b);', 'sort requires no comparator'],
			['return [1].map("x");', 'Expected an identifier'],
			['return `unterminated;', 'Unterminated template literal'],
			['return `invalid ${1;', 'Unterminated template interpolation'],
			['return `bad \\q`;', 'Unsupported template escape'],
			['let r = tools.read_records({});', 'Program has no return statement'],
			['catalog.search({query:"read"})', 'Missing statement semicolon'],
			['{result: 1}', 'An object at statement position is not a return'],
			['return;', 'return requires a JSON value'],
			['return 1 == 1;', 'Use strict equality'],
			['return [1].reduce(x => x);', 'Unsupported method reduce'],
			['let name = "read_records"; return tools[name]({});', 'Expected a literal string'],
			['let x = 1; return x++;', 'Missing statement semicolon']
		] as [$source, $message]) {
			$rejected = false;
			try { $parser->parse($source); } catch (\InvalidArgumentException $e) {
				$rejected = str_contains($e->getMessage(), $message) && str_contains($e->getMessage(), 'at line ') && str_contains($e->getMessage(), 'byte column ');
			}
			self::check($rejected, 'Expected actionable parser diagnostic for: ' . $source);
		}
		foreach ([
			['return [].includes({});', 'includes requires a scalar'],
			['return {items:[]}.map(x => x);', 'map requires an array'],
			['return `value: ${{id:1}}`;', 'Template interpolation requires a scalar'],
			['let x = "1"; x++; return x;', 'Arithmetic requires finite numbers'],
			['let a = [{id:1}]; return a.sort();', 'sort requires scalar array elements'],
			['return {}.missing;', 'Unknown or invalid JSON object property "missing". Available properties: none.'],
			['return {status:"success",output:{}}.ok;', 'property "ok". Available properties: "status", "output".'],
			['return [1].items;', 'Invalid JSON array property "items".'],
			['return [1][4];', 'integer indices 0 to 0'],
			['return null.foo;', 'Cannot read JSON property "foo" from null.'],
			['return [] === [];', 'Compare scalar values'],
			['if (false) { return 1; }', 'Program ended without a return value']
		] as [$source, $message]) {
			$rejected = false;
			try { $vm->advance($parser->parse($source), new ToolProgramState(), 'language', static fn(): bool => false); }
			catch (\RuntimeException $e) { $rejected = str_contains($e->getMessage(), $message); }
			self::check($rejected, 'Expected actionable runtime diagnostic for: ' . $source);
		}
		$diagnostic = '';
		try {
			$source = 'return {status:"fixture-private-value",output:{},"private arbitrary key":1}[' . json_encode(str_repeat('private-key-', 100)) . '];';
			$vm->advance($parser->parse($source), new ToolProgramState(), 'language', static fn(): bool => false);
		} catch (\RuntimeException $e) { $diagnostic = $e->getMessage(); }
		self::check(str_contains($diagnostic, 'non-identifier key omitted') && str_contains($diagnostic, '"status", "output"') && !str_contains($diagnostic, 'fixture-private-value') && !str_contains($diagnostic, 'private arbitrary key') && !str_contains($diagnostic, 'private-key-') && strlen($diagnostic) < 400, 'Property diagnostic exposed values or unbounded keys.');
		foreach ([
			'let i = 0; for (let n = 0; true; n++) { i++; } return i;',
			'return [' . implode(',', array_fill(0, 2000, '0')) . '].map(x => x + 1);',
			'let a = [' . implode(',', range(999, 0)) . ']; return a.sort();'
		] as $source) {
			$state = new ToolProgramState();
			$rejected = false;
			try { $vm->advance($parser->parse($source), $state, 'language', static fn(): bool => false); }
			catch (\RuntimeException $e) { $rejected = str_contains($e->getMessage(), 'instruction limit') && $state->steps === ToolProgram::MAX_STEPS; }
			self::check($rejected, 'Expanded language operation bypassed the VM instruction budget.');
		}
		$state = new ToolProgramState();
		$ticks = 0;
		$rejected = false;
		try { $vm->advance($parser->parse('return [1,2,3,4,5].map(x => x+1);'), $state, 'language', static function() use (&$ticks): bool { return ++$ticks > 20; }); }
		catch (\RuntimeException $e) { $rejected = str_contains($e->getMessage(), 'cancelled'); }
		self::check($rejected, 'Callback execution ignored cancellation.');
	}

	/** @return array<int,array<string,mixed>> */
	private static function guardObservations(array $messages): array {
		$marker = "\n\nCurrent-turn model-visible tool observations (in message order):\n";
		$content = $messages[1]['content'];
		self::check(str_contains($content, $marker), 'Final guard did not receive model-visible observations.');
		$payload = explode("\n\nDraft final response:\n", explode($marker, $content, 2)[1], 2)[0];
		return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
	}

	private static function guardVerdict(bool $approved): AiChatResult {
		return self::response('guard-verdict', 'missionbay_final_response_verdict', [
			'verdict' => $approved ? 'accept' : 'replace',
			'has_unsupported_mutation_claim' => !$approved,
			'reason' => 'Fixture verdict',
			'replacement' => $approved ? '' : 'Change denied.'
		]);
	}

	private static function guardTurn(AgentToolOrchestratorResult $run, AgentSelectedNativeQueueModel $model, bool $approved, bool $stream): array {
		$turn = new AgentAssistantTurnResult($run->getMessages(), ['role' => 'user', 'content' => 'Read and update records.'], [], 'assistant', 'final', false, $run);
		$service = new AgentAssistantFinalResponseService(new AgentAssistantMessageFactory());
		$messagesBefore = $turn->getMessages();
		$callsBefore = count($model->getMessageSets());
		$chunks = [];
		$content = $stream
			? $service->createStreamingResponse($model, $turn, static function(string $chunk) use (&$chunks): void { $chunks[] = $chunk; })
			: $service->createDirectResponse($model, $turn);
		self::check($content === ($approved ? 'Completed.' : 'Change denied.'), 'Final guard verdict was not preserved.');
		self::check(!$stream || $chunks === [$content], 'Buffered final response was not published exactly once.');
		self::check($turn->getMessages() === $messagesBefore, 'Guard changed the existing message context.');
		$expectedCalls = trim($run->getFinalOutputContent()) !== '' ? 1 : 2;
		self::check(count($model->getMessageSets()) === $callsBefore + $expectedCalls && count($turn->getModelResults()) === count($run->getModelResults()) + $expectedCalls, 'Guard introduced an extra model call or lost metadata.');
		$guardIndex = $callsBefore + $expectedCalls - 1;
		self::check(count($model->getToolSets()[$guardIndex]) === 1 && $model->getToolSets()[$guardIndex][0]['function']['name'] === 'missionbay_final_response_verdict', 'Guard exposed a domain tool.');
		return $model->getMessageSets()[$guardIndex];
	}

	private static function runFinalResponseEvidence(): void {
		// Real ordinary calls in the program-enabled profile, before and after HITL.
		// The guard sees both queries in execution order, not only the write ledger.
		foreach ([AgentInteractionResponse::DECISION_APPROVE, AgentInteractionResponse::DECISION_DENY] as $decision) {
			$approved = $decision === AgentInteractionResponse::DECISION_APPROVE;
			[$tool, $orchestrator, $resume] = self::harness(true);
			$model = new AgentSelectedNativeQueueModel([
				self::response('before', 'read_records', []),
				self::response('write', 'update_record', ['id' => 42, 'title' => 'reviewed'])
			]);
			$run = self::runTurn($orchestrator, $tool, $model);
			self::check($run->isSuspended() && $tool->reads === 1 && $tool->mutation->getCallCount() === 0, 'Ordinary action did not suspend before commit.');
			$prepared = $resume->prepare(new AgentResume($run->getResumeHandle(), [new AgentInteractionResponse($run->getInteractionRequests()[0]->getId(), $decision)]));
			$model = new AgentSelectedNativeQueueModel([self::response('after', 'read_records', []), self::terminal(), self::guardVerdict($approved)]);
			$run = self::runTurn($orchestrator, $tool, $model, $prepared);
			self::check($run->isCompleted() && $tool->reads === 2 && $tool->mutation->getCallCount() === ($approved ? 1 : 0), 'Ordinary resume replayed or omitted a call.');
			$messages = self::guardTurn($run, $model, $approved, true);
			$observations = self::guardObservations($messages);
			self::check(array_column($observations, 'call_id') === ['before', 'write', 'after'], 'Ordinary before/write/after observations were not preserved across resume.');
			self::check(array_column($observations, 'execution_index') === [1, 2, 3], 'Ordinary execution order was lost.');
			self::check($observations[0]['content'] === '{"items":[42,43]}' && $observations[2]['content'] === '{"items":[42,43]}', 'Read evidence changed.');
			self::check($observations[1]['status'] === ($approved ? 'success' : 'failure'), 'Mutation decision did not survive resume.');
			$ledger = AgentExecutionLedger::fromEvidence($run->getMutationToolNames(), $run->getToolResults(), $run->getModelDecisionAssessments());
			$mutations = $approved ? $ledger->getSuccessfulMutationCalls() : $ledger->getFailedMutationCalls();
			self::check(count($mutations) === 1 && $mutations[0]['execution_index'] === 2, 'Mutation ledger is not aligned with observed execution order.');
		}

		// A resumed program returns only counts and the domain outcome. The raw
		// internal read results stay in the execution records, not guard messages.
		foreach ([AgentInteractionResponse::DECISION_APPROVE, AgentInteractionResponse::DECISION_DENY] as $decision) {
			$approved = $decision === AgentInteractionResponse::DECISION_APPROVE;
			[$tool, $orchestrator, $resume] = self::harness(true);
			$source = 'let before = tools.read_records({}); let write = tools.update_record({id:42,title:"reviewed"}); let after = tools.read_records({}); return {before_count:before.output.items.length,after_count:after.output.items.length,updated:write.status === "success"};';
			$run = self::runTurn($orchestrator, $tool, self::startModel($source, false));
			self::check($run->isSuspended() && $tool->reads === 1, 'Program action did not suspend.');
			$prepared = $resume->prepare(new AgentResume($run->getResumeHandle(), [new AgentInteractionResponse($run->getInteractionRequests()[0]->getId(), $decision)]));
			$model = new AgentSelectedNativeQueueModel([self::terminal(), self::guardVerdict($approved)]);
			$run = self::runTurn($orchestrator, $tool, $model, $prepared);
			self::check($run->isCompleted() && $tool->reads === 2 && $tool->mutation->getCallCount() === ($approved ? 1 : 0), 'Program resume replayed or omitted a call.');
			$messages = self::guardTurn($run, $model, $approved, false);
			$observations = self::guardObservations($messages);
			self::check(count($observations) === 1 && $observations[0]['call_id'] === 'program-1' && $observations[0]['program'] === true && $observations[0]['execution_index'] === 4, 'Program parent observation lost or internal child exposed.');
			self::check(json_decode($observations[0]['arguments'], true, 512, JSON_THROW_ON_ERROR) === ['program' => $source], 'Visible program call arguments lost.');
			$observed = json_decode($observations[0]['content'], true, 512, JSON_THROW_ON_ERROR);
			self::check($observed['result'] === ['before_count' => 2, 'after_count' => 2, 'updated' => $approved], 'Returned verification summary changed.');
			self::check(!str_contains($messages[1]['content'], '[42,43]') && !str_contains($messages[1]['content'], '\"items\"'), 'Guard reintroduced internal read data.');
		}

		// Replay the membership-test evidence shape without depending on ILIAS or
		// storing any real identities. A stale historical message reuses an ID;
		// unmatched data and raw values must never enter the guard request.
		$before = [['id' => 1, 'role' => 'member'], ['id' => 2, 'role' => 'tutor']];
		$after = array_merge($before, [['id' => 3, 'role' => 'member']]);
		$results = [
			AgentToolResult::success('before', 'members', ['course' => 10], $before),
			AgentToolResult::success('add', 'add_member', ['course' => 10, 'user' => 3], ['added' => true]),
			AgentToolResult::success('middle', 'members', ['course' => 10], $after),
			AgentToolResult::success('remove', 'remove_member', ['course' => 10, 'user' => 3], ['removed' => true, 'previous_role' => 'member']),
			AgentToolResult::success('final', 'members', ['course' => 10], $before),
			AgentToolResult::success('cache', 'members', ['course' => 10], $after, ['cache' => ['hit' => true]]),
			AgentToolResult::failure('failed', 'members', ['course' => 10], 'denied', 'Denied.'),
			AgentToolResult::success('summary', 'members', ['course' => 10, 'secret' => 'HIDDEN_ARGUMENT'], 'HIDDEN_RAW_OUTPUT', ['compaction' => ['applied' => true, 'input_truncated' => true]]),
			AgentToolResult::success('internal', 'members', [], 'HIDDEN_INTERNAL_OUTPUT')
		];
		$messages = [
			['role' => 'user', 'content' => 'Previous turn'],
			['role' => 'tool', 'tool_call_id' => 'final', 'content' => 'OLD_HISTORY'],
			['role' => 'user', 'content' => 'Controlled membership test'],
			['role' => 'tool', 'tool_call_id' => 'unmatched', 'content' => 'UNMATCHED_HISTORY']
		];
		foreach (array_slice($results, 0, -1) as $result) {
			$args = $result->getCallId() === 'summary' ? ['course' => 10] : $result->getArguments();
			$messages[] = \Base3\MissionBay\Ai\AgentChatMessageAdapter::assistantMessage(self::response($result->getCallId(), $result->getToolName(), $args));
			$messages[] = ['role' => 'tool', 'tool_call_id' => $result->getCallId(), 'content' => match ($result->getCallId()) {
				'summary' => 'Two participants, partial summary.',
				'failed' => '{"ok":false,"error":"Denied."}',
				default => json_encode($result->getOutput(), JSON_THROW_ON_ERROR)
			}];
		}
		$run = new AgentToolOrchestratorResult(messages: $messages, finalAssistantMessage: null, completed: true, iterations: 9, finalOutputContent: 'Completed.', finalResponseMode: AgentToolOrchestratorResult::FINAL_RESPONSE_COMPLETE, mutationToolNames: ['add_member', 'remove_member'], toolResults: $results, finalOutputDelivery: AgentToolOrchestratorResult::FINAL_OUTPUT_DELIVERY_BUFFERED);
		$guardMessages = self::guardTurn($run, new AgentSelectedNativeQueueModel([self::guardVerdict(true)]), true, false);
		$observations = self::guardObservations($guardMessages);
		self::check(array_column($observations, 'call_id') === ['before', 'add', 'middle', 'remove', 'final', 'cache', 'failed', 'summary'], 'Guard included unrelated history or omitted current observations.');
		self::check($observations[0]['content'] === $observations[4]['content'] && json_decode($observations[2]['content'], true, 512, JSON_THROW_ON_ERROR) === $after, 'Membership verification evidence not preserved.');
		self::check($observations[5]['cache_hit'] === true && $observations[6]['status'] === 'failure' && $observations[7]['compacted'] === true && $observations[7]['input_truncated'] === true, 'Evidence limitations lost.');
		self::check($observations[7]['content'] === 'Two participants, partial summary.' && json_decode($observations[7]['arguments'], true, 512, JSON_THROW_ON_ERROR) === ['course' => 10], 'Guard did not reuse the visible observation and arguments.');
		foreach (['OLD_HISTORY', 'UNMATCHED_HISTORY', 'HIDDEN_ARGUMENT', 'HIDDEN_RAW_OUTPUT', 'HIDDEN_INTERNAL_OUTPUT'] as $secret) {
			self::check(!str_contains($guardMessages[1]['content'], $secret), 'Guard disclosed data outside the existing observation: ' . $secret);
		}
	}

	public static function run(): void {
		self::runFinalResponseEvidence();
		self::runLanguage();
		[$tool, $orchestrator, $resume, $events] = self::harness(false);
		// Read-only deterministic processing with native model decision and one observation.
		$source = 'let rows = tools.read_records({}); let total = 0; for (let id of rows.output.items) { total = total + id; } return total;';
		$model = self::startModel($source);
		$run = self::runTurn($orchestrator, $tool, $model);
		self::check($run->isCompleted(), 'Read program did not complete: ' . $run->getFailureCode() . ' ' . $run->getFailureMessage());
		$output = self::result($run);
		self::check($output['result'] === 85 && $tool->reads === 1 && count($model->getMessageSets()) === 2, 'Read program result or call count differs.');
		self::check(count($run->getToolResults()) === 2, 'Concrete result missing from ledger.');
		$messages = $model->getMessageSets()[1];
		$ids = array_column(array_filter($messages, static fn(array $m): bool => ($m['role'] ?? '') === 'tool'), 'tool_call_id');
		self::check($ids === ['program-1'], 'Concrete result leaked as unmatched model tool message.');
		$started = array_values(array_filter($events->getFiredEvents(), static fn($e): bool => $e instanceof MissionBayToolStartedEvent));
		$finished = array_values(array_filter($events->getFiredEvents(), static fn($e): bool => $e instanceof MissionBayToolFinishedEvent));
		self::check(count($started) === 1 && $started[0]->getArguments()['program'] === $source && count($finished) === 1 && $finished[0]->getResult()['result'] === 85, 'Program log does not contain source and result.');
		[$tool, $orchestrator] = self::harness(false);
		$run = self::runTurn($orchestrator, $tool, self::startModel('let r = tools.read_records({}); if (false) { tools.unknown({}); } return r;'));
		self::check(self::result($run)['status'] === 'failed' && $tool->reads === 0, 'Whole-program preflight did not reject unknown call.');
		[$tool, $orchestrator] = self::harness(false);
		$run = self::runTurn($orchestrator, $tool, self::startModel('let r = tools.read_records({}); let s = tools.read_records({}); return s;'), budget: new AgentBudget(maxToolCalls: 1));
		self::check($tool->reads === 1 && self::result($run)['status'] === 'failed', 'Existing tool budget did not stop the second call.');
		// Reject unsupported syntax before any effect; the model may repair in the outer loop.
		[$tool, $orchestrator] = self::harness(false);
		$run = self::runTurn($orchestrator, $tool, self::startModel('let r = tools.read_records({}); eval("x"); return r;'));
		self::check($run->isCompleted() && self::result($run)['status'] === 'failed' && $tool->reads === 0, 'Invalid source had effects or prevented the next model decision.');
		// Each output is still validated at the normal tool boundary.
		[$tool, $orchestrator] = self::harness(false);
		$tool->invalidRead = true;
		$run = self::runTurn($orchestrator, $tool, self::startModel('let r = tools.read_records({}); return r.status;'));
		self::check(self::result($run)['result'] === 'failure' && self::result($run)['execution']['failed'] === 1, 'Invalid output reached program as success.');
		// Both authorization revocation and hard-selection withdrawal must stop the pending write.
		foreach ([false, true] as $filterWithdrawal) {
			[$tool, $orchestrator, $resume] = self::harness(true);
			$run = self::runTurn($orchestrator, $tool, self::startModel('let r = tools.read_records({}); let u = tools.update_record({id:42,title:"reviewed"}); return u.status;', false));
			self::check($run->isSuspended(), 'Revocation fixture did not suspend.');
			if (!$filterWithdrawal) { $tool->mutation->setAuthorized(false); }
			$prepared = $resume->prepare(new AgentResume($run->getResumeHandle(), [new AgentInteractionResponse($run->getInteractionRequests()[0]->getId(), AgentInteractionResponse::DECISION_APPROVE)]));
			$run = self::runTurn($orchestrator, $tool, new AgentSelectedNativeQueueModel([self::terminal()]), $prepared, selection: $filterWithdrawal ? new AgentCapabilitySelectionConfig(excludeTools: ['update_record']) : null);
			self::check($tool->reads === 1 && $tool->mutation->getCallCount() === 0, 'Withdrawn action executed or earlier read replayed.');
			$output = self::result($run);
			self::check($filterWithdrawal ? $output['status'] === 'failed' : $output['result'] === 'failure', 'Revocation was not reflected in program result.');
		}
		// Verify the actual listener's record-building path, not just event emission.
		$listenerClass = new \ReflectionClass(MissionBayToolEventDisplayListener::class);
		$listener = $listenerClass->newInstanceWithoutConstructor();
		$recordBuilder = $listenerClass->getMethod('buildToolRecord');
		$record = $recordBuilder->invoke($listener, $finished[0], 'finished', '2026-11-25 12:00:00', []);
		self::check(json_decode($record['arguments_json'], true)['program'] === $source && json_decode($record['result_json'], true)['result'] === 85, 'Tool log record omitted source or result.');
		// Four functions fit directly without a selection model iteration.
		[$tool, $orchestrator] = self::harness(false, 2);
		$model = self::startModel($source);
		$run = self::runTurn($orchestrator, $tool, $model, selection: new AgentCapabilitySelectionConfig(maxTools: 64, selectAllThreshold: 0));
		self::check($run->isCompleted() && self::result($run)['result'] === 85 && count($model->getToolSets()[0]) === 5 && count($model->getToolSets()) === 2, 'Small catalog must execute immediately.');
		self::check(!str_contains(json_encode($model->getMessageSets()[0], JSON_THROW_ON_ERROR), 'catalog.search('), 'Unnecessary exploration instruction for small catalog.');
		// Many functions in ONE source: search, select only one, execute in the ordinary program pipeline.
		[$tool, $orchestrator] = self::harness(false, 2498);
		$search = 'let matches = catalog.search({query:"read records",limit:1}); let names = []; for (let row of matches.output) { names.push(row.name); } return catalog.select({names:names});';
		$model = new AgentSelectedNativeQueueModel([
			self::response('explore-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => $search]),
			self::response('program-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let r = tools.read_records({}); return r.output.items.length;']),
			self::terminal()
		]);
		$config = new AgentCapabilitySelectionConfig(maxTools: 64, selectAllThreshold: 0, sticky: false);
		$run = self::runTurn($orchestrator, $tool, $model, selection: $config);
		$sets = $model->getToolSets();
		self::check($run->isCompleted() && self::result($run)['result'] === 2 && $tool->reads === 1 && $tool->decoyReads === 0 && count($sets) === 3 && count($sets[0]) === 1 && count($sets[1]) === 2, 'Single-source 2500 function catalog failed. ' . $run->getFailureCode() . ' ' . $run->getFailureMessage());
		$initial = json_encode([$sets[0], $model->getMessageSets()[0]], JSON_THROW_ON_ERROR);
		self::check(strlen($initial) < 10000 && !str_contains($initial, 'archive_entry_2497') && !str_contains($initial, 'BASE3-CAPABILITY-SOURCE-CATALOG'), 'Large catalog leaked into initial model context: ' . strlen($initial));
		self::check($tool->definitionReads < 30, 'Contract resolution repeatedly enumerates the entire source.');
		$exploration = array_values(array_filter($run->getToolResults(), static fn($result): bool => $result->getCallId() === 'explore-1'))[0]->getOutput();
		self::check($exploration['execution']['catalog_operations'] === 2 && $exploration['execution']['tool_calls'] === 0 && $exploration['selected_tool_names'] === ['read_records'], 'Exploration executed a domain tool or selected the wrong functions.');
		self::check($exploration['result']->status === 'success' && $exploration['result']->tool === 'catalog.select' && $exploration['result']->output->selected_tool_names === ['read_records'] && $exploration['result']->error_code === '' && $exploration['result']->metadata instanceof \stdClass, 'Selection did not use the normal result envelope.');
		// A model can check status/output using the same idiom as an ordinary call.
		[$tool, $orchestrator, , $events] = self::harness(false, 20);
		$model = new AgentSelectedNativeQueueModel([
			self::response('search-envelope', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let r = catalog.search({query:"read_records",limit:1}); if (r.status !== "success") { return r.error_code; } return r;']),
			self::response('select-envelope', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let s = catalog.select({names:["read_records","update_record"]}); if (s.status !== "success") { return {status:"failed",details:s.error_message}; } if (!s.output || !s.output.selected_tool_names) { return null; } return {status:"success",selected_tool_names:s.output.selected_tool_names};']),
			self::response('program-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let r = tools.read_records({}); if (r.status !== "success") { return null; } return r.output.items.length;']),
			self::terminal()
		]);
		$run = self::runTurn($orchestrator, $tool, $model, selection: new AgentCapabilitySelectionConfig(maxTools:2));
		$byId = [];
		foreach ($run->getToolResults() as $record) { $byId[$record->getCallId()] = $record->getOutput(); }
		$searchResult = $byId['search-envelope']['result'];
		self::check($run->isCompleted() && self::result($run)['result'] === 2 && $tool->reads === 1 && $tool->mutation->getCallCount() === 0, 'Uniform catalog and domain result handling failed.');
		self::check(array_keys(get_object_vars($searchResult)) === ['call_id','tool','arguments','status','output','error_code','error_message','metadata'] && $searchResult->tool === 'catalog.search' && $searchResult->status === 'success' && $searchResult->output[0]->name === 'read_records' && $searchResult->arguments->query === 'read_records' && $searchResult->metadata instanceof \stdClass, 'Search envelope differs from ordinary result contract.');
		self::check($byId['search-envelope']['execution']['tool_calls'] === 0 && $byId['select-envelope']['execution']['tool_calls'] === 0 && $byId['search-envelope']['execution']['catalog_operations'] === 1 && $byId['select-envelope']['selected_tool_names'] === ['read_records','update_record'] && count($model->getToolSets()[1]) === 1 && count($model->getToolSets()[2]) === 3, 'Catalog operation was counted as a domain call or selection changed too early.');
		$catalogEvents = array_filter($events->getFiredEvents(), static fn($event): bool => ($event instanceof MissionBayToolStartedEvent || $event instanceof MissionBayToolFinishedEvent || $event instanceof MissionBayToolFailedEvent) && in_array($event->getToolName(), ['catalog.search','catalog.select'], true));
		self::check($catalogEvents === [], 'Internal catalog operation was logged as a concrete tool.');
		// A property error after select must still discard the provisional selection.
		[$tool, $orchestrator] = self::harness(false, 20);
		$model = self::startModel('let s = catalog.select({names:["read_records"]}); return s.ok;');
		$run = self::runTurn($orchestrator, $tool, $model, selection: new AgentCapabilitySelectionConfig(maxTools:1));
		$output = self::result($run);
		self::check($output['status'] === 'failed' && str_contains($output['error'], 'property "ok"') && str_contains($output['error'], '"status", "output"') && !isset($output['selected_tool_names']) && count($model->getToolSets()[1]) === 1 && $tool->reads === 0, 'Failed catalog selection was committed or lacked an actionable diagnostic.');
		[$tool, $orchestrator] = self::harness(false);
		$run = self::runTurn($orchestrator, $tool, self::startModel('let r = tools.read_records({}); return r.ok;'));
		$output = self::result($run);
		self::check($output['status'] === 'failed' && $output['execution']['successful'] === 1 && $tool->reads === 1 && str_contains($output['error'], 'property "ok"') && str_contains($output['error'], '"status", "output"'), 'Domain result error lost its property diagnostic or execution accounting.');
		// A single broad schema also triggers exploration even when the count is small.
		[$tool, $orchestrator] = self::harness(false, 1, 12000);
		$model = new AgentSelectedNativeQueueModel([self::response('explore-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => $search]), self::response('program-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let r = tools.read_records({}); return r.output.items.length;']), self::terminal()]);
		$run = self::runTurn($orchestrator, $tool, $model, selection: new AgentCapabilitySelectionConfig(semanticMaxPromptCharacters: 8000));
		self::check($run->isCompleted() && self::result($run)['result'] === 2 && count($model->getToolSets()[0]) === 1, 'Schema-size threshold did not trigger exploration.');
		// The old multi-source stress case now exercises exact function reselection.
		[$tool, $orchestrator] = self::harness(false);
		$tools = [$tool];
		for ($i = 0; $i < 100; $i++) {
			$names = [];
			for ($j = 0; $j < 10; $j++) { $names[] = 'func_' . $i . '_' . $j; }
			$tools[] = new AgentSelectedNativeSourceToolDouble('source-' . $i, 'Source ' . $i, 'Fixture for selection', $names);
		}
		$definitions = [];
		foreach ($tools as $candidate) { $definitions = array_merge($definitions, $candidate->getToolDefinitions()); }
		$model = new AgentSelectedNativeQueueModel([
			self::response('explore-first', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let rows = catalog.search({query:"func_99_9",limit:1}); return catalog.select({names:[rows.output[0].name]});']),
			self::response('untyped-call', 'func_99_9', ['query' => 'test']),
			self::response('explore-next', ToolProgramDefinitionService::TOOL_NAME, ['program' => $search]),
			self::response('program-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let r = tools.read_records({}); return r.output.items.length;']),
			self::terminal()
		]);
		$run = $orchestrator->run($model, [['role' => 'user', 'content' => 'Count records.']], $definitions, $tools, new AgentContext(), maxLoops: 32, capabilityCatalog: (new AgentCapabilityCatalogBuilder())->build($tools, $definitions), capabilitySelectionConfig: $config, modelDecisionConfig: AgentModelDecisionConfig::nativeCapability(true));
		$sets = $model->getToolSets();
		self::check($run->isCompleted() && self::result($run)['result'] === 2 && count($sets[0]) === 1 && count($sets[1]) === 2 && count($sets[3]) === 2 && !str_contains(json_encode($sets[3], JSON_THROW_ON_ERROR), 'func_99_9'), 'Large multi-source catalog selection or reselection failed. ' . $run->getFailureCode() . ' ' . $run->getFailureMessage());
		// No search leaks or calls to excluded functions, no selection on failure,
		// and no effects from mixed exploration/domain programs even in dead branches.
		foreach ([
			'return catalog.select({names:["update_record"]});',
			'let s = catalog.select({names:["read_records"]}); return "x" * 2;',
			'let s = catalog.search({query:"read records"}); if (false) { tools.read_records({}); } return s;',
			'return catalog.search({query:"read records",limit:26});',
			'return catalog.search({query:"read records",limit:null});',
			'for (let n of [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21]) { let r = catalog.search({query:"read records",limit:1}); } return true;'
		] as $bad) {
			[$tool, $orchestrator] = self::harness(false, 20);
			$model = self::startModel($bad);
			$run = self::runTurn($orchestrator, $tool, $model, selection: new AgentCapabilitySelectionConfig(maxTools: 1, excludeTools: ['update_record']));
			self::check($run->isCompleted() && self::result($run)['status'] === 'failed' && $tool->reads === 0 && $tool->mutation->getCallCount() === 0 && count($model->getToolSets()[1]) === 1, 'Invalid exploration changed selection or invoked a tool: ' . $bad);
		}
		[$tool, $orchestrator] = self::harness(false, 20);
		$run = self::runTurn($orchestrator, $tool, self::startModel('return catalog.search({query:"update_record",limit:5});'), selection: new AgentCapabilitySelectionConfig(maxTools:1, excludeTools: ['update_record']));
		self::check(self::result($run)['result']->status === 'success' && self::result($run)['result']->output === [], 'Search returned an excluded function.');
		[$tool, $orchestrator] = self::harness(false, 20);
		$model = new AgentSelectedNativeQueueModel([self::response('explore-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'return catalog.select({names:["archive_entry_19"]});']), self::terminal()]);
		$run = self::runTurn($orchestrator, $tool, $model, selection: new AgentCapabilitySelectionConfig(maxTools:2, alwaysAvailable:['read_records']));
		self::check($run->isCompleted() && count($model->getToolSets()[0]) === 2 && count($model->getToolSets()[1]) === 3, 'Mandatory function was not retained in new working set.');
		// The function selection is retained by the original suspension. Hard filters
		// and the concrete mutation's authorization are checked again before resume.
		foreach ([false, true] as $withdraw) {
			[$tool, $orchestrator, $resume] = self::harness(true, 2498);
			$model = new AgentSelectedNativeQueueModel([
				self::response('explore-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let reads = catalog.search({query:"read_records",limit:1}); let writes = catalog.search({query:"update_record",limit:1}); return catalog.select({names:[reads.output[0].name,writes.output[0].name]});']),
				self::response('program-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let r = tools.read_records({}); let u = tools.update_record({id:42,title:"reviewed"}); return u.status;'])
			]);
			$run = self::runTurn($orchestrator, $tool, $model, selection: $config);
			self::check($run->isSuspended() && $tool->reads === 1 && $tool->mutation->getCallCount() === 0, 'Large catalog approval was bypassed.');
			$prepared = $resume->prepare(new AgentResume($run->getResumeHandle(), [new AgentInteractionResponse($run->getInteractionRequests()[0]->getId(), AgentInteractionResponse::DECISION_APPROVE)]));
			self::check($prepared->getSuspension()->getState()['selected_tool_names'] === ['read_records', 'update_record'], 'Suspension lost the selected function set.');
			$run = self::runTurn($orchestrator, $tool, new AgentSelectedNativeQueueModel([self::terminal()]), $prepared, selection: $withdraw ? new AgentCapabilitySelectionConfig(maxTools:64, selectAllThreshold:0, excludeTools:['update_record']) : $config);
			self::check($tool->reads === 1 && $tool->mutation->getCallCount() === ($withdraw ? 0 : 1), 'Large-catalog resume replayed a read or bypassed withdrawal.');
			self::check($withdraw ? self::result($run)['status'] === 'failed' : self::result($run)['result'] === 'success', 'Unexpected large-catalog resume result.');
		}
		[$tool, $orchestrator] = self::harness(false, 2, 12000);
		$model = self::startModel('return catalog.select({names:["archive_entry_0"]});');
		$run = self::runTurn($orchestrator, $tool, $model, selection: new AgentCapabilitySelectionConfig(semanticMaxPromptCharacters:8000));
		self::check(self::result($run)['status'] === 'failed' && count($model->getToolSets()[1]) === 1, 'Oversized selection was published.');
		[$tool, $orchestrator] = self::harness(false, 20);
		$run = self::runTurn($orchestrator, $tool, self::startModel('return catalog.select({names:["read_records","update_record"]});'), selection: new AgentCapabilitySelectionConfig(maxTools:1));
		self::check(self::result($run)['status'] === 'failed', 'Selection exceeded maxTools.');
		// Verify a real built-in tool contract and its return value.
		$time = new CurrentTimeAgentTool();
		$contracts = new AgentToolContractValidationService();
		self::check($contracts->getProgramContract('get_current_time', [$time]) !== null && $contracts->validateOutput(new AiToolCall('time', 'get_current_time', []), $time->callTool('get_current_time', [], new AgentContext()), [$time])->isValidated(), 'Current time contract failed.');
		// Pure interpreter control flow and forbidden syntax.
		$parser = new ToolProgramParser();
		$vm = new ToolProgramInterpreter();
		$program = $parser->parse('let total = 0; for (let i of [1,2,3,4,5]) { if (i === 2) { continue; } if (i === 4) { break; } total = total + i; } return total;');
		self::check($vm->advance($program, new ToolProgramState(), 'local', static fn(): bool => false)['result'] === 4, 'VM loop control failed.');
		self::check($vm->advance($parser->parse('let catalog = {value:42}; return catalog.value;'), new ToolProgramState(), 'local', static fn(): bool => false)['result'] === 42, 'Existing catalog variable syntax changed.');
		// Mixed string/number concatenation in real tool-return processing.
		[$tool, $orchestrator] = self::harness(false);
		$run = self::runTurn($orchestrator, $tool, self::startModel('let r = tools.read_records({}); let reason = ""; for (let id of r.output.items) { reason = reason + "item " + id + ";"; } return reason;'));
		self::check(self::result($run)['result'] === 'item 42;item 43;' && $tool->reads === 1, 'String/number concatenation failed for tool output.');
		foreach ([
			['return "count: " + 4;', 'count: 4'],
			['return 4 + " items";', '4 items'],
			['return "cost: " + 1.25;', 'cost: 1.25'],
			['return "4" + 2;', '42'],
			['return "a" + "b";', 'ab'],
			['return 4 + 2;', 6],
			['return 4 * 2;', 8]
		] as [$source, $expected]) {
			self::check($vm->advance($parser->parse($source), new ToolProgramState(), 'local', static fn(): bool => false)['result'] === $expected, 'Program addition or concatenation semantics differ.');
		}
		foreach (['return "4" * 2;', 'return "x" + true;', 'return "x" + null;', 'return "x" + [];', 'return {} + "x";'] as $source) {
			$rejected = false;
			try {
				$vm->advance($parser->parse($source), new ToolProgramState(), 'local', static fn(): bool => false);
			} catch (\RuntimeException $e) {
				$rejected = $e->getMessage() === 'Arithmetic requires finite numbers.';
			}
			self::check($rejected, 'Unsupported arithmetic or coercion was accepted.');
		}
		// Conditional expressions and bounded join, including the forms used in the live fulfillment programs.
		foreach ([
			['return true ? 4 : 2;', 4],
			['return false ? 4 : 2;', 2],
			['return 1 ? 2 : 3;', 2],
			['return true || false ? 1 + 2 * 3 : 0;', 7],
			['return !false && 1 + 2 === 3 ? 7 : 8;', 7],
			['return true ? false ? 1 : 2 : 3;', 2],
			['return false ? 1 : true ? 2 : 3;', 2],
			['return (false ? 1 : 2) + 3;', 5],
			['return true ? 7 : 1 / 0;', 7],
			['return false ? {}.missing : null;', null],
			['let x = {available:true,price:600}; return {cost:x.available ? x.price : null}.cost;', 600],
			['let x = {available:false,price:600}; return [x.available ? x.price : null][0];', null],
			['return ["a", "b"].join(", ");', 'a, b'],
			['return ["item", 4, 1.25].join(" / ");', 'item / 4 / 1.25'],
			['return [1, 2, 3].join();', '1,2,3'],
			['return [].join();', ''],
			['return ["only"].join("!");', 'only'],
			['return ["a", "b"].join("");', 'ab'],
			['return ["a", "b"].join(true ? "; " : ", ");', 'a; b'],
			['return {join:"ordinary property"}.join;', 'ordinary property']
		] as [$expression, $expected]) {
			self::check($vm->advance($parser->parse($expression), new ToolProgramState(), 'local', static fn(): bool => false)['result'] === $expected, 'Conditional expression or join semantics differ: ' . $expression);
		}
		foreach ([
			['return [true].join();', 'join requires strings or finite numbers as array elements.'],
			['return [null].join();', 'join requires strings or finite numbers as array elements.'],
			['return [[1]].join();', 'join requires strings or finite numbers as array elements.'],
			['return [{}].join();', 'join requires strings or finite numbers as array elements.'],
			['return [1].join(2);', 'join requires an array and a string separator.'],
			['return "text".join();', 'join requires an array and a string separator.'],
			['let sep = "x"; for (let i of [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18]) { sep = sep + sep; } return [0,1,2,3,4,5,6,7,8,9].join(sep);', 'Joined string exceeds the program state limit.']
		] as [$expression, $message]) {
			$rejected = false;
			try {
				$vm->advance($parser->parse($expression), new ToolProgramState(), 'local', static fn(): bool => false);
			} catch (\RuntimeException $e) {
				$rejected = $e->getMessage() === $message;
			}
			self::check($rejected, 'Invalid or oversized conditional/join expression was accepted: ' . $expression);
		}
		foreach (['return true ? 1;', 'return true ?: 1;', 'return [1].join(",", ".");', 'return [1].map("x");'] as $expression) {
			$rejected = false;
			try {
				$parser->parse($expression);
			} catch (\InvalidArgumentException $e) {
				$rejected = true;
			}
			self::check($rejected, 'Unsupported conditional or method syntax was accepted: ' . $expression);
		}
		// Both branches must be parsed and their tool contracts verified, even when unreachable.
		[$tool, $orchestrator] = self::harness(false);
		$run = self::runTurn($orchestrator, $tool, self::startModel('return true ? tools.read_records({}) : tools.unknown({});'));
		self::check(self::result($run)['status'] === 'failed' && $tool->reads === 0, 'Conditional preflight did not reject an unknown function.');
		[$tool, $orchestrator] = self::harness(false);
		$run = self::runTurn($orchestrator, $tool, self::startModel('return false ? catalog.search({query:"read"}) : tools.read_records({});'));
		self::check(self::result($run)['status'] === 'failed' && $tool->reads === 0, 'Conditional bypassed the catalog/domain separation.');
		[$tool, $orchestrator] = self::harness(true);
		$model = self::startModel('let records = tools.read_records({}); return records.status === "success" ? records.output.items.join(", ") : tools.update_record({id:42,title:"unreachable"});');
		$run = self::runTurn($orchestrator, $tool, $model);
		self::check($run->isCompleted() && self::result($run)['result'] === '42, 43' && $tool->reads === 1 && $tool->mutation->getCallCount() === 0, 'Unchosen mutation branch executed or requested approval.');
		self::check(str_contains(json_encode($model->getMessageSets()[0], JSON_THROW_ON_ERROR), 'condition ? whenTrue : whenFalse') && str_contains(json_encode($model->getMessageSets()[0], JSON_THROW_ON_ERROR), 'array.join(separator)'), 'Model instruction omitted supported syntax.');
		// The selected conditional branch can suspend and resume via the original policy pipeline.
		foreach ([AgentInteractionResponse::DECISION_APPROVE, AgentInteractionResponse::DECISION_DENY] as $decision) {
			[$tool, $orchestrator, $resume] = self::harness(true);
			$run = self::runTurn($orchestrator, $tool, self::startModel('let r = tools.read_records({}); let status = r.status === "success" ? tools.update_record({id:42,title:"reviewed"}).status : "skipped"; return [status, r.output.items.join()].join(": ");', false));
			self::check($run->isSuspended() && $tool->reads === 1 && $tool->mutation->getCallCount() === 0, 'Selected conditional mutation was not suspended.');
			$prepared = $resume->prepare(new AgentResume($run->getResumeHandle(), [new AgentInteractionResponse($run->getInteractionRequests()[0]->getId(), $decision)]));
			$run = self::runTurn($orchestrator, $tool, new AgentSelectedNativeQueueModel([self::terminal()]), $prepared);
			$approved = $decision === AgentInteractionResponse::DECISION_APPROVE;
			self::check($run->isCompleted() && self::result($run)['result'] === ($approved ? 'success: 42,43' : 'failure: 42,43') && $tool->reads === 1 && $tool->mutation->getCallCount() === ($approved ? 1 : 0), 'Conditional branch did not resume correctly.');
		}
		[$tool, $orchestrator, $resume] = self::harness(true);
		$run = self::runTurn($orchestrator, $tool, self::startModel(self::SOURCE, false));
		self::check($run->isSuspended(), 'Profile mismatch fixture did not suspend.');
		$prepared = $resume->prepare(new AgentResume($run->getResumeHandle(), [new AgentInteractionResponse($run->getInteractionRequests()[0]->getId(), AgentInteractionResponse::DECISION_APPROVE)]));
		$run = self::runTurn($orchestrator, $tool, new AgentSelectedNativeQueueModel([]), $prepared, profileId: 'native-tool-loop');
		self::check($run->getFailureCode() === 'orchestrator_profile_changed' && $tool->mutation->getCallCount() === 0, 'Changed profile did not reject resume before mutation.');
		foreach ([AgentInteractionResponse::DECISION_APPROVE, AgentInteractionResponse::DECISION_DENY] as $decision) {
			[$tool, $orchestrator, $resume, $events] = self::harness(true);
			$run = self::runTurn($orchestrator, $tool, self::startModel(self::SOURCE, false));
			self::check($run->isSuspended() && $tool->reads === 1 && $tool->mutation->getCallCount() === 0, 'First mutation not suspended. ' . $run->getFailureCode() . ' ' . $run->getFailureMessage());
			$prepared = $resume->prepare(new AgentResume($run->getResumeHandle(), [new AgentInteractionResponse($run->getInteractionRequests()[0]->getId(), $decision)]));
			self::check(isset($prepared->getSuspension()->getState()['program_state']['vm']), 'Interpreter state missing in suspension.');
			self::check($prepared->getSuspension()->getState()['orchestrator_profile'] === 'program-tool-loop', 'Effective profile missing from suspension.');
			$run = self::runTurn($orchestrator, $tool, new AgentSelectedNativeQueueModel([]), $prepared);
			self::check($run->isSuspended() && $tool->reads === 1, 'Second approval missing or first read replayed. ' . $run->getFailureCode() . ' ' . $run->getFailureMessage());
			$prepared = $resume->prepare(new AgentResume($run->getResumeHandle(), [new AgentInteractionResponse($run->getInteractionRequests()[0]->getId(), $decision)]));
			$model = new AgentSelectedNativeQueueModel([self::terminal()]);
			$run = self::runTurn($orchestrator, $tool, $model, $prepared);
			$expected = $decision === AgentInteractionResponse::DECISION_APPROVE ? [42, 43] : [];
			self::check($run->isCompleted() && self::result($run)['result'] === $expected && $tool->reads === 1 && $tool->mutation->getCallCount() === count($expected) && count($model->getMessageSets()) === 1, 'Approval/denial resume failed. ' . $run->getFailureCode() . ' ' . $run->getFailureMessage());
		}
		// Catalog discovery uses the same pure callback lowering as domain programs.
		[$tool, $orchestrator] = self::harness(false, 20);
		$model = new AgentSelectedNativeQueueModel([
			self::response('explore-map', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let matches = catalog.search({query:"read_records",limit:1}); return catalog.select({names:matches.output.map(x => x.name)});']),
			self::response('program-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let r = tools.read_records({}); return r.output.items.map(x => x + 1);']),
			self::terminal()
		]);
		$run = self::runTurn($orchestrator, $tool, $model, selection: new AgentCapabilitySelectionConfig(maxTools:1));
		self::check($run->isCompleted() && self::result($run)['result'] === [43,44] && $tool->reads === 1, 'Callback catalog selection failed.');
		// Invalid source still has no effects, including a call hidden in template interpolation.
		foreach ([
			'let r = tools.read_records({}); return [1].map(x => tools.update_record({id:x,title:"wrong"}));',
			'let r = tools.read_records({}); return `value ${true ? 1 : tools.unknown({})}`;',
			'let r = tools.read_records({});'
		] as $source) {
			[$tool, $orchestrator] = self::harness(false);
			$run = self::runTurn($orchestrator, $tool, self::startModel($source));
			self::check(self::result($run)['status'] === 'failed' && $tool->reads === 0 && $tool->mutation->getCallCount() === 0, 'Expanded language bypassed whole-program preflight.');
		}
		// The classic loop, pure array operations, truthiness and templates survive two HITL pauses.
		foreach ([AgentInteractionResponse::DECISION_APPROVE, AgentInteractionResponse::DECISION_DENY] as $decision) {
			[$tool, $orchestrator, $resume] = self::harness(true);
			$source = 'let r = tools.read_records({}); if (r.status !== "success" || !r.output) { return null; } let ids = r.output.items.filter(x => x > 0).map(x => x); let done = []; for (let i = 0; i < ids.length; i++) { let id = ids[i]; let result = tools.update_record({id:id,title:`reviewed-${id}`}); if (result.status === "success") { done.push(id); } } return {done:done,all:ids.every(x => done.includes(x)),summary:`Done: ${done.length}`};';
			$model = self::startModel($source, false);
			$run = self::runTurn($orchestrator, $tool, $model);
			$instruction = json_encode($model->getMessageSets()[0], JSON_THROW_ON_ERROR);
			self::check(str_contains($instruction, 'HITL is solely the orchestrator') && str_contains($instruction, 'never conversational approval'), 'Approval responsibility not conveyed to the native program model.');
			self::check($run->isSuspended() && $tool->reads === 1 && $tool->mutation->getCallCount() === 0, 'First classic-loop mutation did not suspend.');
			for ($i = 0; $i < 2; $i++) {
				$prepared = $resume->prepare(new AgentResume($run->getResumeHandle(), [new AgentInteractionResponse($run->getInteractionRequests()[0]->getId(), $decision)]));
				self::check($prepared->getSuspension()->getState()['program_state']['version'] === ToolProgram::VERSION, 'New language version not persisted.');
				$run = self::runTurn($orchestrator, $tool, new AgentSelectedNativeQueueModel($i === 0 ? [] : [self::terminal()]), $prepared);
				self::check($tool->reads === 1 && $tool->mutation->getCallCount() === ($decision === AgentInteractionResponse::DECISION_APPROVE ? $i+1 : 0), 'Classic-loop resume replayed or bypassed an action.');
				self::check($i === 0 ? $run->isSuspended() : $run->isCompleted(), 'Unexpected classic-loop resume state.');
			}
			$result = (array)self::result($run)['result'];
			$approved = $decision === AgentInteractionResponse::DECISION_APPROVE;
			self::check($result['done'] === ($approved ? [42,43] : []) && $result['all'] === $approved && $result['summary'] === ($approved ? 'Done: 2' : 'Done: 0'), 'Expanded-language approval/denial result differs.');
			self::check(!$approved || $tool->mutation->getLastArguments()['title'] === 'reviewed-43', 'Template argument was lost on resume.');
		}
		// A prior language continuation cannot be resumed at incompatible VM instruction positions.
		[$tool, $orchestrator, $resume] = self::harness(true);
		$run = self::runTurn($orchestrator, $tool, self::startModel(self::SOURCE, false));
		self::check($run->isSuspended(), 'Version fixture did not suspend.');
		$prepared = $resume->prepare(new AgentResume($run->getResumeHandle(), [new AgentInteractionResponse($run->getInteractionRequests()[0]->getId(), AgentInteractionResponse::DECISION_APPROVE)]));
		$old = $prepared->getSuspension()->toArray();
		$old['state']['program_state']['version'] = 1;
		$prepared = new PreparedAgentResume($prepared->getResume(), new AgentSuspensionClaim($prepared->getResumeHandle(), $prepared->getClaim()->getClaimToken(), AgentSuspension::fromArray($old)));
		$run = self::runTurn($orchestrator, $tool, new AgentSelectedNativeQueueModel([self::terminal()]), $prepared);
		self::check($tool->mutation->getCallCount() === 0 && self::result($run)['status'] === 'failed' && str_contains(self::result($run)['error'], 'language version changed'), 'Old instruction positions were resumed after a language change.');

	}
}

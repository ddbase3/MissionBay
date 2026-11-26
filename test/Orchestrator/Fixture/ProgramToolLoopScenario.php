<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Orchestrator\Fixture;

use Base3\AssistantFoundation\Dto\AgentBudget;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Capability\AgentCapabilityCatalogBuilder;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Event\MissionBayToolStartedEvent;
use Base3\MissionBay\Event\MissionBayToolFinishedEvent;
use Base3\MissionBay\Event\MissionBayToolFailedEvent;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
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
	private static function harness(bool $approval): array {
		$tool = new ProgramToolLoopFixtureTool();
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
		$results = [self::response('select-1', 'missionbay_select_capability_sources', ['source_ids' => ['program-fixture']]), self::response('program-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => $source])];
		if ($terminal) { $results[] = self::terminal(); }
		return new AgentSelectedNativeQueueModel($results);
	}
	private static function terminal(): AiChatResult { return new AiChatResult('Completed.', [], new AiResultMetadata('model_decision', 'test', 'terminal')); }
	private static function result($run): array {
		foreach ($run->getToolResults() as $result) { if ($result->getCallId() === 'program-1') { return $result->getOutput(); } }
		throw new \RuntimeException('Missing parent program result: ' . $run->getFailureCode() . ' ' . $run->getFailureMessage());
	}
	public static function run(): void {
		[$tool, $orchestrator, $resume, $events] = self::harness(false);
		// Read-only deterministic processing with native model decision and one observation.
		$source = 'let rows = tools.read_records({}); let total = 0; for (let id of rows.output.items) { total = total + id; } return total;';
		$model = self::startModel($source);
		$run = self::runTurn($orchestrator, $tool, $model);
		self::check($run->isCompleted(), 'Read program did not complete: ' . $run->getFailureCode() . ' ' . $run->getFailureMessage());
		$output = self::result($run);
		self::check($output['result'] === 85 && $tool->reads === 1 && count($model->getMessageSets()) === 3, 'Read program result or call count differs.');
		self::check(count($run->getToolResults()) === 2, 'Concrete result missing from ledger.');
		$messages = $model->getMessageSets()[2];
		$ids = array_column(array_filter($messages, static fn(array $m): bool => ($m['role'] ?? '') === 'tool'), 'tool_call_id');
		self::check($ids === ['select-1', 'program-1'], 'Concrete result leaked as unmatched model tool message.');
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
		// Existing selection can replace a large working set with the next program's sources.
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
			self::response('select-large', 'missionbay_select_capability_sources', ['source_ids' => ['source-0','source-1','source-2','source-3','source-4','source-5']]),
			self::response('select-next', 'missionbay_select_capability_sources', ['source_ids' => ['program-fixture']]),
			self::response('program-1', ToolProgramDefinitionService::TOOL_NAME, ['program' => 'let r = tools.read_records({}); return r.output.items.length;']),
			self::terminal()
		]);
		$run = $orchestrator->run($model, [['role' => 'user', 'content' => 'Count records.']], $definitions, $tools, new AgentContext(), maxLoops: 32, capabilityCatalog: (new AgentCapabilityCatalogBuilder())->build($tools, $definitions), capabilitySelectionConfig: new AgentCapabilitySelectionConfig(maxTools:64), modelDecisionConfig: AgentModelDecisionConfig::nativeCapability(true));
		$sets = $model->getToolSets();
		self::check($run->isCompleted() && self::result($run)['result'] === 2 && count($sets[0]) === 2 && count($sets[1]) === 62 && count($sets[2]) === 4, 'Large catalog selection or reselection failed. ' . $run->getFailureCode() . ' ' . $run->getFailureMessage());
		// Verify a real built-in tool contract and its return value.
		$time = new CurrentTimeAgentTool();
		$contracts = new AgentToolContractValidationService();
		self::check($contracts->getProgramContract('get_current_time', [$time]) !== null && $contracts->validateOutput(new AiToolCall('time', 'get_current_time', []), $time->callTool('get_current_time', [], new AgentContext()), [$time])->isValidated(), 'Current time contract failed.');
		// Pure interpreter control flow and forbidden syntax.
		$parser = new ToolProgramParser();
		$vm = new ToolProgramInterpreter();
		$program = $parser->parse('let total = 0; for (let i of [1,2,3,4,5]) { if (i === 2) { continue; } if (i === 4) { break; } total = total + i; } return total;');
		self::check($vm->advance($program, new ToolProgramState(), 'local', static fn(): bool => false)['result'] === 4, 'VM loop control failed.');
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
	}
}

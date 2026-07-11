<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Orchestrator\Stage;

/**
 * AgentToolLoopContextKeys
 *
 * Internal context keys shared by the first MissionBay tool-loop stages.
 *
 * The keys deliberately live in MissionBay for now. They describe the
 * existing tool orchestration runtime and are not yet part of the stable
 * AssistantFoundation agent state model.
 */
final class AgentToolLoopContextKeys {

	public const PREFIX = 'missionbay.agent.tool_loop.';

	public const MODEL = self::PREFIX . 'model';
	public const MESSAGES = self::PREFIX . 'messages';
	public const TOOL_DEFINITIONS = self::PREFIX . 'tool_definitions';
	public const TOOLS = self::PREFIX . 'tools';
	public const EVENT_CALLBACK = self::PREFIX . 'event_callback';
	public const LOGGER = self::PREFIX . 'logger';
	public const NODE_ID = self::PREFIX . 'node_id';
	public const TRACE = self::PREFIX . 'trace';
	public const MAX_LOOPS = self::PREFIX . 'max_loops';
	public const BUDGET = self::PREFIX . 'budget';
	public const TOOL_CACHE_CONFIG = self::PREFIX . 'tool_cache_config';
	public const RUN_STARTED_AT = self::PREFIX . 'run_started_at';

	public const PHASE = self::PREFIX . 'phase';
	public const ITERATION = self::PREFIX . 'iteration';
	public const CALL_INDEX = self::PREFIX . 'call_index';
	public const PENDING_TOOL_CALLS = self::PREFIX . 'pending_tool_calls';
	public const ACTIONS = self::PREFIX . 'actions';
	public const ACTION_DECISIONS = self::PREFIX . 'action_decisions';
	public const ACTION_REVIEW_CANDIDATES = self::PREFIX . 'action_review_candidates';
	public const PREAPPROVED_ACTIONS = self::PREFIX . 'preapproved_actions';
	public const INTERACTION_REQUESTS = self::PREFIX . 'interaction_requests';
	public const SUSPENSION = self::PREFIX . 'suspension';
	public const RESUME = self::PREFIX . 'resume';
	public const SUSPENDED = self::PREFIX . 'suspended';
	public const EXECUTION_STATUS = self::PREFIX . 'execution_status';
	public const TOOL_RESULTS = self::PREFIX . 'tool_results';
	public const OBSERVATIONS = self::PREFIX . 'observations';
	public const EXECUTED_TOOL_CALLS = self::PREFIX . 'executed_tool_calls';
	public const TOOL_CALL_INDEXES = self::PREFIX . 'tool_call_indexes';
	public const TOOL_CACHE_PLANS = self::PREFIX . 'tool_cache_plans';
	public const TOOL_CACHE_RECORDS = self::PREFIX . 'tool_cache_records';
	public const PROGRESS_ASSESSMENTS = self::PREFIX . 'progress_assessments';
	public const CONSECUTIVE_STALLED_ITERATIONS = self::PREFIX . 'consecutive_stalled_iterations';
	public const LOOP_PROGRESS_TERMINATED = self::PREFIX . 'loop_progress_terminated';
	public const FINAL_ASSISTANT_MESSAGE = self::PREFIX . 'final_assistant_message';
	public const FINAL_OUTPUT_CONTENT = self::PREFIX . 'final_output_content';
	public const FINAL_RESPONSE_MODE = self::PREFIX . 'final_response_mode';
	public const MODEL_RESULTS = self::PREFIX . 'model_results';
	public const CONTEXT_ASSESSMENTS = self::PREFIX . 'context_assessments';
	public const CONTEXT_COMPACTIONS = self::PREFIX . 'context_compactions';
	public const RESULT_VERIFICATIONS = self::PREFIX . 'result_verifications';
	public const CONTINUATION_DECISIONS = self::PREFIX . 'continuation_decisions';
	public const CONTINUATION_HINT = self::PREFIX . 'continuation_hint';
	public const FINAL_RESPONSE_INSTRUCTION = self::PREFIX . 'final_response_instruction';
	public const BUDGET_ASSESSMENTS = self::PREFIX . 'budget_assessments';
	public const STAGE_TRACE = self::PREFIX . 'stage_trace';
	public const COMPLETED = self::PREFIX . 'completed';
	public const FAILURE_CODE = self::PREFIX . 'failure_code';
	public const FAILURE_MESSAGE = self::PREFIX . 'failure_message';
	public const FAILURE_DETAIL = self::PREFIX . 'failure_detail';

	public const PHASE_RESUME = 'resume';
	public const PHASE_MODEL = 'model';
	public const PHASE_TOOLS = 'tools';
	public const PHASE_AFTER_TOOLS = 'after-tools';
	public const PHASE_OBSERVED = 'observed';
	public const PHASE_FINAL = 'final';
	public const PHASE_COMPLETE = 'complete';
	public const PHASE_FAILED = 'failed';
	public const PHASE_AWAITING_APPROVAL = 'awaiting-approval';
	public const PHASE_AWAITING_INPUT = 'awaiting-input';

	public const FINAL_RESPONSE_NONE = 'none';
	public const FINAL_RESPONSE_COMPLETE = 'complete';
	public const FINAL_RESPONSE_PARTIAL = 'partial';

	/**
	 * Returns the temporary runtime keys that should not remain in the
	 * flow-wide context after the orchestration call has finished.
	 *
	 * @return array<int,string>
	 */
	public static function getTemporaryRuntimeKeys(): array {
		return [
			self::MODEL,
			self::TOOL_DEFINITIONS,
			self::TOOLS,
			self::EVENT_CALLBACK,
			self::LOGGER,
			self::NODE_ID,
			self::TRACE,
			self::MAX_LOOPS,
			self::BUDGET,
			self::TOOL_CACHE_CONFIG,
			self::RUN_STARTED_AT,
			self::ACTION_REVIEW_CANDIDATES,
			self::PREAPPROVED_ACTIONS,
			self::INTERACTION_REQUESTS,
			self::SUSPENSION,
			self::RESUME,
			self::SUSPENDED,
			self::EXECUTION_STATUS,
			self::TOOL_RESULTS,
			self::TOOL_CALL_INDEXES,
			self::TOOL_CACHE_PLANS,
			self::CONSECUTIVE_STALLED_ITERATIONS,
			self::LOOP_PROGRESS_TERMINATED,
			self::CONTINUATION_HINT,
			self::FINAL_RESPONSE_INSTRUCTION
		];
	}
}

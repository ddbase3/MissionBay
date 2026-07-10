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

	public const PHASE = self::PREFIX . 'phase';
	public const ITERATION = self::PREFIX . 'iteration';
	public const CALL_INDEX = self::PREFIX . 'call_index';
	public const PENDING_TOOL_CALLS = self::PREFIX . 'pending_tool_calls';
	public const EXECUTED_TOOL_CALLS = self::PREFIX . 'executed_tool_calls';
	public const FINAL_ASSISTANT_MESSAGE = self::PREFIX . 'final_assistant_message';
	public const MODEL_RESULTS = self::PREFIX . 'model_results';
	public const COMPLETED = self::PREFIX . 'completed';
	public const FAILURE_CODE = self::PREFIX . 'failure_code';
	public const FAILURE_MESSAGE = self::PREFIX . 'failure_message';
	public const FAILURE_DETAIL = self::PREFIX . 'failure_detail';

	public const PHASE_MODEL = 'model';
	public const PHASE_TOOLS = 'tools';
	public const PHASE_AFTER_TOOLS = 'after-tools';
	public const PHASE_COMPLETE = 'complete';
	public const PHASE_FAILED = 'failed';

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
			self::MAX_LOOPS
		];
	}
}

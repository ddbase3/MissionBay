<?php declare(strict_types=1);

namespace Base3\MissionBay\Orchestrator\Program;

/** A compiled, fully parsed program. No host code is executed. */
final class ToolProgram {

	public const VERSION = 1;
	public const MAX_SOURCE_BYTES = 65536;
	public const MAX_DEPTH = 16;
	public const MAX_STEPS = 10000;
	public const MAX_CALLS = 100;
	public const MAX_STATE_BYTES = 2097152;
	public const MAX_RESULT_BYTES = 65536;
	public const MAX_WORK_BYTES = 268435456;
	public const MAX_TOOL_RESULT_BYTES = 8388608;

	/** @param array<int,array<int,mixed>> $instructions @param array<int,string> $toolNames */
	public function __construct(
		public readonly array $instructions,
		public readonly array $toolNames
	) {}
}

<?php declare(strict_types=1);

namespace Base3\MissionBay\Orchestrator\Program;

/** Interpreter continuation only; the existing suspension owns its persistence. */
final class ToolProgramState {

	public int $pc = 0;
	public int $steps = 0;
	public int $calls = 0;
	public int $successful = 0;
	public int $failed = 0;
	public int $workBytes = 0;
	public int $resultBytes = 0;
	public array $stack = [];
	public array $locals = [];
	public string $waitingCallId = '';

	public function toJson(): string {
		$json = json_encode([
			'pc' => $this->pc,
			'steps' => $this->steps,
			'calls' => $this->calls,
			'successful' => $this->successful,
			'failed' => $this->failed,
			'workBytes' => $this->workBytes,
			'resultBytes' => $this->resultBytes,
			'stack' => $this->stack,
			'locals' => (object)$this->locals,
			'waiting_call_id' => $this->waitingCallId
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION, 128);
		if (strlen($json) > ToolProgram::MAX_STATE_BYTES) {
			throw new \RuntimeException('Program state exceeds 2 MiB.');
		}
		return $json;
	}

	public function checkSize(): void {
		$this->workBytes += strlen($this->toJson());
		if ($this->workBytes > ToolProgram::MAX_WORK_BYTES) {
			throw new \RuntimeException('Program cumulative state processing limit exceeded.');
		}
	}

	public static function fromJson(string $json): self {
		if (strlen($json) > ToolProgram::MAX_STATE_BYTES) {
			throw new \RuntimeException('Program state exceeds 2 MiB.');
		}
		$data = json_decode($json, false, 128, JSON_THROW_ON_ERROR);
		if (!$data instanceof \stdClass || !is_array($data->stack ?? null)
			|| !($data->locals ?? null) instanceof \stdClass || !is_string($data->waiting_call_id ?? null)) {
			throw new \RuntimeException('Invalid program continuation.');
		}
		$state = new self();
		foreach (['pc', 'steps', 'calls', 'successful', 'failed', 'workBytes', 'resultBytes'] as $key) {
			if (!is_int($data->{$key} ?? null) || $data->{$key} < 0) {
				throw new \RuntimeException('Invalid program continuation counter.');
			}
			$state->{$key} = $data->{$key};
		}
		if ($state->steps > ToolProgram::MAX_STEPS || $state->calls > ToolProgram::MAX_CALLS
			|| $state->workBytes > ToolProgram::MAX_WORK_BYTES || $state->resultBytes > ToolProgram::MAX_TOOL_RESULT_BYTES
			|| $state->successful + $state->failed + ($data->waiting_call_id !== '' ? 1 : 0) !== $state->calls) {
			throw new \RuntimeException('Invalid program continuation counters.');
		}
		$state->stack = $data->stack;
		$state->locals = get_object_vars($data->locals);
		$state->waitingCallId = $data->waiting_call_id;
		return $state;
	}
}

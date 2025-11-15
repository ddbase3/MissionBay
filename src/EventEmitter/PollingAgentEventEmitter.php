<?php declare(strict_types=1);

namespace MissionBay\EventEmitter;

class PollingAgentEventEmitter extends AbstractAgentEventEmitter {

	public static function getName(): string {
		return 'polling';
	}

	private string $file;

	public function __construct() {
		// Automatically pick a file name based on session id
		$sid = session_id() ?: uniqid('poll_', true);

		// Store events in a temp file (JSON lines)
		$this->file = sys_get_temp_dir() . "/agentpoll_" . $sid . ".jsonl";

		@unlink($this->file);
	}

	public function emitEvent(array $event): void {
		// Append to file-based event queue
		file_put_contents(
			$this->file,
			json_encode([
				'ts'	=> microtime(true),
				'event'	=> $event
			]) . "\n",
			FILE_APPEND
		);

		// Optional sink (e.g. UI debug logger)
		if ($this->sink) {
			($this->sink)($event);
		}
	}

	public function finish(): void {
		$this->emitEvent(['type' => 'done']);
	}
}

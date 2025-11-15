<?php declare(strict_types=1);

namespace MissionBay\EventEmitter;

class SseAgentEventEmitter extends AbstractAgentEventEmitter {

    public static function getName(): string {
        return 'sse';
    }

    public function emitEvent(array $event): void {
        if ($this->sink) {
            ($this->sink)($event);
        }
    }

    public function finish(): void {
        if ($this->sink) {
            ($this->sink)(['type' => 'done']);
        }
    }
}

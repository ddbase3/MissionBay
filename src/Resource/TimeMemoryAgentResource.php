<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * TimeMemoryAgentResource
 *
 * Provides a single system message with the current date/time/timezone.
 * Does not store or accept appended history.
 */
class TimeMemoryAgentResource extends AbstractAgentResource implements IAgentMemory {

	private IAgentConfigValueResolver $resolver;
	private int $priority = 10;

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'timememoryagentresource';
	}

	public function getDescription(): string {
		return 'Provides the current date/time/timezone as a system message.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);
		$this->priority = (int)($this->resolver->resolveValue($config['priority'] ?? null) ?? 10);
	}

	// ------- IAgentMemory -------
	public function loadNodeHistory(string $nodeId): array {
		$now = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));
		return [[
			'role' => 'system',
			'content' => 'Current time is ' . $now->format(\DateTimeInterface::ATOM)
		]];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		// no-op
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		// no-op
	}

	public function resetNodeHistory(string $nodeId): void {
		// no-op
	}

	public function put(string $key, mixed $value): void {
		// no-op
	}

	public function get(string $key): mixed {
		return null;
	}

	public function forget(string $key): void {
		// no-op
	}

	public function keys(): array {
		return [];
	}

	public function getPriority(): int {
		return $this->priority;
	}
}


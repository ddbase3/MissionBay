<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationScope;
use Base3\Session\Api\ISession;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\SessionMemoryAgentResource;
use PHPUnit\Framework\TestCase;

final class SessionMemorySessionStub implements ISession {

	public array $values = [];
	private bool $started = false;

	public function __construct(private readonly string $id) {}
	public function started(): bool { return $this->started; }
	public function getId(): string { return $this->id; }
	public function start(): bool { $this->started = true; return true; }
	public function destroy(): bool { $this->started = false; $this->values = []; return true; }
	public function get(string $key, mixed $default = null): mixed { return $this->values[$key] ?? $default; }
	public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
	public function has(string $key): bool { return array_key_exists($key, $this->values); }
	public function remove(string $key): void { unset($this->values[$key]); }
}

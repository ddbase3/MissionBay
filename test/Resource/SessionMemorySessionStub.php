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

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Dto\AgentConversation;
use Base3\AssistantFoundation\Dto\AgentConversationScope;
use Base3\CoreFoundation\Session\Api\ISession;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\SessionMemoryAgentResource;
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

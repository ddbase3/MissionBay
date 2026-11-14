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

namespace Base3\MissionBay\Test\Service;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentConversation;
use Base3\AssistantFoundation\Dto\AgentConversationRequest;
use Base3\CoreFoundation\Session\Api\ISession;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;
use Base3\MissionBay\Profile\AgentMemoryProfileResolver;
use Base3\MissionBay\Resource\SessionMemoryAgentResource;
use Base3\MissionBay\Service\AgentConversationService;
use PHPUnit\Framework\TestCase;

final class ConversationSessionDouble implements ISession {
	private array $values = [];
	private bool $started = false;
	public function started(): bool { return $this->started; }
	public function getId(): string { return 'session-one'; }
	public function start(): bool { $this->started = true; return true; }
	public function destroy(): bool { $this->values = []; $this->started = false; return true; }
	public function get(string $key, mixed $default = null): mixed { return $this->values[$key] ?? $default; }
	public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
	public function has(string $key): bool { return array_key_exists($key, $this->values); }
	public function remove(string $key): void { unset($this->values[$key]); }
}

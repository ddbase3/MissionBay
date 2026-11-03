<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationRequest;
use Base3\Session\Api\ISession;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Profile\AgentMemoryProfileResolver;
use MissionBay\Resource\SessionMemoryAgentResource;
use MissionBay\Service\AgentConversationService;
use PHPUnit\Framework\TestCase;

final class ConversationSettingsStoreDouble implements ISettingsStore {
	public function __construct(private array $groups) {}
	public function get(string $group, string $name, array $default = []): array { return $this->groups[$group][$name] ?? $default; }
	public function set(string $group, string $name, array $settings): void { $this->groups[$group][$name] = $settings; }
	public function has(string $group, string $name): bool { return isset($this->groups[$group][$name]); }
	public function remove(string $group, string $name): void { unset($this->groups[$group][$name]); }
	public function getGroup(string $group): array { return $this->groups[$group] ?? []; }
	public function save(): void {}
	public function reload(): void {}
}

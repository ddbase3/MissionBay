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

final class ConversationPresetRepositoryDouble implements IAgentComponentPresetRepository {
	public function __construct(private array $presets) {}
	public function getPresets(): array { return $this->presets; }
	public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
	public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
	public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
	public function removePreset(string $id): void { unset($this->presets[$id]); }
}

<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationScope;
use AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentMemoryProfileResolver;
use MissionBay\Resource\AbstractAgentResource;
use MissionBay\Resource\AgentContext\Text\StaticTextContextAgentResource;
use PHPUnit\Framework\TestCase;

final class AgentMemoryProfileResolverTestDouble2 implements IAgentComponentPresetRepository  {
			private array $presets = [
				'session-main' => ['id' => 'session-main', 'label' => 'Main session memory', 'type' => 'testconversationmemory', 'enabled' => true, 'config' => ['namespace' => 'main', 'max' => 20]],
				'current-time' => ['id' => 'current-time', 'label' => 'Current time', 'type' => 'testcontextcontributor', 'enabled' => true, 'config' => []],
				'static-text' => ['id' => 'static-text', 'label' => 'Static text', 'type' => 'statictextcontextagentresource', 'enabled' => true, 'config' => ['text' => 'Reusable system context.']],
				'user-prefs' => ['id' => 'user-prefs', 'label' => 'User preferences', 'type' => 'testdualtoolcontext', 'enabled' => true, 'config' => ['priority' => 20]],
				'tool-only' => ['id' => 'tool-only', 'label' => 'Tool only', 'type' => 'testtoolonly', 'enabled' => true, 'config' => []]
			];
			public function getPresets(): array { return $this->presets; }
			public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
			public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
			public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
			public function removePreset(string $id): void { unset($this->presets[$id]); }
		}

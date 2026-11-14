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

namespace Base3\MissionBay\Test\Profile;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentContextContributor;
use Base3\AssistantFoundation\Api\IAgentConversationMemory;
use Base3\AssistantFoundation\Dto\AgentConversation;
use Base3\AssistantFoundation\Dto\AgentConversationScope;
use Base3\AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Profile\AgentContextProfileResolver;
use Base3\MissionBay\Profile\AgentMemoryProfileResolver;
use Base3\MissionBay\Resource\AbstractAgentResource;
use Base3\MissionBay\Resource\AgentContext\Text\StaticTextContextAgentResource;
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

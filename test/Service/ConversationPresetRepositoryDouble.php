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

final class ConversationPresetRepositoryDouble implements IAgentComponentPresetRepository {
	public function __construct(private array $presets) {}
	public function getPresets(): array { return $this->presets; }
	public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
	public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
	public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
	public function removePreset(string $id): void { unset($this->presets[$id]); }
}

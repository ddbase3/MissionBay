<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentTextTaskRequest;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentAssistantContextContributionService;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Dto\AgentFlowCompilation;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentToolProfileResolver;
use MissionBay\Service\AgentTextTaskService;
use PHPUnit\Framework\TestCase;

final class TextTaskPresetRepositoryDouble implements IAgentComponentPresetRepository {
	public function __construct(private array $presets = []) {}
	public function getPresets(): array { return $this->presets; }
	public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
	public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
	public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
	public function removePreset(string $id): void { unset($this->presets[$id]); }
}

<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentCapabilitySourceMetadata;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\AbstractAgentResource;
use MissionBay\Resource\ConfiguredAgentToolResource;
use MissionBay\Service\AgentComponentPresetMaterializer;
use PHPUnit\Framework\TestCase;

final class MaterializerPresetRepositoryDouble implements IAgentComponentPresetRepository {

	private array $presets = [
		'deepwiki' => [
			'id' => 'deepwiki',
			'label' => 'DeepWiki',
			'type' => 'materializerremotetooldouble',
			'enabled' => true,
			'capabilities' => ['tool'],
			'config' => [],
			'docks' => []
		]
	];

	public function getPresets(): array {
		return $this->presets;
	}

	public function getPreset(string $id, array $default = []): array {
		return $this->presets[$id] ?? $default;
	}

	public function hasPreset(string $id): bool {
		return isset($this->presets[$id]);
	}

	public function savePreset(string $id, array $preset): void {
		$this->presets[$id] = $preset;
	}

	public function removePreset(string $id): void {
		unset($this->presets[$id]);
	}
}

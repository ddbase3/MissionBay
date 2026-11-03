<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentExecutionRequest;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Dto\AgentFlowCompilation;
use MissionBay\Service\AgentComponentFlowBuilder;
use MissionBay\Service\AgentComponentPresetFlowExpander;
use MissionBay\Service\AgentExecutionService;
use MissionBay\Service\AgentFlowCompiler;
use PHPUnit\Framework\TestCase;

final class AgentExecutionServiceCapabilityConfigTestDouble1 implements IAgentComponentPresetRepository  {
			private array $presets = [
				'chat-main' => [
					'id' => 'chat-main',
					'label' => 'Primary chat model',
					'type' => 'configuredchatmodelagentresource',
					'enabled' => true,
					'capabilities' => ['chatmodel'],
					'config' => [
						'service' => [
							'mode' => 'fixed',
							'value' => 'llm-a'
						]
					]
				]
			];

			public function getPresets(): array { return $this->presets; }
			public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
			public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
			public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
			public function removePreset(string $id): void { unset($this->presets[$id]); }
		}

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
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentContextFactory;
use Base3\MissionBay\Api\IAgentFlow;
use Base3\MissionBay\Api\IAgentFlowCompiler;
use Base3\MissionBay\Api\IAgentFlowFactory;
use Base3\MissionBay\Dto\AgentFlowCompilation;
use Base3\MissionBay\Service\AgentComponentFlowBuilder;
use Base3\MissionBay\Service\AgentComponentPresetFlowExpander;
use Base3\MissionBay\Service\AgentExecutionService;
use Base3\MissionBay\Service\AgentFlowCompiler;
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

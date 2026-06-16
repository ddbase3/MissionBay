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

namespace MissionBay\Service;

use MissionBay\Api\IAgentComponentPresetInstaller;
use MissionBay\Api\IAgentComponentPresetRepository;

/**
 * AgentComponentDefaultPresetInstaller
 *
 * Installs a minimal set of safe starter presets for the component based
 * AgentFlow runtime. The initial set intentionally avoids complex dock chains.
 */
class AgentComponentDefaultPresetInstaller implements IAgentComponentPresetInstaller {

	public function __construct(private readonly IAgentComponentPresetRepository $presetRepository) {}

	public static function getName(): string {
		return 'agentcomponentdefaultpresetinstaller';
	}

	public function installDefaults(bool $overwrite = false): array {
		$installed = [];
		$skipped = [];

		foreach ($this->getDefaultPresets() as $id => $preset) {
			if (!$overwrite && $this->presetRepository->hasPreset($id)) {
				$skipped[] = $id;
				continue;
			}

			$this->presetRepository->savePreset($id, $preset);
			$installed[] = $id;
		}

		return [
			'ok' => true,
			'overwrite' => $overwrite,
			'installed' => $installed,
			'skipped' => $skipped,
			'count_installed' => count($installed),
			'count_skipped' => count($skipped)
		];
	}

	public function getDefaultPresets(): array {
		return [
			'timememory_default' => [
				'id' => 'timememory_default',
				'label' => 'Current Time Memory',
				'type' => 'timememoryagentresource',
				'enabled' => true,
				'capabilities' => [
					'memory'
				],
				'config' => [],
				'docks' => [],
				'meta' => [
					'description' => 'Injects the current date, time and weekday as system context.',
					'category' => 'context',
					'risk' => 'none',
					'status' => 'ready',
					'version' => 1
				]
			],
			'webfetch_default' => [
				'id' => 'webfetch_default',
				'label' => 'Web Fetch Text',
				'type' => 'webfetchtextagenttool',
				'enabled' => true,
				'capabilities' => [
					'tool'
				],
				'config' => [],
				'docks' => [],
				'meta' => [
					'description' => 'Fetches a public webpage and extracts readable text.',
					'category' => 'web',
					'risk' => 'read_external_url',
					'status' => 'ready',
					'version' => 1
				]
			]
		];
	}

	public function getDefaultAgentComponents(): array {
		return [
			[
				'preset' => 'timememory_default',
				'attach_as' => [
					'memory'
				],
				'enabled' => true,
				'order' => 10,
				'memory_config' => [
					'enabled' => [
						'mode' => 'fixed',
						'value' => true
					],
					'priority' => [
						'mode' => 'fixed',
						'value' => 10
					]
				]
			],
			[
				'preset' => 'webfetch_default',
				'attach_as' => [
					'tool'
				],
				'enabled' => true,
				'tool_config' => [
					'enabled' => [
						'mode' => 'fixed',
						'value' => true
					],
					'namespace' => [
						'mode' => 'fixed',
						'value' => 'web'
					],
					'label' => [
						'mode' => 'fixed',
						'value' => 'Webpage Fetch'
					],
					'description' => [
						'mode' => 'fixed',
						'value' => 'Fetches a public webpage and extracts readable text.'
					],
					'category' => [
						'mode' => 'fixed',
						'value' => 'web'
					],
					'tags' => [
						'mode' => 'fixed',
						'value' => [
							'web',
							'fetch',
							'text'
						]
					],
					'priority' => [
						'mode' => 'fixed',
						'value' => 50
					]
				]
			]
		];
	}
}

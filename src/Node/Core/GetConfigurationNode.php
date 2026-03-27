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

namespace MissionBay\Node\Core;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use Base3\Configuration\Api\IConfiguration;
use MissionBay\Node\AbstractAgentNode;

class GetConfigurationNode extends AbstractAgentNode {

	private IConfiguration $configuration;

	public function __construct(IConfiguration $configuration, ?string $id = null) {
		parent::__construct($id);
		$this->configuration = $configuration;
	}

	public static function getName(): string {
		return 'getconfigurationnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'section',
				description: 'Name of the configuration section (e.g., "openai", "smtp").',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'key',
				description: 'Key within the selected section to retrieve.',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'value',
				description: 'The retrieved configuration value.',
				type: 'mixed',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if section or key was missing or not found.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$section = $inputs['section'] ?? null;
		$key = $inputs['key'] ?? null;

		if (!$section || !$key) {
			return ['error' => $this->error('Missing section or key input')];
		}

		$sectionData = $this->configuration->get($section);

		if (!is_array($sectionData)) {
			return ['error' => $this->error("Config section '$section' not found or invalid")];
		}

		if (!array_key_exists($key, $sectionData)) {
			return ['error' => $this->error("Config key '$key' not found in section '$section'")];
		}

		return ['value' => $sectionData[$key]];
	}

	public function getDescription(): string {
		return 'Retrieves a specific configuration value from a named section using Base3\'s IConfiguration interface. Useful for injecting secrets, environment settings, or other global parameters into a flow.';
	}
}


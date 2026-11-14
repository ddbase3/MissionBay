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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentCapability;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\MissionBay\Api\IAgentCapabilitySourceMetadata;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySourceCatalogService;
use PHPUnit\Framework\TestCase;

final class CapabilitySourceToolDouble implements IAgentTool, IAgentCapabilitySourceMetadata {

	/** @param array<int,string> $functionNames */
	public function __construct(
		private readonly string $sourceId,
		private readonly string $label,
		private readonly string $description,
		private readonly array $functionNames
	) {}

	public static function getName(): string {
		return 'capabilitySourceToolDouble';
	}

	public function getCapabilitySourceId(): string {
		return $this->sourceId;
	}

	public function getCapabilitySourceLabel(): string {
		return $this->label;
	}

	public function getCapabilitySourceDescription(): string {
		return $this->description;
	}

	public function getToolDefinitions(): array {
		$result = [];
		foreach ($this->functionNames as $name) {
			$result[] = [
				'type' => 'function',
				'readOnlyHint' => true,
				'mutation' => false,
				'requiresApproval' => false,
				'function' => [
					'name' => $name,
					'description' => 'Function ' . $name . '.',
					'parameters' => [
						'type' => 'object',
						'properties' => [],
						'required' => []
					]
				]
			];
		}
		return $result;
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return ['ok' => true, 'name' => $name];
	}
}

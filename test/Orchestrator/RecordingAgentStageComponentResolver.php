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
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Dto\AgentStageMount;
use Base3\AssistantFoundation\Dto\AgentStageResult;
use Base3\AssistantFoundation\Dto\AgentStageSlot;
use Base3\CoreFoundation\Api\IComponent;
use Base3\CoreFoundation\Api\IComponentResolver;
use Base3\MissionBay\Orchestrator\AgentStagePipelineResolver;
use PHPUnit\Framework\TestCase;

final class RecordingAgentStageComponentResolver implements IComponentResolver {

	/**
	 * @var array<string,IAgentStage>
	 */
	private array $stages;

	/**
	 * @var array<int,string>
	 */
	private array $requestedIds = [];

	/**
	 * @param array<string,IAgentStage> $stages
	 */
	public function __construct(array $stages) {
		$this->stages = $stages;
	}

	public function has(string $interfaceName, string $id): bool {
		return $interfaceName === IAgentStage::class && isset($this->stages[$id]);
	}

	public function get(string $interfaceName, string $id): ?IComponent {
		$this->requestedIds[] = $id;

		if ($interfaceName !== IAgentStage::class) {
			return null;
		}

		return $this->stages[$id] ?? null;
	}

	public function all(string $interfaceName): iterable {
		if ($interfaceName !== IAgentStage::class) {
			return [];
		}

		return array_values($this->stages);
	}

	/**
	 * @return array<int,string>
	 */
	public function getRequestedIds(): array {
		return $this->requestedIds;
	}
}

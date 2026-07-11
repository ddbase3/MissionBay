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

namespace MissionBay\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentStageResult;

/**
 * Applies infrastructure-service results inside one semantic stage and
 * exposes the combined patch to the outer orchestrator.
 */
final class AgentStageResultAccumulator {

	/** @var array<string,mixed> */
	private array $patch = [];

	/** @var array<string,mixed> */
	private array $metadata = [];

	public function __construct(private readonly IAgentContext $context) {}

	public function apply(AgentStageResult $result, string $metadataKey = ''): void {
		foreach ($result->getPatch() as $key => $value) {
			$this->context->setVar($key, $value);
			$this->patch[$key] = $value;
		}

		if ($result->getMetadata() === []) {
			return;
		}

		if ($metadataKey !== '') {
			$this->metadata[$metadataKey] = $result->getMetadata();
			return;
		}

		$this->metadata = array_merge($this->metadata, $result->getMetadata());
	}

	public function result(): AgentStageResult {
		return AgentStageResult::patch($this->patch, $this->metadata);
	}
}

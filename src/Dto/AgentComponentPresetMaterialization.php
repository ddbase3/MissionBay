<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Dto;

use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentConversationMemory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentTool;

/**
 * Immutable result of one component-preset materialization.
 */
final class AgentComponentPresetMaterialization {

	/**
	 * @param array<string,mixed> $preset
	 * @param array<int,string> $capabilities
	 * @param array<int,string> $warnings
	 * @param array<string,array<int,string>> $docks
	 */
	public function __construct(
		private readonly string $presetId,
		private readonly array $preset,
		private readonly ?IAgentResource $resource,
		private readonly ?IAgentTool $tool,
		private readonly ?IAgentConversationMemory $memory,
		private readonly ?IAgentContextContributor $contextContributor,
		private readonly array $capabilities,
		private readonly array $warnings = [],
		private readonly array $docks = []
	) {}

	public function getPresetId(): string {
		return $this->presetId;
	}

	/** @return array<string,mixed> */
	public function getPreset(): array {
		return $this->preset;
	}

	public function getResource(): ?IAgentResource {
		return $this->resource;
	}

	public function getTool(): ?IAgentTool {
		return $this->tool;
	}

	public function getMemory(): ?IAgentConversationMemory {
		return $this->memory;
	}

	public function getContextContributor(): ?IAgentContextContributor {
		return $this->contextContributor;
	}

	/** @return array<int,string> */
	public function getCapabilities(): array {
		return $this->capabilities;
	}

	/** @return array<int,string> */
	public function getWarnings(): array {
		return $this->warnings;
	}

	/** @return array<string,array<int,string>> */
	public function getDocks(): array {
		return $this->docks;
	}

	public function isReady(): bool {
		return $this->resource instanceof IAgentResource && $this->warnings === [];
	}
}

<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Dto\Assistant;

use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use AssistantFoundation\Dto\AgentStageMount;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;

/**
 * Runtime result of resolving the capability sources explicitly configured for
 * one agent. It contains executable objects for the current process and a
 * transport-safe report for diagnostics.
 */
final class AgentCapabilityDiscoveryResult {

	/**
	 * @param array<int,IAgentTool> $tools
	 * @param array<int,IAgentResourceProvider> $resourceProviders
	 * @param array<int,IAgentPromptProvider> $promptProviders
	 * @param array<int,string> $instructions
	 * @param array<int,AgentStageMount> $stageMounts
	 * @param array<int,string> $resolvedToolIds
	 * @param array<int,string> $resolvedProviderIds
	 * @param array<int,string> $resolvedModuleIds
	 * @param array<int,string> $resolvedResourceProviderIds
	 * @param array<int,string> $resolvedPromptProviderIds
	 * @param array<int,string> $warnings
	 * @param array<int,string> $errors
	 */
	public function __construct(
		private AgentCapabilitySourceConfig $sourceConfig,
		private array $tools = [],
		private array $resourceProviders = [],
		private array $promptProviders = [],
		private array $instructions = [],
		private array $stageMounts = [],
		private array $resolvedToolIds = [],
		private array $resolvedProviderIds = [],
		private array $resolvedModuleIds = [],
		private array $resolvedResourceProviderIds = [],
		private array $resolvedPromptProviderIds = [],
		private array $warnings = [],
		private array $errors = []
	) {}

	public function getSourceConfig(): AgentCapabilitySourceConfig {
		return $this->sourceConfig;
	}

	/** @return array<int,IAgentTool> */
	public function getTools(): array {
		return $this->tools;
	}

	/** @return array<int,IAgentResourceProvider> */
	public function getResourceProviders(): array {
		return $this->resourceProviders;
	}

	/** @return array<int,IAgentPromptProvider> */
	public function getPromptProviders(): array {
		return $this->promptProviders;
	}

	/** @return array<int,string> */
	public function getInstructions(): array {
		return $this->instructions;
	}

	/** @return array<int,AgentStageMount> */
	public function getStageMounts(): array {
		return $this->stageMounts;
	}

	/** @return array<int,string> */
	public function getWarnings(): array {
		return $this->warnings;
	}

	/** @return array<int,string> */
	public function getErrors(): array {
		return $this->errors;
	}

	public function hasErrors(): bool {
		return $this->errors !== [];
	}

	/** @return array<string,mixed> */
	public function toArray(): array {
		return [
			'sources' => $this->sourceConfig->toArray(),
			'tool_count' => count($this->tools),
			'resource_provider_count' => count($this->resourceProviders),
			'prompt_provider_count' => count($this->promptProviders),
			'instruction_count' => count($this->instructions),
			'stage_mount_count' => count($this->stageMounts),
			'resolved' => [
				'tools' => $this->resolvedToolIds,
				'providers' => $this->resolvedProviderIds,
				'modules' => $this->resolvedModuleIds,
				'resourceProviders' => $this->resolvedResourceProviderIds,
				'promptProviders' => $this->resolvedPromptProviderIds
			],
			'warnings' => $this->warnings,
			'errors' => $this->errors
		];
	}
}

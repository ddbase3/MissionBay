<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Orchestrator\Fixture;

use Base3\AssistantFoundation\Api\IAgentConfigurationService;

/** Fixed in-memory agent catalog for nested execution verification only. */
final class AgentDelegationAgentConfigurations implements IAgentConfigurationService {

	/** @var array<string,array<string,mixed>> */
	private array $agents;

	public function __construct() {
		$this->agents = [
			'agent-b' => ['label' => 'Agent B', 'enabled' => true, 'user_prompt' => 'Delegate to C'],
			'agent-c' => ['label' => 'Agent C', 'enabled' => true, 'user_prompt' => 'Update one record']
		];
	}

	public function getAgents(string $settingsGroup = 'agent'): array {
		return $settingsGroup === 'agent' ? $this->agents : [];
	}

	public function getAgent(string $id, string $settingsGroup = 'agent'): ?array {
		return $settingsGroup === 'agent' ? ($this->agents[$id] ?? null) : null;
	}

	public function hasAgent(string $id, string $settingsGroup = 'agent'): bool {
		return $this->getAgent($id, $settingsGroup) !== null;
	}

	public function isFixed(string $id): bool {
		return false;
	}

	public function saveAgent(string $id, array $settings): void {
		throw new \LogicException('The test catalog is read-only.');
	}

	public function deleteAgent(string $id): void {
		throw new \LogicException('The test catalog is read-only.');
	}

	public function reload(): void {}
}

<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Policy\Fixture;

use Base3\AssistantFoundation\Api\IAgentConfigurationService;

final class PolicyTestAgentConfigurations implements IAgentConfigurationService {

	public array $agents = [];

	public function getAgents(string $settingsGroup = 'agent'): array {
		return $this->agents;
	}

	public function getAgent(string $id, string $settingsGroup = 'agent'): ?array {
		return $this->agents[$id] ?? null;
	}

	public function hasAgent(string $id, string $settingsGroup = 'agent'): bool {
		return array_key_exists($id, $this->agents);
	}

	public function isFixed(string $id): bool { return false; }

	public function saveAgent(string $id, array $settings): void { $this->agents[$id] = $settings; }

	public function deleteAgent(string $id): void { unset($this->agents[$id]); }

	public function reload(): void {}
}

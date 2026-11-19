<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Display\Fixture;

use Base3\AssistantFoundation\Api\IAgentConfigurationService;

final class AgentAdminHitlAgentConfigurations implements IAgentConfigurationService {
	public array $settings = [
		'enabled' => true,
		'label' => 'Jarvis',
		'user_prompt' => 'Default prompt',
		'system_prompt' => 'System'
	];
	public function getAgents(string $settingsGroup = 'agent'): array { return ['jarvis' => $this->settings]; }
	public function getAgent(string $id, string $settingsGroup = 'agent'): ?array {
		return $id === 'jarvis' && $settingsGroup === 'agent' ? $this->settings : null;
	}
	public function hasAgent(string $id, string $settingsGroup = 'agent'): bool { return $this->getAgent($id, $settingsGroup) !== null; }
	public function isFixed(string $id): bool { return false; }
	public function saveAgent(string $id, array $settings): void { $this->settings = $settings; }
	public function deleteAgent(string $id): void { $this->settings = []; }
	public function reload(): void {}
}

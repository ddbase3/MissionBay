<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class AgentorchestratorprofileadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'agentssettingstab';
	}

	public function getDisplayName(): ?string {
		return 'agentorchestratorprofileadmindisplay';
	}

	public function getOrder(): int {
		return 30;
	}
}

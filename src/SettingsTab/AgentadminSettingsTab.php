<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class AgentadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'agentssettingstab';
	}

	public function getDisplayName(): ?string {
		return 'agentadmindisplay';
	}

	public function getOrder(): int {
		return 10;
	}
}

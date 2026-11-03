<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class AgentcomponentpresettestadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'agenttoolssettingstab';
	}

	public function getDisplayName(): ?string {
		return 'agentcomponentpresettestadmindisplay';
	}

	public function getOrder(): int {
		return 20;
	}
}

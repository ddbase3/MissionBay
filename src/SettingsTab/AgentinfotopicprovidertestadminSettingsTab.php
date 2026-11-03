<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class AgentinfotopicprovidertestadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'agenttoolssettingstab';
	}

	public function getDisplayName(): ?string {
		return 'agentinfotopicprovidertestadmindisplay';
	}

	public function getOrder(): int {
		return 30;
	}
}

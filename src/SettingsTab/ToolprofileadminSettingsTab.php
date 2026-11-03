<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class ToolprofileadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'agentssettingstab';
	}

	public function getDisplayName(): ?string {
		return 'toolprofileadmindisplay';
	}

	public function getOrder(): int {
		return 40;
	}
}

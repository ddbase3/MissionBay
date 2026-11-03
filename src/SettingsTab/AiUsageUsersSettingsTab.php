<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class AiUsageUsersSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'aiusagesettingstab';
	}

	public function getDisplayName(): ?string {
		return 'generalreportdisplay';
	}

	public function getOrder(): int {
		return 50;
	}

	public function getDisplayData(): mixed {
		return 'missionbay_ai_usage_users';
	}
}

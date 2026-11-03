<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class SpeechtotextconfigSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'providersettingstab';
	}

	public function getDisplayName(): ?string {
		return 'speechtotextconfigdisplay';
	}

	public function getOrder(): int {
		return 40;
	}
}

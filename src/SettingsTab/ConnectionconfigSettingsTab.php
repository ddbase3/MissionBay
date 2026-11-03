<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class ConnectionconfigSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'providersettingstab';
	}

	public function getDisplayName(): ?string {
		return 'connectionconfigdisplay';
	}

	public function getOrder(): int {
		return 10;
	}
}

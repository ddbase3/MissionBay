<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class VectorstoreconfigSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'providersettingstab';
	}

	public function getDisplayName(): ?string {
		return 'vectorstoreconfigdisplay';
	}

	public function getOrder(): int {
		return 90;
	}
}

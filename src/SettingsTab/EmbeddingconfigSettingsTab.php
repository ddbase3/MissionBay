<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class EmbeddingconfigSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'providersettingstab';
	}

	public function getDisplayName(): ?string {
		return 'embeddingconfigdisplay';
	}

	public function getOrder(): int {
		return 30;
	}
}

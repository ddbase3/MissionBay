<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class RetrievalcollectionadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'embeddingsettingstab';
	}

	public function getDisplayName(): ?string {
		return 'retrievalcollectionadmindisplay';
	}

	public function getOrder(): int {
		return 20;
	}
}

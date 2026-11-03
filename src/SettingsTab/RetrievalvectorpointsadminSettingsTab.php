<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class RetrievalvectorpointsadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'embeddingsettingstab';
	}

	public function getDisplayName(): ?string {
		return 'retrievalvectorpointsadmindisplay';
	}

	public function getOrder(): int {
		return 30;
	}
}

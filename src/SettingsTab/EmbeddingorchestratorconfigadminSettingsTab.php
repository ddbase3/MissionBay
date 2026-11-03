<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class EmbeddingorchestratorconfigadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'embeddingsettingstab';
	}

	public function getDisplayName(): ?string {
		return 'embeddingorchestratorconfigadmindisplay';
	}

	public function getOrder(): int {
		return 10;
	}
}

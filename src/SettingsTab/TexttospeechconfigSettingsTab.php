<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class TexttospeechconfigSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'providersettingstab';
	}

	public function getDisplayName(): ?string {
		return 'texttospeechconfigdisplay';
	}

	public function getOrder(): int {
		return 50;
	}
}

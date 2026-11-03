<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class AiUsageTokensSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'aiusagesettingstab';
	}

	public function getDisplayName(): ?string {
		return 'generalreportdisplay';
	}

	public function getOrder(): int {
		return 20;
	}

	public function getDisplayData(): mixed {
		return 'missionbay_ai_usage_tokens_timeline';
	}
}

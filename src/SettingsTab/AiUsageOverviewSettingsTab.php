<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class AiUsageOverviewSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'aiusagesettingstab';
	}

	public function getDisplayName(): ?string {
		return 'compositedisplay';
	}

	public function getOrder(): int {
		return 10;
	}

	public function getDisplayData(): mixed {
		return ['columns' => 2, 'items' => [['id' => 'metrics', 'display' => 'generalreportdisplay', 'data' => 'missionbay_ai_usage_metrics', 'span' => 2], ['id' => 'tokens', 'display' => 'generalreportdisplay', 'data' => 'missionbay_ai_usage_tokens_timeline'], ['id' => 'operations', 'display' => 'generalreportdisplay', 'data' => 'missionbay_ai_usage_operations'], ['id' => 'models', 'display' => 'generalreportdisplay', 'title' => $this->translation->translate('Settings', static::getName(), 'models_title'), 'data' => 'missionbay_ai_usage_models', 'span' => 2]]];
	}
}

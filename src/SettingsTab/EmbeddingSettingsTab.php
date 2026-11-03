<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractSettingsTab;

final class EmbeddingSettingsTab extends AbstractSettingsTab {

	public function getOrder(): int {
		return 70;
	}
}

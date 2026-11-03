<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractSettingsTab;

final class AgentsSettingsTab extends AbstractSettingsTab {

	public function getOrder(): int {
		return 50;
	}
}

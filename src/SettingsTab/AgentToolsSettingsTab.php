<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractSettingsTab;

final class AgentToolsSettingsTab extends AbstractSettingsTab {

	public function getOrder(): int {
		return 60;
	}
}

<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class KnowledgeagentmemoryadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'agenttoolssettingstab';
	}

	public function getDisplayName(): ?string {
		return 'knowledgeagentmemoryadmindisplay';
	}

	public function getOrder(): int {
		return 40;
	}
}

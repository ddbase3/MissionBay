<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use Base3\Authorization\Api\IAuthorizationService;
use Base3\Translation\Api\ITranslation;
use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class AgentadminSettingsTab extends AbstractFeatureSettingsTab {

	/** @param array<string,mixed> $agentAdminDisplayData Host-independent FileManager settings from MissionBay composition. */
	public function __construct(
		ITranslation $translation,
		IAuthorizationService $authorization,
		private readonly array $agentAdminDisplayData = []
	) {
		parent::__construct($translation, $authorization);
	}

	public function getDisplayData(): mixed {
		return $this->agentAdminDisplayData !== [] ? $this->agentAdminDisplayData : null;
	}

	public function getParentTabName(): ?string {
		return 'agentssettingstab';
	}

	public function getDisplayName(): ?string {
		return 'agentadmindisplay';
	}

	public function getOrder(): int {
		return 10;
	}
}

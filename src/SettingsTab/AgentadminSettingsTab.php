<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\SettingsTab;

use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\UiFoundation\Settings\AbstractFeatureSettingsTab;

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

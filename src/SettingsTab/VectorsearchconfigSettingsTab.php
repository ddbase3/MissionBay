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
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Permission;
use Base3\MissionBay\Feature\VectorSearchConfigFeature;
use Throwable;
use Base3\UiFoundation\Settings\AbstractSettingsTab;

/** Reuses the existing vector-search configuration feature. */
final class VectorsearchconfigSettingsTab extends AbstractSettingsTab {

	public function __construct(
		ITranslation $translation,
		private readonly IAuthorizationService $authorization
	) {
		parent::__construct($translation);
	}

	public function getParentTabName(): ?string {
		return 'providersettingstab';
	}

	public function getDisplayName(): ?string {
		return 'vectorsearchconfigdisplay';
	}

	public function getOrder(): int {
		return 110;
	}

	public function isVisible(): bool {
		try {
			return $this->authorization->decide(new AuthorizationRequest(
				$this->authorization->getDefaultAuthority(),
				Permission::for('feature', VectorSearchConfigFeature::getName())
			))->isAllowed();
		} catch (Throwable) {
			return false;
		}
	}
}

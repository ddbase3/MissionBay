<?php declare(strict_types=1);

namespace MissionBay\SettingsTab;

use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Permission;
use MissionBay\Feature\VectorSearchConfigFeature;
use Throwable;
use UiFoundation\Settings\AbstractSettingsTab;

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

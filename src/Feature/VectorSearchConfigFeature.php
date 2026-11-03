<?php declare(strict_types=1);

namespace MissionBay\Feature;

use Base3\Authorization\Api\IFeature;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Permission;

/** Direct authorization feature for the standalone vector-search configuration display. */
final class VectorSearchConfigFeature implements IFeature {

	public function __construct(private readonly ITranslation $translation) {}

	public static function getName(): string {
		return 'vectorsearchconfigfeature';
	}

	public function getLabel(): string {
		return $this->translation->translate('Features', self::getName(), 'label');
	}

	public function getDescription(): string {
		return $this->translation->translate('Features', self::getName(), 'description');
	}

	public function getPermissions(): array {
		return [Permission::for('feature', self::getName())];
	}

	public function getDefaultAudience(): array {
		return ['group' => 'administration'];
	}
}

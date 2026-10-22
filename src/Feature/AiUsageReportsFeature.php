<?php declare(strict_types=1);

namespace MissionBay\Feature;

use Base3\Authorization\Api\IFeature;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Permission;

/** Discoverable authorization definition for MissionBay AI usage reports. */
final class AiUsageReportsFeature implements IFeature {

	public function __construct(private readonly ITranslation $translation) {}

	public static function getName(): string {
		return 'aiusagereportsfeature';
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
		return ['group' => 'development'];
	}
}

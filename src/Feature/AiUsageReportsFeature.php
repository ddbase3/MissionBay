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

namespace Base3\MissionBay\Feature;

use Base3\CoreFoundation\Authorization\Api\IFeature;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Permission;

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

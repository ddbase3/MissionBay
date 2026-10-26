<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Service;

use CredentialFoundation\Api\IEncryptionTargetProvider;
use CredentialFoundation\Dto\EncryptionTargetDefinition;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Resource\DatabaseMemoryAgentResource;

/**
 * Makes actual configured database-memory presets available to KeyHarbor.
 *
 * A discoverable resource class is not a configured consumer. Each stored
 * component preset is independently addressable and can have its own profile.
 */
final class DatabaseMemoryEncryptionTargetProvider implements IEncryptionTargetProvider {

	public function __construct(
		private readonly IAgentComponentPresetRepository $presets
	) {}

	public static function getName(): string {
		return 'databasememoryencryptiontargetprovider';
	}

	/**
	 * @return EncryptionTargetDefinition[] Configured database-memory presets
	 * @throws \RuntimeException If two distinct presets resolve to the same runtime resource ID
	 */
	public function getTargets(): array {
		$result = [];
		foreach ($this->presets->getPresets() as $id => $preset) {
			if (!is_array($preset) || ($preset['type'] ?? null) !== DatabaseMemoryAgentResource::getName()) {
				continue;
			}

			$targetId = DatabaseMemoryAgentResource::encryptionTargetId(
				AgentComponentPresetFlowExpander::getResourceIdForPreset((string)$id)
			);
			if (isset($result[$targetId])) {
				throw new \RuntimeException('Database memory presets have conflicting runtime resource IDs: ' . $targetId);
			}
			$label = trim((string)($preset['label'] ?? ''));
			$result[$targetId] = new EncryptionTargetDefinition(
				$targetId,
				$label !== '' ? $label : (string)$id
			);
		}

		return array_values($result);
	}
}

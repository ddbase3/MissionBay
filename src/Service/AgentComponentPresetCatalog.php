<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Service;

use Base3\Api\IClassMap;
use MissionBay\Api\IAgentComponentPresetCatalog;
use MissionBay\Api\IAgentComponentPresetRepository;

final class AgentComponentPresetCatalog implements IAgentComponentPresetCatalog {

	public function __construct(
		private readonly IAgentComponentPresetRepository $presetRepository,
		private readonly IClassMap $classMap
	) {}

	public function getPresetOptionsByInterface(string $interfaceName): array {
		$result = [];

		foreach($this->presetRepository->getPresets() as $presetId => $preset) {
			$presetId = trim((string)$presetId);
			if($presetId === '' || !$this->isEnabled($preset) || !$this->presetImplements($presetId, $interfaceName)) {
				continue;
			}

			$type = trim((string)($preset['type'] ?? ''));
			$label = trim((string)($preset['label'] ?? $preset['name'] ?? ''));

			$result[] = [
				'id' => $presetId,
				'label' => $label !== '' ? $label : $presetId,
				'type' => $type
			];
		}

		usort($result, static fn(array $left, array $right): int => strcasecmp((string)$left['label'], (string)$right['label']));
		return $result;
	}

	public function presetImplements(string $presetId, string $interfaceName): bool {
		$preset = $this->presetRepository->getPreset(trim($presetId), []);
		if($preset === [] || !$this->isEnabled($preset)) {
			return false;
		}

		$type = trim((string)($preset['type'] ?? ''));
		if($type === '') {
			return false;
		}

		return $this->classMap->getClassByInterfaceName($interfaceName, $type) !== null;
	}

	/** @param array<string,mixed> $preset */
	private function isEnabled(array $preset): bool {
		if(!array_key_exists('enabled', $preset)) {
			return true;
		}

		$value = $preset['enabled'];
		if(is_bool($value)) {
			return $value;
		}
		if(is_int($value)) {
			return $value !== 0;
		}

		return !in_array(strtolower(trim((string)$value)), ['0', 'false', 'no', 'off'], true);
	}
}

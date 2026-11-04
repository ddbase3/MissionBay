<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use MissionBay\Service\AgentConfigFormService;

/** Exposes the existing pure normalization methods without loading UI services. */
final class AgentConfigFormNormalizationProbe extends AgentConfigFormService {

	public function __construct() {}

	protected function getTranslations(): array {
		return [];
	}

	protected function loadAgentComponentPresetSettings(string $preset): ?array {
		return null;
	}

	public function technicalKey(string $value): string { return $this->normalizeTechnicalKey($value); }
	public function technicalKeyList(mixed $value): array { return $this->normalizeTechnicalKeyList($value); }
	public function stringList(mixed $value): array { return $this->normalizeStringList($value); }
	public function boolean(mixed $value): bool { return $this->toBool($value); }
	public function textBlock(string $value): string { return $this->normalizeTextBlock($value); }
	public function capabilitySources(mixed $value): array { return $this->normalizeCapabilitySources($value); }
	public function capabilitySelection(mixed $value, array &$errors): array { return $this->normalizeCapabilitySelection($value, $errors); }
	public function attachCapabilities(mixed $value): array { return $this->normalizeAttachCapabilityList($value); }
	public function attachAs(array $row): array { return $this->normalizeAgentComponentAttachAs($row); }
	public function normalizeComponents(mixed $value, array &$errors): array { return $this->normalizeAgentComponentsInput($value, $errors); }
	public function memoryConfig(array $row, string $order): array { return $this->buildAgentComponentMemoryConfig($row, $order); }
	public function toolConfig(array $row): array { return $this->buildAgentComponentToolConfig($row); }
	public function fixed(mixed $value): array { return $this->fixedValue($value); }
	public function readFixed(array $config, string $key, mixed $default): mixed { return $this->getFixedConfigValue($config, $key, $default); }
	public function redacted(mixed $value): mixed { return $this->redactSensitiveData($value); }
	public function sensitive(string $key): bool { return $this->isSensitiveExportKey($key); }
	public function matchingPresets(array $options, string $capability): array { return $this->filterPresetOptionsByCapability($options, $capability); }
	public function otherPresets(array $options, string $capability): array { return $this->filterPresetOptionsWithoutCapability($options, $capability); }
}

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

namespace MissionBay\Resource;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Service\ServiceConfig;

abstract class AbstractConfiguredServiceAgentResource extends AbstractAgentResource {

	protected array|string|null $serviceConfig = null;

	/**
	 * @var array<string,mixed>
	 */
	protected array $resolvedOptions = [];

	/**
	 * @var array<string,mixed>
	 */
	protected array $optionOverrides = [];

	public function __construct(
		protected readonly IAgentConfigValueResolver $resolver,
		protected readonly ISettingsStore $settingsStore,
		?string $id = null
	) {
		parent::__construct($id);
	}

	/**
	 * Stores the configured service reference without resolving its runtime implementation.
	 * Resolution happens on first operational access or when resolved options are requested.
	 */
	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->setServiceConfigFromResourceConfig($config);
		$this->resolvedOptions = [];
	}

	public function getOptions(): array {
		$this->ensureConfigured();

		return $this->resolvedOptions;
	}

	/**
	 * @param array<string,mixed> $options
	 */
	public function setOptions(array $options): void {
		$this->optionOverrides = array_merge($this->optionOverrides, $options);
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);

		$this->applyResolvedOptions();
	}

	abstract protected function ensureConfigured(): void;

	abstract protected function applyResolvedOptions(): void;

	protected function setServiceConfigFromResourceConfig(array $config): void {
		$this->serviceConfig = $config['service'] ?? null;
	}

	protected function resolveServiceId(): string {
		$value = $this->resolver->resolveValue($this->serviceConfig);

		if(!is_scalar($value) && $value !== null) {
			return '';
		}

		return $this->normalizeKey((string)$value);
	}

	/**
	 * @return array<int,string>
	 */
	protected function listConfiguredServiceIds(string $settingsGroup, string $expectedServiceType): array {
		$records = $this->settingsStore->getGroup($settingsGroup);

		if(!is_array($records)) {
			return [];
		}

		$result = [];

		foreach($records as $id => $settings) {
			if(!is_string($id) || $id === '' || !is_array($settings)) {
				continue;
			}

			$config = ServiceConfig::fromSettings($id, $settings);

			if(!$config->isEnabled() || $config->getServiceType() !== $expectedServiceType) {
				continue;
			}

			$serviceId = $this->normalizeKey($config->getId());

			if($serviceId !== '') {
				$result[$serviceId] = $serviceId;
			}
		}

		$result = array_values($result);
		sort($result);

		return $result;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function buildConfiguredServiceSchema(string $settingsGroup, string $serviceType, string $description): array {
		$service = [
			'type' => 'string',
			'description' => $description
		];
		$serviceIds = $this->listConfiguredServiceIds($settingsGroup, $serviceType);

		if($serviceIds !== []) {
			$service['enum'] = $serviceIds;
		}

		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'service' => $service
			],
			'required' => ['service']
		];
	}

	protected function getIntResolvedOption(string $key, int $default): int {
		$value = $this->resolvedOptions[$key] ?? null;

		if($value === null || $value === '' || !is_numeric($value)) {
			return $default;
		}

		$value = (int)$value;

		return $value > 0 ? $value : $default;
	}

	protected function isSensitiveKey(string $key): bool {
		return in_array($key, [
			'apikey',
			'api_key',
			'auth_secret',
			'secretvalue',
			'secret_value',
			'token',
			'secret',
			'password',
			'authorization'
		], true);
	}

	protected function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	protected function toBool(mixed $value, bool $default): bool {
		if($value === null || $value === '') {
			return $default;
		}

		if(is_bool($value)) {
			return $value;
		}

		if(is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));

		if(in_array($value, ['1', 'true', 'yes', 'on'], true)) {
			return true;
		}

		if(in_array($value, ['0', 'false', 'no', 'off'], true)) {
			return false;
		}

		return $default;
	}
}

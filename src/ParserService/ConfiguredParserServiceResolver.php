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

namespace MissionBay\ParserService;

use AssistantFoundation\Dto\ParserServiceDefinition;
use MissionBay\Api\IConfiguredParserServiceResolver;
use MissionBay\Api\IParserService;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use RuntimeException;

/**
 * Resolves configured parser service records into isolated runtime parser instances.
 */
final class ConfiguredParserServiceResolver implements IConfiguredParserServiceResolver {

	private const PARSER_SETTINGS_GROUP = 'service-parser';
	private const SERVICE_TYPE = 'parser';
	private const SERVICE_ALIAS = 'parser';

	public function __construct(
		private readonly ConfiguredServiceRuntimeResolver $runtimeResolver
	) {}

	public function listServiceIds(): array {
		return $this->runtimeResolver->listServiceIds(
			self::PARSER_SETTINGS_GROUP,
			self::SERVICE_TYPE
		);
	}

	public function getPriority(string $serviceId): int {
		return $this->describe($serviceId)->getPriority();
	}

	public function describe(string $serviceId): ParserServiceDefinition {
		$config = $this->runtimeResolver->loadServiceConfig(
			self::PARSER_SETTINGS_GROUP,
			$serviceId,
			self::SERVICE_TYPE
		);
		$driverDefinition = $this->runtimeResolver->resolveDriverDefinition(
			$config->getDriver(),
			self::SERVICE_TYPE,
			IParserService::class
		);
		$defaults = $driverDefinition->getDefaultConfig();
		$defaultOptions = is_array($defaults['options'] ?? null) ? $defaults['options'] : [];
		$options = array_merge($defaultOptions, $config->getOptions());

		return new ParserServiceDefinition(
			id: $config->getId(),
			name: $config->getName(),
			driver: $config->getDriver(),
			priority: $this->normalizePriority($options['priority'] ?? null),
			supportedTypes: $this->normalizeList($options['supportedTypes'] ?? ['file']),
			supportedExtensions: $this->normalizeList($options['supportedExtensions'] ?? [])
		);
	}

	public function resolve(string $serviceId, array $optionOverrides = []): IParserService {
		$service = $this->runtimeResolver->resolve(
			self::PARSER_SETTINGS_GROUP,
			$serviceId,
			self::SERVICE_TYPE,
			self::SERVICE_ALIAS,
			IParserService::class,
			$optionOverrides
		);

		if(!$service instanceof IParserService) {
			throw new RuntimeException('Configured parser service could not be initialized.');
		}

		return $service;
	}

	public function resolveSettings(string $serviceId, array $settings, array $optionOverrides = []): IParserService {
		$service = $this->runtimeResolver->resolveSettings(
			$serviceId,
			$settings,
			self::SERVICE_TYPE,
			self::SERVICE_ALIAS,
			IParserService::class,
			$optionOverrides
		);

		if(!$service instanceof IParserService) {
			throw new RuntimeException('Configured parser service could not be initialized.');
		}

		return $service;
	}

	private function normalizePriority(mixed $value): int {
		if($value === null || $value === '' || !is_numeric($value)) {
			return 50;
		}

		return max(0, (int)$value);
	}

	/**
	 * @return array<int,string>
	 */
	private function normalizeList(mixed $value): array {
		if(is_string($value)) {
			$value = preg_split('/[\r\n,]+/', $value) ?: [];
		}

		if(!is_array($value)) {
			return [];
		}

		$result = [];
		foreach($value as $item) {
			$item = strtolower(ltrim(trim((string)$item), '.'));
			if($item !== '') {
				$result[$item] = $item;
			}
		}

		return array_values($result);
	}

}

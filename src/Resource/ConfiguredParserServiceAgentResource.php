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

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContentParser;
use MissionBay\Api\IParserService;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;
use MissionBay\ParserService\DoclingParserService;
use MissionBay\ParserService\UnstructuredParserService;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

/**
 * ConfiguredParserServiceAgentResource
 *
 * Loads a configured parser service and delegates to the matching parser adapter.
 */
final class ConfiguredParserServiceAgentResource extends AbstractConfiguredServiceAgentResource implements IAgentContentParser {

	private const PARSER_SETTINGS_GROUP = 'service-parser';
	private const CONNECTION_SETTINGS_GROUP = 'connection';
	private const SERVICE_TYPE = 'parser';
	private const SERVICE_ALIAS = 'parser';

	private ?IParserService $service = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredparserserviceagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured parser service by id and delegates content parsing to the matching parser adapter.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->setServiceConfigFromResourceConfig($config);
		$this->service = null;
		$this->resolvedOptions = [];

		$this->configureService();
	}

	public function getPriority(): int {
		return $this->ensureService()->getPriority();
	}

	public function supports(AgentContentItem $item): bool {
		return $this->ensureService()->supports($item);
	}

	public function parse(AgentContentItem $item): AgentParsedContent {
		return $this->ensureService()->parse($item);
	}

	protected function ensureConfigured(): void {
		$this->ensureService();
	}

	protected function applyResolvedOptions(): void {
		if($this->service instanceof IParserService) {
			$this->service->setOptions($this->resolvedOptions);
		}
	}

	private function ensureService(): IParserService {
		if($this->service instanceof IParserService) {
			return $this->service;
		}

		$this->configureService();

		if(!$this->service instanceof IParserService) {
			throw new RuntimeException('Configured parser service could not be initialized.');
		}

		return $this->service;
	}

	private function configureService(): void {
		$serviceId = $this->resolveServiceId();

		if($serviceId === '') {
			throw new RuntimeException('ConfiguredParserServiceAgentResource requires config key "service".');
		}

		$serviceConfig = $this->loadServiceConfig(self::PARSER_SETTINGS_GROUP, $serviceId, self::SERVICE_TYPE);
		$connectionConfig = $this->loadConnectionConfig(self::CONNECTION_SETTINGS_GROUP, $serviceConfig->getConnectionId());
		$serviceName = $this->resolveParserServiceName($serviceConfig->getDriver());

		if($serviceName === '') {
			throw new RuntimeException(
				'Parser service config has no usable driver: ' . $serviceId . ' ' . $this->formatConfigDebug($serviceConfig->toSettings())
			);
		}

		$service = $this->classMap->getInstanceByInterfaceName(IParserService::class, $serviceName);

		if(!$service instanceof IParserService) {
			throw new RuntimeException(
				'Unable to resolve parser service "' . $serviceName . '" for driver "' . $serviceConfig->getDriver() . '".'
			);
		}

		$this->resolvedOptions = $this->buildRuntimeOptions($serviceConfig, $connectionConfig);
		$this->resolvedOptions = array_merge($this->resolvedOptions, $this->optionOverrides);

		$this->service = $service;
		$this->applyResolvedOptions();
	}

	private function resolveParserServiceName(string $driver): string {
		$map = [
			'unstructured-parser' => UnstructuredParserService::getName(),
			'docling-parser' => DoclingParserService::getName()
		];

		return $map[$driver] ?? '';
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildRuntimeOptions(ServiceConfig $serviceConfig, ConnectionConfig $connectionConfig): array {
		$options = $this->buildBaseRuntimeOptions($serviceConfig, $connectionConfig, self::SERVICE_ALIAS);
		$serviceOptions = $serviceConfig->getOptions();

		$options = $this->mergeServiceOptions($options, $serviceOptions, [
			'parser_id' => true,
			'parser_label' => true,
			'service_type' => true,
			'service_driver' => true,
			'connection_id' => true,
			'connection_label' => true,
			'connection_type' => true,
			'connection_driver' => true,
			'auth_type' => true,
			'auth_header_name' => true,
			'model' => true,
			'endpoint' => true,
			'base_url' => true,
			'apikey' => true,
			'auth_secret' => true
		]);

		$this->mapOptionalNumber($options, $serviceOptions, 'priority', 'priority', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'maxBytes', 'max_bytes', 'int');
		$this->mapOptionalString($options, $serviceOptions, 'contentType', 'content_type');
		$this->mapOptionalString($options, $serviceOptions, 'fileField', 'file_field');
		$this->mapOptionalArray($options, $serviceOptions, 'supportedTypes', 'supported_types');

		return $options;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $sourceOptions
	 */
	private function mapOptionalString(array &$runtimeOptions, array $sourceOptions, string $sourceKey, string $targetKey): void {
		if(!array_key_exists($sourceKey, $sourceOptions)) {
			return;
		}

		$value = trim((string)$sourceOptions[$sourceKey]);

		if($value === '') {
			return;
		}

		$runtimeOptions[$targetKey] = $value;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $sourceOptions
	 */
	private function mapOptionalArray(array &$runtimeOptions, array $sourceOptions, string $sourceKey, string $targetKey): void {
		if(!array_key_exists($sourceKey, $sourceOptions)) {
			return;
		}

		$value = $sourceOptions[$sourceKey];

		if(is_string($value)) {
			$value = preg_split('/[\r\n,]+/', $value) ?: [];
		}

		if(!is_array($value)) {
			return;
		}

		$out = [];

		foreach($value as $item) {
			$item = strtolower(trim((string)$item));

			if($item !== '') {
				$out[] = $item;
			}
		}

		if($out === []) {
			return;
		}

		$runtimeOptions[$targetKey] = array_values(array_unique($out));
	}
}

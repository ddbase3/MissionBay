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

use AssistantFoundation\Api\IConfigurableVectorSearch;
use AssistantFoundation\Api\IVectorSearch;
use Base3\Api\IClassMap;
use Base3\Api\ISchemaProvider;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

/**
 * Loads a configured vector-search service and delegates similarity searches.
 */
final class ConfiguredVectorSearchAgentResource extends AbstractConfiguredServiceAgentResource implements IVectorSearch, ISchemaProvider {

	private const SETTINGS_GROUP = 'service-vectorsearch';
	private const CONNECTION_SETTINGS_GROUP = 'connection';
	private const SERVICE_TYPE = 'vectorsearch';
	private const SERVICE_ALIAS = 'vectorsearch';

	private ?IConfigurableVectorSearch $service = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredvectorsearchagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured vector-search service by id and delegates similarity searches.';
	}

	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'service' => [
					'type' => 'string',
					'description' => 'Configured vector-search service id from the service-vectorsearch settings group.'
				]
			],
			'required' => ['service']
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->service = null;
	}

	public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
		return $this->ensureService()->search($vector, $limit, $minScore);
	}

	protected function ensureConfigured(): void {
		$this->ensureService();
	}

	protected function applyResolvedOptions(): void {
		if($this->service instanceof IConfigurableVectorSearch) {
			$this->service->setOptions($this->resolvedOptions);
		}
	}

	private function ensureService(): IConfigurableVectorSearch {
		if($this->service instanceof IConfigurableVectorSearch) {
			return $this->service;
		}

		$this->configureService();

		if(!$this->service instanceof IConfigurableVectorSearch) {
			throw new RuntimeException('Configured vector-search service could not be initialized.');
		}

		return $this->service;
	}

	private function configureService(): void {
		$serviceId = $this->resolveServiceId();

		if($serviceId === '') {
			throw new RuntimeException('ConfiguredVectorSearchAgentResource requires config key "service".');
		}

		$serviceConfig = $this->loadServiceConfig(self::SETTINGS_GROUP, $serviceId, self::SERVICE_TYPE);
		$connectionConfig = $this->loadConnectionConfig(self::CONNECTION_SETTINGS_GROUP, $serviceConfig->getConnectionId());
		$serviceName = $this->resolveServiceImplementationName(
			$this->classMap,
			$serviceConfig->getDriver(),
			self::SERVICE_TYPE,
			IConfigurableVectorSearch::class
		);

		if($serviceName === '') {
			throw new RuntimeException(
				'Vector-search config has no usable driver: ' . $serviceId . ' ' . $this->formatConfigDebug($serviceConfig->toSettings())
			);
		}

		$service = $this->classMap->getInstanceByInterfaceName(IConfigurableVectorSearch::class, $serviceName);

		if(!$service instanceof IConfigurableVectorSearch) {
			throw new RuntimeException(
				'Unable to resolve vector-search service "' . $serviceName . '" for driver "' . $serviceConfig->getDriver() . '".'
			);
		}

		$this->resolvedOptions = $this->buildRuntimeOptions($serviceConfig, $connectionConfig);
		$this->resolvedOptions = array_merge($this->resolvedOptions, $this->optionOverrides);
		$this->service = $service;
		$this->applyResolvedOptions();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildRuntimeOptions(ServiceConfig $serviceConfig, ConnectionConfig $connectionConfig): array {
		$options = $this->buildBaseRuntimeOptions($serviceConfig, $connectionConfig, self::SERVICE_ALIAS);
		$serviceOptions = $serviceConfig->getOptions();

		$options = $this->mergeServiceOptions($options, $serviceOptions, [
			'vectorsearch_id' => true,
			'vectorsearch_label' => true,
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
			'auth_secret' => true,
			'timeout_seconds' => true,
			'connect_timeout_seconds' => true
		]);


		return $options;
	}
}

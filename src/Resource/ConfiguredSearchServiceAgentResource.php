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
use MissionBay\Api\ISearchService;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\SearchService\MistralWebSearchService;
use MissionBay\SearchService\OpenAiWebSearchService;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

/**
 * ConfiguredSearchServiceAgentResource
 *
 * Loads a configured search service and delegates to the matching
 * ISearchService adapter.
 */
class ConfiguredSearchServiceAgentResource extends AbstractConfiguredServiceAgentResource implements ISearchService {

	private const SEARCH_SETTINGS_GROUP = 'service-search';
	private const CONNECTION_SETTINGS_GROUP = 'connection';
	private const SERVICE_TYPE = 'search';
	private const SERVICE_ALIAS = 'search';

	private ?ISearchService $service = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredsearchserviceagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured web search service by id and delegates to the matching search adapter.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->setServiceConfigFromResourceConfig($config);
		$this->service = null;
		$this->resolvedOptions = [];

		$this->configureService();
	}

	public function search(string $query, array $options = []): array {
		return $this->ensureService()->search($query, $options);
	}

	protected function ensureConfigured(): void {
		$this->ensureService();
	}

	protected function applyResolvedOptions(): void {
		if($this->service instanceof ISearchService) {
			$this->service->setOptions($this->resolvedOptions);
		}
	}

	private function ensureService(): ISearchService {
		if($this->service instanceof ISearchService) {
			return $this->service;
		}

		$this->configureService();

		if(!$this->service instanceof ISearchService) {
			throw new RuntimeException('Configured search service could not be initialized.');
		}

		return $this->service;
	}

	private function configureService(): void {
		$serviceId = $this->resolveServiceId();

		if($serviceId === '') {
			throw new RuntimeException('ConfiguredSearchServiceAgentResource requires config key "service".');
		}

		$serviceConfig = $this->loadServiceConfig(self::SEARCH_SETTINGS_GROUP, $serviceId, self::SERVICE_TYPE);
		$connectionConfig = $this->loadConnectionConfig(self::CONNECTION_SETTINGS_GROUP, $serviceConfig->getConnectionId());

		$serviceName = $this->resolveSearchServiceName($serviceConfig->getDriver());

		if($serviceName === '') {
			throw new RuntimeException(
				'Search service config has no usable driver: ' . $serviceId . ' ' . $this->formatConfigDebug($serviceConfig->toSettings())
			);
		}

		$service = $this->classMap->getInstanceByInterfaceName(ISearchService::class, $serviceName);

		if(!$service instanceof ISearchService) {
			throw new RuntimeException(
				'Unable to resolve search service "' . $serviceName . '" for driver "' . $serviceConfig->getDriver() . '".'
			);
		}

		$this->resolvedOptions = $this->buildRuntimeOptions($serviceConfig, $connectionConfig);
		$this->resolvedOptions = array_merge($this->resolvedOptions, $this->optionOverrides);

		$this->service = $service;
		$this->applyResolvedOptions();
	}

	private function resolveSearchServiceName(string $driver): string {
		$map = [
			'openai-websearch' => OpenAiWebSearchService::getName(),
			'mistral-websearch' => MistralWebSearchService::getName()
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
			'search_id' => true,
			'search_label' => true,
			'service_type' => true,
			'service_driver' => true,
			'connection_id' => true,
			'connection_label' => true,
			'connection_type' => true,
			'connection_driver' => true,
			'model' => true,
			'endpoint' => true,
			'apikey' => true
		]);

		$this->mapOptionalNumber($options, $serviceOptions, 'maxResults', 'max_results', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');
		$this->mapOptionalBool($options, $serviceOptions, 'externalWebAccess', 'external_web_access');
		$this->mapOptionalString($options, $serviceOptions, 'searchContextSize', 'search_context_size');
		$this->mapOptionalString($options, $serviceOptions, 'returnTokenBudget', 'return_token_budget');
		$this->mapOptionalString($options, $serviceOptions, 'toolChoice', 'tool_choice');
		$this->mapOptionalArray($options, $serviceOptions, 'allowedDomains', 'allowed_domains');
		$this->mapOptionalArray($options, $serviceOptions, 'blockedDomains', 'blocked_domains');

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

		if(!is_array($value)) {
			return;
		}

		$out = [];

		foreach($value as $item) {
			$item = trim((string)$item);

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

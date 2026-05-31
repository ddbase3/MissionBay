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
use MissionBay\Api\IAgentVectorStore;
use MissionBay\Api\IVectorStoreService;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Dto\AgentEmbeddingChunk;
use MissionBay\Service\ServiceConfig;
use MissionBay\VectorStore\QdrantVectorStoreService;
use RuntimeException;

/**
 * ConfiguredVectorStoreAgentResource
 *
 * Loads a configured vector store service and delegates vector storage
 * operations to the matching backend adapter.
 */
final class ConfiguredVectorStoreAgentResource extends AbstractConfiguredServiceAgentResource implements IAgentVectorStore {

	private const VECTORSTORE_SETTINGS_GROUP = 'service-vectorstore';
	private const CONNECTION_SETTINGS_GROUP = 'connection';
	private const SERVICE_TYPE = 'vectorstore';
	private const SERVICE_ALIAS = 'vectorstore';

	private ?IVectorStoreService $service = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredvectorstoreagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured vector store service by id and delegates vector store operations.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->setServiceConfigFromResourceConfig($config);
		$this->service = null;
		$this->resolvedOptions = [];

		$this->configureService();
	}

	public function upsert(AgentEmbeddingChunk $chunk): void {
		$this->ensureService()->upsert($chunk);
	}

	public function existsByHash(string $collectionKey, string $hash): bool {
		return $this->ensureService()->existsByHash($collectionKey, $hash);
	}

	public function existsByFilter(string $collectionKey, array $filter): bool {
		return $this->ensureService()->existsByFilter($collectionKey, $filter);
	}

	public function deleteByFilter(string $collectionKey, array $filter): int {
		return $this->ensureService()->deleteByFilter($collectionKey, $filter);
	}

	public function search(string $collectionKey, array $vector, int $limit = 3, ?float $minScore = null, ?array $filterSpec = null): array {
		return $this->ensureService()->search($collectionKey, $vector, $limit, $minScore, $filterSpec);
	}

	public function createCollection(string $collectionKey): void {
		$this->ensureService()->createCollection($collectionKey);
	}

	public function deleteCollection(string $collectionKey): void {
		$this->ensureService()->deleteCollection($collectionKey);
	}

	public function getInfo(string $collectionKey): array {
		return $this->ensureService()->getInfo($collectionKey);
	}

	protected function ensureConfigured(): void {
		$this->ensureService();
	}

	protected function applyResolvedOptions(): void {
		if($this->service instanceof IVectorStoreService) {
			$this->service->setOptions($this->resolvedOptions);
		}
	}

	private function ensureService(): IVectorStoreService {
		if($this->service instanceof IVectorStoreService) {
			return $this->service;
		}

		$this->configureService();

		if(!$this->service instanceof IVectorStoreService) {
			throw new RuntimeException('Configured vector store service could not be initialized.');
		}

		return $this->service;
	}

	private function configureService(): void {
		$serviceId = $this->resolveServiceId();

		if($serviceId === '') {
			throw new RuntimeException('ConfiguredVectorStoreAgentResource requires config key "service".');
		}

		$serviceConfig = $this->loadServiceConfig(self::VECTORSTORE_SETTINGS_GROUP, $serviceId, self::SERVICE_TYPE);
		$connectionConfig = $this->loadConnectionConfig(self::CONNECTION_SETTINGS_GROUP, $serviceConfig->getConnectionId());
		$serviceName = $this->resolveVectorStoreServiceName($serviceConfig->getDriver());

		if($serviceName === '') {
			throw new RuntimeException(
				'Vector store config has no usable driver: ' . $serviceId . ' ' . $this->formatConfigDebug($serviceConfig->toSettings())
			);
		}

		$service = $this->classMap->getInstanceByInterfaceName(IVectorStoreService::class, $serviceName);

		if(!$service instanceof IVectorStoreService) {
			throw new RuntimeException(
				'Unable to resolve vector store service "' . $serviceName . '" for driver "' . $serviceConfig->getDriver() . '".'
			);
		}

		$this->resolvedOptions = $this->buildRuntimeOptions($serviceConfig, $connectionConfig);
		$this->resolvedOptions = array_merge($this->resolvedOptions, $this->optionOverrides);

		$this->service = $service;
		$this->applyResolvedOptions();
	}

	private function resolveVectorStoreServiceName(string $driver): string {
		$map = [
			'qdrant-vectorstore' => QdrantVectorStoreService::getName()
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
			'vectorstore_id' => true,
			'vectorstore_label' => true,
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

		$this->mapOptionalBool($options, $serviceOptions, 'createPayloadIndexes', 'create_payload_indexes');
		$this->mapOptionalNumber($options, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');

		return $options;
	}
}

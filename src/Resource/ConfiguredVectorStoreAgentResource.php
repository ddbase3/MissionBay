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

use AssistantFoundation\Api\IRetrievalIndex;
use AssistantFoundation\Api\IRetrievalIndexInspector;
use AssistantFoundation\Dto\RetrievalIndexItem;
use AssistantFoundation\Dto\RetrievalSearchRequest;
use AssistantFoundation\Dto\RetrievalSearchResult;
use Base3\Api\ISchemaProvider;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use MissionBay\Api\IVectorStoreService;
use RuntimeException;

/**
 * Loads a configured vector-store service and delegates retrieval-index
 * operations to the matching backend adapter.
 */
final class ConfiguredVectorStoreAgentResource extends AbstractConfiguredServiceAgentResource implements IRetrievalIndex, IRetrievalIndexInspector, ISchemaProvider {

	private const VECTORSTORE_SETTINGS_GROUP = 'service-vectorstore';
	private const SERVICE_TYPE = 'vectorstore';
	private const SERVICE_ALIAS = 'vectorstore';

	private ?IVectorStoreService $service = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly ConfiguredServiceRuntimeResolver $runtimeResolver,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredvectorstoreagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured vector-store service by id and delegates retrieval-index operations.';
	}

	public function getSchema(): array {
		return $this->buildConfiguredServiceSchema(
			self::VECTORSTORE_SETTINGS_GROUP,
			self::SERVICE_TYPE,
			'Configured vector-store service id from the service-vectorstore settings group.'
		);
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);
		$this->service = null;
	}

	public function upsert(RetrievalIndexItem $item): void {
		$this->ensureService()->upsert($item);
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

	public function search(RetrievalSearchRequest $request): RetrievalSearchResult {
		return $this->ensureService()->search($request);
	}

	public function context(
		string $collectionKey,
		string $pointId,
		int $before = 1,
		int $after = 1,
		?array $filterSpec = null
	): RetrievalSearchResult {
		return $this->ensureService()->context($collectionKey, $pointId, $before, $after, $filterSpec);
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

	public function inspectPoints(
		string $collectionKey,
		int $limit = 10,
		?array $filterSpec = null,
		string|int|null $offset = null,
		bool $withVectorSummary = false
	): array {
		return $this->ensureService()->inspectPoints(
			$collectionKey,
			$limit,
			$filterSpec,
			$offset,
			$withVectorSummary
		);
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
			throw new RuntimeException(static::class . ' requires config key "service".');
		}

		$service = $this->runtimeResolver->resolve(
			self::VECTORSTORE_SETTINGS_GROUP,
			$serviceId,
			self::SERVICE_TYPE,
			self::SERVICE_ALIAS,
			IVectorStoreService::class,
			$this->optionOverrides
		);

		if(!$service instanceof IVectorStoreService) {
			throw new RuntimeException('Configured vector store service could not be initialized.');
		}

		$this->service = $service;
		$this->resolvedOptions = $service->getOptions();
	}
}

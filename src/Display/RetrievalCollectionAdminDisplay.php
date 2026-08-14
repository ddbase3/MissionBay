<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Display;

use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use AssistantFoundation\Api\IRetrievalIndex;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IEmbeddingOrchestratorConfigRepository;
use MissionBay\Api\IRetrievalCollectionConfigRepository;

/**
 * Manages logical retrieval collection keys and their physical backend collections.
 */
final class RetrievalCollectionAdminDisplay implements IDisplay {

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IRetrievalCollectionConfigRepository $collectionRepository,
		private readonly IEmbeddingOrchestratorConfigRepository $orchestratorConfigRepository,
		private readonly IAgentComponentPresetMaterializer $presetMaterializer,
		private readonly IRetrievalCollectionDefinition $collectionDefinition
	) {}

	public static function getName(): string {
		return 'retrievalcollectionadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		return 'Manages logical retrieval collection keys, backend collection names and backend lifecycle.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if(strtolower($out) === 'json') {
			return $this->handleJson($final);
		}

		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/RetrievalCollectionAdminDisplay.php');
		$this->view->assign('service', $this->linkTargetService->getLink([
			'name' => self::getName(),
			'out' => 'json'
		]));

		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final): string {
		try {
			$payload = $this->request->getJsonBody();
			$payload = is_array($payload) ? $payload : [];
			$action = strtolower(trim((string)($payload['action'] ?? 'bootstrap')));

			$response = match($action) {
				'save' => $this->save($payload),
				'remove' => $this->remove($payload),
				'info' => $this->info($payload),
				'create' => $this->createBackend($payload),
				'delete' => $this->deleteBackend($payload),
				default => $this->bootstrap()
			};
		}
		catch(\Throwable $e) {
			$response = [
				'ok' => false,
				'error' => $e->getMessage()
			];
		}

		if($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string)json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	}

	/** @return array<string,mixed> */
	private function bootstrap(): array {
		return [
			'ok' => true,
			'collections' => $this->collectionRows(),
			'orchestrator' => $this->orchestratorConfigRepository->getConfig()
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function save(array $payload): array {
		$oldKey = trim((string)($payload['old_key'] ?? ''));
		$collectionKey = trim((string)($payload['collection_key'] ?? ''));
		$backendCollection = trim((string)($payload['backend_collection'] ?? ''));

		if($oldKey !== '' && $oldKey !== $collectionKey) {
			$this->assertNotActiveCollection($oldKey, 'rename');
		}

		$this->collectionRepository->saveCollection($collectionKey, $backendCollection);

		if($oldKey !== '' && $oldKey !== $collectionKey) {
			$this->collectionRepository->removeCollection($oldKey);
		}

		return [
			'ok' => true,
			'message' => 'Collection mapping saved.',
			'collections' => $this->collectionRows()
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function remove(array $payload): array {
		$collectionKey = $this->requireCollectionKey($payload);
		$this->assertNotActiveCollection($collectionKey, 'remove');
		$this->collectionRepository->removeCollection($collectionKey);

		return [
			'ok' => true,
			'message' => 'Collection mapping removed. The physical backend collection was not deleted.',
			'collections' => $this->collectionRows()
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function info(array $payload): array {
		$collectionKey = $this->requireCollectionKey($payload);
		$store = $this->loadVectorStore();

		$info = $store->getInfo($collectionKey);

		return [
			'ok' => true,
			'exists' => true,
			'collection_key' => $collectionKey,
			'backend_collection' => $this->collectionDefinition->getBackendCollectionName($collectionKey),
			'info' => $info
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function createBackend(array $payload): array {
		$collectionKey = $this->requireCollectionKey($payload);
		$store = $this->loadVectorStore();
		$store->createCollection($collectionKey);

		return [
			'ok' => true,
			'message' => 'Backend collection created.',
			'exists' => true,
			'collection_key' => $collectionKey,
			'backend_collection' => $this->collectionDefinition->getBackendCollectionName($collectionKey),
			'info' => $store->getInfo($collectionKey)
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function deleteBackend(array $payload): array {
		$collectionKey = $this->requireCollectionKey($payload);
		$store = $this->loadVectorStore();
		$store->deleteCollection($collectionKey);

		return [
			'ok' => true,
			'message' => 'Backend collection deleted. The logical collection mapping remains configured.',
			'exists' => false,
			'collection_key' => $collectionKey,
			'backend_collection' => $this->collectionDefinition->getBackendCollectionName($collectionKey)
		];
	}

	/** @return array<int,array<string,string>> */
	private function collectionRows(): array {
		$rows = [];
		foreach($this->collectionRepository->getCollections() as $collectionKey => $settings) {
			$rows[] = [
				'key' => $collectionKey,
				'backend_collection' => (string)($settings['backend_collection'] ?? '')
			];
		}
		return $rows;
	}

	/** @param array<string,mixed> $payload */
	private function requireCollectionKey(array $payload): string {
		$collectionKey = trim((string)($payload['collection_key'] ?? ''));
		if(!$this->collectionRepository->hasCollection($collectionKey)) {
			throw new \InvalidArgumentException('Unknown collection key: ' . $collectionKey);
		}
		return $collectionKey;
	}

	private function assertNotActiveCollection(string $collectionKey, string $operation): void {
		$config = $this->orchestratorConfigRepository->getConfig();
		if((string)($config['collection_key'] ?? '') === $collectionKey) {
			throw new \RuntimeException('Cannot ' . $operation . ' the collection mapping while it is selected by the embedding orchestrator. Select another collection first.');
		}
	}

	private function loadVectorStore(): IRetrievalIndex {
		$config = $this->orchestratorConfigRepository->getConfig();
		$presetId = trim((string)($config['vector_store_preset'] ?? ''));
		if($presetId === '') {
			throw new \RuntimeException('Embedding orchestrator has no vector-store preset configured.');
		}

		$context = $this->presetMaterializer->createContext([
			'source' => 'retrieval-collection-admin'
		]);
		$materialization = $this->presetMaterializer->materialize($presetId, $context);
		$resource = $materialization->getResource();

		if(!$resource instanceof IRetrievalIndex) {
			$warnings = $materialization->getWarnings();
			$suffix = $warnings !== [] ? ' ' . implode(' ', $warnings) : '';
			throw new \RuntimeException('Configured vector-store preset does not expose IRetrievalIndex: ' . $presetId . $suffix);
		}

		return $resource;
	}
}

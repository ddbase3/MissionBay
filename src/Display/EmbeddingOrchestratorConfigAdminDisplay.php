<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Display;

use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use AssistantFoundation\Api\IRetrievalIndex;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use MissionBay\Api\IAgentComponentPresetCatalog;
use MissionBay\Api\IEmbeddingOrchestratorConfigRepository;

/**
 * Configures the generic embedding-orchestrator resource composition.
 */
final class EmbeddingOrchestratorConfigAdminDisplay implements IDisplay {

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IEmbeddingOrchestratorConfigRepository $configRepository,
		private readonly IAgentComponentPresetCatalog $presetCatalog,
		private readonly IRetrievalCollectionDefinition $collectionDefinition
	) {}

	public static function getName(): string {
		return 'embeddingorchestratorconfigadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		return 'Configures the embedding resource preset, vector-store resource preset and logical retrieval collection.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if(strtolower($out) === 'json') {
			return $this->handleJson($final);
		}

		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/EmbeddingOrchestratorConfigAdminDisplay.php');
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
		$collections = [];
		foreach($this->collectionDefinition->getCollectionKeys() as $collectionKey) {
			$collectionKey = trim((string)$collectionKey);
			if($collectionKey === '') {
				continue;
			}

			$collections[] = [
				'key' => $collectionKey,
				'backend_collection' => $this->collectionDefinition->getBackendCollectionName($collectionKey)
			];
		}

		return [
			'ok' => true,
			'config' => $this->configRepository->getConfig(),
			'embedding_presets' => $this->presetCatalog->getPresetOptionsByInterface(IAiEmbeddingModel::class),
			'vector_store_presets' => $this->presetCatalog->getPresetOptionsByInterface(IRetrievalIndex::class),
			'collections' => $collections
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function save(array $payload): array {
		$embeddingPreset = trim((string)($payload['embedding_preset'] ?? ''));
		$vectorStorePreset = trim((string)($payload['vector_store_preset'] ?? ''));
		$collectionKey = trim((string)($payload['collection_key'] ?? ''));

		if(!$this->presetCatalog->presetImplements($embeddingPreset, IAiEmbeddingModel::class)) {
			throw new \InvalidArgumentException('Selected embedding preset does not expose IAiEmbeddingModel: ' . $embeddingPreset);
		}
		if(!$this->presetCatalog->presetImplements($vectorStorePreset, IRetrievalIndex::class)) {
			throw new \InvalidArgumentException('Selected vector-store preset does not expose IRetrievalIndex: ' . $vectorStorePreset);
		}
		if(!in_array($collectionKey, $this->collectionDefinition->getCollectionKeys(), true)) {
			throw new \InvalidArgumentException('Unknown collection key: ' . $collectionKey);
		}

		$this->configRepository->saveConfig($embeddingPreset, $vectorStorePreset, $collectionKey);

		return [
			'ok' => true,
			'message' => 'Embedding orchestrator configuration saved.',
			'config' => $this->configRepository->getConfig(),
			'backend_collection' => $this->collectionDefinition->getBackendCollectionName($collectionKey)
		];
	}
}

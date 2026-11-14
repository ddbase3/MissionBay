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

namespace Base3\MissionBay\Display;

use Base3\CoreFoundation\Usermanager\Permission;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\AssistantFoundation\Api\IAiEmbeddingModel;
use Base3\AssistantFoundation\Api\IRetrievalCollectionDefinition;
use Base3\AssistantFoundation\Api\IRetrievalIndex;
use Base3\CoreFoundation\Api\IDisplay;
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\MissionBay\Api\IAgentComponentPresetCatalog;
use Base3\MissionBay\Api\IEmbeddingOrchestratorConfigRepository;

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
		private readonly IRetrievalCollectionDefinition $collectionDefinition,
		private readonly IAuthorizationService $settingsAuthorization
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
		if (!$this->settingsAuthorization->decide(new AuthorizationRequest(
			$this->settingsAuthorization->getDefaultAuthority(),
			Permission::for('feature', \Base3\MissionBay\SettingsTab\EmbeddingorchestratorconfigadminSettingsTab::getName())
		))->isAllowed()) {
			http_response_code(403);
			return '';
		}

		if(strtolower($out) === 'json') {
			return $this->handleJson($final);
		}

		$this->view->setPath(dirname(__DIR__, 2));
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

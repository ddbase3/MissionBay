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
use Base3\AssistantFoundation\Api\IRetrievalCollectionDefinition;
use Base3\AssistantFoundation\Api\IRetrievalIndexInspector;
use Base3\CoreFoundation\Api\IDisplay;
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IEmbeddingOrchestratorConfigRepository;

/**
 * Backend-neutral inspector for points stored in the configured retrieval index.
 */
final class RetrievalVectorPointsAdminDisplay implements IDisplay {

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IEmbeddingOrchestratorConfigRepository $orchestratorConfigRepository,
		private readonly IAgentComponentPresetMaterializer $presetMaterializer,
		private readonly IRetrievalCollectionDefinition $collectionDefinition,
		private readonly IAuthorizationService $settingsAuthorization
	) {}

	public static function getName(): string {
		return 'retrievalvectorpointsadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		return 'Inspects stored retrieval points through the backend-neutral IRetrievalIndexInspector contract.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if (!$this->settingsAuthorization->decide(new AuthorizationRequest(
			$this->settingsAuthorization->getDefaultAuthority(),
			Permission::for('feature', \Base3\MissionBay\SettingsTab\RetrievalvectorpointsadminSettingsTab::getName())
		))->isAllowed()) {
			http_response_code(403);
			return '';
		}

		if(strtolower($out) === 'json') {
			return $this->handleJson($final);
		}

		$this->view->setPath(dirname(__DIR__, 2));
		$this->view->setTemplate('Display/RetrievalVectorPointsAdminDisplay.php');
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
				'inspect' => $this->inspect($payload),
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
			$collections[] = [
				'key' => $collectionKey,
				'backend_collection' => $this->collectionDefinition->getBackendCollectionName($collectionKey)
			];
		}

		$config = $this->orchestratorConfigRepository->getConfig();

		return [
			'ok' => true,
			'collections' => $collections,
			'default_collection_key' => (string)($config['collection_key'] ?? '')
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function inspect(array $payload): array {
		$collectionKey = trim((string)($payload['collection_key'] ?? ''));
		if(!in_array($collectionKey, $this->collectionDefinition->getCollectionKeys(), true)) {
			throw new \InvalidArgumentException('Unknown collection key: ' . $collectionKey);
		}

		$limit = max(1, min(100, (int)($payload['limit'] ?? 10)));
		$offset = $payload['offset'] ?? null;
		if(!is_string($offset) && !is_int($offset) && $offset !== null) {
			$offset = null;
		}
		$filter = $payload['filter'] ?? null;
		if($filter instanceof \stdClass) {
			$filter = (array)$filter;
		}
		if($filter !== null && !is_array($filter)) {
			throw new \InvalidArgumentException('Filter must be a structured JSON object or null.');
		}

		$store = $this->loadInspector();
		$result = $store->inspectPoints($collectionKey, $limit, $filter, $offset, true);

		return [
			'ok' => true,
			'result' => $result
		];
	}

	private function loadInspector(): IRetrievalIndexInspector {
		$config = $this->orchestratorConfigRepository->getConfig();
		$presetId = trim((string)($config['vector_store_preset'] ?? ''));
		if($presetId === '') {
			throw new \RuntimeException('Embedding orchestrator has no vector-store preset configured.');
		}

		$context = $this->presetMaterializer->createContext([
			'source' => 'retrieval-vector-points-admin'
		]);
		$materialization = $this->presetMaterializer->materialize($presetId, $context);
		$resource = $materialization->getResource();

		if(!$resource instanceof IRetrievalIndexInspector) {
			$warnings = $materialization->getWarnings();
			$suffix = $warnings !== [] ? ' ' . implode(' ', $warnings) : '';
			throw new \RuntimeException('Configured vector-store preset does not expose IRetrievalIndexInspector: ' . $presetId . $suffix);
		}

		return $resource;
	}
}

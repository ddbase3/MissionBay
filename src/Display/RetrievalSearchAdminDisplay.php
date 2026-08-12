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

namespace MissionBay\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use MissionBay\Api\IRetrievalSearchService;

/**
 * Interactive read-only workbench for configured MissionBay retrieval tools.
 *
 * Search execution is delegated to IRetrievalSearchService so other UIs can
 * reuse the exact same preset materialization and tool invocation boundary.
 */
final class RetrievalSearchAdminDisplay implements IDisplay {

	private const SEARCH_FUNCTION = 'retrieval_search';
	private const CONTEXT_FUNCTION = 'retrieval_context';

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IRetrievalSearchService $retrievalSearchService
	) {}

	public static function getName(): string {
		return 'retrievalsearchadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		return 'Interactive workbench for configured MissionBay retrieval tool presets.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if(strtolower($out) === 'json') {
			return $this->handleJson($final);
		}

		return $this->handleHtml();
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/RetrievalSearchAdminDisplay.php');
		$this->view->assign('service', $this->linkTargetService->getLink([
			'name' => self::getName(),
			'out' => 'json'
		]));

		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final): string {
		$response = $this->buildJsonResponse();

		if($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string)json_encode(
			$response,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		);
	}

	/** @return array<string,mixed> */
	private function buildJsonResponse(): array {
		$payload = $this->request->getJsonBody();
		$payload = is_array($payload) ? $payload : [];
		$action = strtolower(trim((string)($payload['action'] ?? 'bootstrap')));

		try {
			return match($action) {
				'bootstrap' => $this->buildBootstrapResponse(),
				'search' => $this->buildSearchResponse($payload),
				'context' => $this->buildContextResponse($payload),
				default => [
					'ok' => false,
					'error' => 'Unsupported action: ' . $action
				]
			};
		}
		catch(\Throwable $e) {
			return [
				'ok' => false,
				'error' => $e->getMessage(),
				'exception' => $e::class
			];
		}
	}

	/** @return array<string,mixed> */
	private function buildBootstrapResponse(): array {
		return [
			'ok' => true,
			'presets' => $this->retrievalSearchService->getSearchPresets([
				'source' => 'retrieval-search-admin'
			]),
			'search_function' => self::SEARCH_FUNCTION,
			'context_function' => self::CONTEXT_FUNCTION
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function buildSearchResponse(array $payload): array {
		return $this->retrievalSearchService->search(
			trim((string)($payload['preset_id'] ?? '')),
			$this->normalizeArray($payload['arguments'] ?? []),
			['source' => 'retrieval-search-admin']
		);
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function buildContextResponse(array $payload): array {
		return $this->retrievalSearchService->context(
			trim((string)($payload['preset_id'] ?? '')),
			$this->normalizeArray($payload['arguments'] ?? []),
			['source' => 'retrieval-search-admin']
		);
	}

	/** @return array<string,mixed> */
	private function normalizeArray(mixed $value): array {
		if($value instanceof \stdClass) {
			$value = (array)$value;
		}

		return is_array($value) ? $value : [];
	}
}

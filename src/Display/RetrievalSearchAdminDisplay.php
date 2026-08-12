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
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentTool;
use MissionBay\Service\AgentComponentPresetToolTestService;

/**
 * Interactive read-only workbench for configured MissionBay retrieval tools.
 *
 * The display intentionally executes materialized component presets instead of
 * addressing a vector-store implementation directly. Resource configuration,
 * mandatory filters, collection mapping, and backend selection therefore stay
 * inside the configured retrieval tool where they belong.
 */
final class RetrievalSearchAdminDisplay implements IDisplay {

	private const SEARCH_FUNCTION = 'retrieval_search';
	private const CONTEXT_FUNCTION = 'retrieval_context';

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IAgentComponentPresetRepository $presetRepository,
		private readonly IAgentComponentPresetMaterializer $materializer,
		private readonly AgentComponentPresetToolTestService $toolTestService
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
		$presets = [];

		foreach($this->presetRepository->getPresets() as $presetId => $preset) {
			$presetId = trim((string)$presetId);
			if($presetId === '') {
				continue;
			}

			try {
				[$materialization, $tool] = $this->materializeTool($presetId);
				$definitions = $this->normalizeToolDefinitions($tool->getToolDefinitions());
				$searchDefinition = $this->findFunctionDefinition($definitions, self::SEARCH_FUNCTION);

				if($searchDefinition === null) {
					continue;
				}

				$label = trim((string)($preset['label'] ?? ''));
				$description = trim((string)(
					$preset['meta']['description'] ??
					$preset['description'] ??
					''
				));

				$presets[] = [
					'id' => $presetId,
					'label' => $label !== '' ? $label : $presetId,
					'description' => $description,
					'has_context' => $this->findFunctionDefinition($definitions, self::CONTEXT_FUNCTION) !== null,
					'search_definition' => $searchDefinition,
					'warnings' => $materialization->getWarnings()
				];
			}
			catch(\Throwable $e) {
				$presets[] = [
					'id' => $presetId,
					'label' => trim((string)($preset['label'] ?? '')) ?: $presetId,
					'description' => '',
					'has_context' => false,
					'search_definition' => null,
					'warnings' => [$e->getMessage()],
					'unavailable' => true
				];
			}
		}

		$presets = array_values(array_filter(
			$presets,
			static fn(array $preset): bool => !($preset['unavailable'] ?? false) || $preset['search_definition'] !== null
		));

		usort($presets, static function(array $left, array $right): int {
			return strcasecmp((string)$left['label'], (string)$right['label']);
		});

		return [
			'ok' => true,
			'presets' => $presets,
			'search_function' => self::SEARCH_FUNCTION,
			'context_function' => self::CONTEXT_FUNCTION
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function buildSearchResponse(array $payload): array {
		$presetId = trim((string)($payload['preset_id'] ?? ''));
		if($presetId === '') {
			throw new \InvalidArgumentException('Missing preset_id.');
		}

		[$materialization, $tool, $context] = $this->materializeTool($presetId, true);
		$definitions = $this->normalizeToolDefinitions($tool->getToolDefinitions());
		$definition = $this->findFunctionDefinition($definitions, self::SEARCH_FUNCTION);

		if($definition === null) {
			throw new \RuntimeException('Selected preset does not expose ' . self::SEARCH_FUNCTION . '.');
		}

		$arguments = $this->normalizeFunctionArguments(
			$this->normalizeArray($payload['arguments'] ?? []),
			$definition
		);

		$functionName = $this->getFunctionName($definition);

		$execution = $this->toolTestService->invoke(
			$tool,
			$functionName,
			$arguments,
			$context
		);

		return [
			'ok' => (bool)($execution['ok'] ?? false),
			'preset_id' => $presetId,
			'arguments' => $arguments,
			'output' => $this->extractToolOutput($execution),
			'execution' => $execution,
			'warnings' => $materialization->getWarnings()
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function buildContextResponse(array $payload): array {
		$presetId = trim((string)($payload['preset_id'] ?? ''));
		if($presetId === '') {
			throw new \InvalidArgumentException('Missing preset_id.');
		}

		[$materialization, $tool, $context] = $this->materializeTool($presetId, true);
		$definitions = $this->normalizeToolDefinitions($tool->getToolDefinitions());
		$definition = $this->findFunctionDefinition($definitions, self::CONTEXT_FUNCTION);

		if($definition === null) {
			throw new \RuntimeException('Selected preset does not expose ' . self::CONTEXT_FUNCTION . '.');
		}

		$arguments = $this->normalizeFunctionArguments(
			$this->normalizeArray($payload['arguments'] ?? []),
			$definition
		);

		$functionName = $this->getFunctionName($definition);

		$execution = $this->toolTestService->invoke(
			$tool,
			$functionName,
			$arguments,
			$context
		);

		return [
			'ok' => (bool)($execution['ok'] ?? false),
			'preset_id' => $presetId,
			'arguments' => $arguments,
			'output' => $this->extractToolOutput($execution),
			'execution' => $execution,
			'warnings' => $materialization->getWarnings()
		];
	}

	/**
	 * @return array{0:\MissionBay\Dto\AgentComponentPresetMaterialization,1:IAgentTool,2?:\AssistantFoundation\Api\IAgentContext}
	 */
	private function materializeTool(string $presetId, bool $withContext = false): array {
		$context = $this->materializer->createContext([
			'source' => 'retrieval-search-admin',
			'component_preset_id' => $presetId
		]);
		$materialization = $this->materializer->materialize($presetId, $context);
		$tool = $materialization->getTool();

		if(!$tool instanceof IAgentTool) {
			throw new \RuntimeException('Preset does not expose an agent tool: ' . $presetId);
		}

		return $withContext
			? [$materialization, $tool, $context]
			: [$materialization, $tool];
	}

	/** @param array<int,array<string,mixed>> $definitions @return array<string,mixed>|null */
	private function findFunctionDefinition(array $definitions, string $name): ?array {
		foreach($definitions as $definition) {
			$functionName = $this->getFunctionName($definition);

			if($functionName === $name || str_ends_with($functionName, '__' . $name)) {
				return $definition;
			}
		}

		return null;
	}

	/** @param array<string,mixed> $definition */
	private function getFunctionName(array $definition): string {
		$function = is_array($definition['function'] ?? null)
			? $definition['function']
			: $definition;

		return trim((string)($function['name'] ?? ''));
	}

	/** @return array<int,array<string,mixed>> */
	private function normalizeToolDefinitions(mixed $definitions): array {
		$result = [];

		foreach(is_array($definitions) ? $definitions : [] as $definition) {
			if(is_array($definition)) {
				$result[] = $definition;
			}
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @param array<string,mixed> $definition
	 * @return array<string,mixed>
	 */
	private function normalizeFunctionArguments(array $arguments, array $definition): array {
		$function = is_array($definition['function'] ?? null)
			? $definition['function']
			: $definition;
		$parameters = is_array($function['parameters'] ?? null) ? $function['parameters'] : [];
		$properties = is_array($parameters['properties'] ?? null) ? $parameters['properties'] : [];

		if($properties === []) {
			return $arguments;
		}

		$result = [];
		foreach($properties as $name => $_schema) {
			$name = (string)$name;
			if($name !== '' && array_key_exists($name, $arguments)) {
				$result[$name] = $arguments[$name];
			}
		}

		return $result;
	}

	/** @return array<string,mixed> */
	private function normalizeArray(mixed $value): array {
		if($value instanceof \stdClass) {
			$value = (array)$value;
		}

		return is_array($value) ? $value : [];
	}

	private function extractToolOutput(array $execution): mixed {
		$toolResult = is_array($execution['tool_result'] ?? null) ? $execution['tool_result'] : [];
		return array_key_exists('output', $toolResult) ? $toolResult['output'] : null;
	}
}

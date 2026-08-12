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

namespace MissionBay\Service;

use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentTool;
use MissionBay\Api\IRetrievalSearchService;

/**
 * Shared execution boundary for configured retrieval tool presets.
 *
 * UIs can expose different subsets of retrieval_search without copying preset
 * materialization, function discovery, argument normalization, or execution.
 */
final class RetrievalSearchService implements IRetrievalSearchService {

	private const SEARCH_FUNCTION = 'retrieval_search';
	private const CONTEXT_FUNCTION = 'retrieval_context';

	public function __construct(
		private readonly IAgentComponentPresetRepository $presetRepository,
		private readonly IAgentComponentPresetMaterializer $materializer,
		private readonly AgentComponentPresetToolTestService $toolTestService
	) {}

	public function getSearchPresets(array $contextMetadata = []): array {
		$presets = [];

		foreach($this->presetRepository->getPresets() as $presetId => $preset) {
			$presetId = trim((string)$presetId);
			if($presetId === '') {
				continue;
			}

			try {
				[$materialization, $tool] = $this->materializeTool($presetId, $contextMetadata);
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
			catch(\Throwable) {
				continue;
			}
		}

		usort($presets, static function(array $left, array $right): int {
			return strcasecmp((string)$left['label'], (string)$right['label']);
		});

		return $presets;
	}

	public function search(string $presetId, array $arguments, array $contextMetadata = []): array {
		return $this->invoke($presetId, self::SEARCH_FUNCTION, $arguments, $contextMetadata);
	}

	public function context(string $presetId, array $arguments, array $contextMetadata = []): array {
		return $this->invoke($presetId, self::CONTEXT_FUNCTION, $arguments, $contextMetadata);
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @param array<string,mixed> $contextMetadata
	 * @return array<string,mixed>
	 */
	private function invoke(
		string $presetId,
		string $function,
		array $arguments,
		array $contextMetadata
	): array {
		$presetId = trim($presetId);
		if($presetId === '') {
			throw new \InvalidArgumentException('Missing preset_id.');
		}

		[$materialization, $tool, $context] = $this->materializeTool($presetId, $contextMetadata, true);
		$definitions = $this->normalizeToolDefinitions($tool->getToolDefinitions());
		$definition = $this->findFunctionDefinition($definitions, $function);

		if($definition === null) {
			throw new \RuntimeException('Selected preset does not expose ' . $function . '.');
		}

		$arguments = $this->normalizeFunctionArguments($arguments, $definition);
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
	 * @param array<string,mixed> $contextMetadata
	 * @return array{0:\MissionBay\Dto\AgentComponentPresetMaterialization,1:IAgentTool,2?:\AssistantFoundation\Api\IAgentContext}
	 */
	private function materializeTool(string $presetId, array $contextMetadata = [], bool $withContext = false): array {
		$contextMetadata['source'] = trim((string)($contextMetadata['source'] ?? 'retrieval-search')) ?: 'retrieval-search';
		$contextMetadata['component_preset_id'] = $presetId;

		$context = $this->materializer->createContext($contextMetadata);
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

	private function extractToolOutput(array $execution): mixed {
		$toolResult = is_array($execution['tool_result'] ?? null) ? $execution['tool_result'] : [];
		return array_key_exists('output', $toolResult) ? $toolResult['output'] : null;
	}
}

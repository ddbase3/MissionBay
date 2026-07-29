<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentTextTaskRuntimeService;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentTextTaskRequest;
use AssistantFoundation\Dto\AgentTextTaskResult;
use MissionBay\Api\IAgentAssistantContextContributionService;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentToolProfileResolver;

/**
 * Isolated MissionBay text tasks using the configured chat model.
 *
 * Conversation memory is never materialized and tools are never executed.
 */
final class AgentTextTaskService implements IAgentTextTaskRuntimeService {

	private const DEFAULT_ASSISTANT_NODE_ID = 'assistant';
	private const ASSISTANT_NODE_TYPE = 'aiassistantnode';

	public function __construct(
		private readonly IAgentFlowCompiler $flowCompiler,
		private readonly IAgentFlowFactory $flowFactory,
		private readonly IAgentComponentPresetMaterializer $presetMaterializer,
		private readonly AgentContextProfileResolver $contextProfileResolver,
		private readonly AgentToolProfileResolver $toolProfileResolver,
		private readonly IAgentAssistantContextContributionService $contextContributionService
	) {}

	public static function getName(): string {
		return 'missionbayagenttexttaskservice';
	}

	public static function getRuntimeId(): string {
		return 'missionbay';
	}

	public function executeTextTask(AgentTextTaskRequest $request): AgentTextTaskResult {
		$configuration = $request->getAgentConfiguration();
		$compilation = $this->flowCompiler->compile($configuration);
		$contextVars = $request->getContext();
		unset($contextVars['conversation_id']);
		$contextVars['source'] = 'agent-text-task';
		$contextVars['agent_text_task'] = $request->getTaskName();
		$context = $this->presetMaterializer->createContext($contextVars);
		$model = $this->resolveModel($compilation->getFlow(), $configuration, $context);
		$warnings = $compilation->getWarnings();
		$messages = [];

		$systemPrompt = $request->getSystemPrompt();
		if ($systemPrompt !== '') {
			$messages[] = ['role' => 'system', 'content' => $systemPrompt];
		}

		if ($request->shouldIncludeContextProfile()) {
			[$contextMessages, $contextWarnings] = $this->buildContextMessages($configuration, $context);
			$messages = array_merge($messages, $contextMessages);
			$warnings = array_merge($warnings, $contextWarnings);
		}

		if ($request->shouldIncludeToolProfile()) {
			[$catalogMessage, $toolWarnings] = $this->buildToolCatalogMessage($configuration, $context);
			if ($catalogMessage !== null) {
				$messages[] = $catalogMessage;
			}
			$warnings = array_merge($warnings, $toolWarnings);
		}

		$messages[] = ['role' => 'user', 'content' => $request->getPrompt()];
		$result = $model->complete($messages, []);
		if ($result->hasToolCalls()) {
			throw new \RuntimeException('Agent text task returned tool calls although no tools were supplied.');
		}

		return new AgentTextTaskResult(
			$result->getContent(),
			array_values(array_unique($warnings)),
			[
				'task' => $request->getTaskName(),
				'model_metadata' => $result->getMetadata()->toArray(),
				'context_profile_included' => $request->shouldIncludeContextProfile(),
				'tool_profile_included' => $request->shouldIncludeToolProfile()
			]
		);
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $configuration
	 */
	private function resolveModel(array $flow, array $configuration, IAgentContext $context): IAiChatModel {
		$assistantNodeId = trim((string)($configuration['agent_components_assistant_node'] ?? ''));
		if ($assistantNodeId === '') {
			$assistantNodeId = self::DEFAULT_ASSISTANT_NODE_ID;
		}

		$assistantNode = null;
		$fallbackNode = null;
		foreach ($flow['nodes'] ?? [] as $node) {
			if (!is_array($node)) {
				continue;
			}
			if ((string)($node['id'] ?? '') === $assistantNodeId) {
				$assistantNode = $node;
				break;
			}
			if ($fallbackNode === null && (string)($node['type'] ?? '') === self::ASSISTANT_NODE_TYPE) {
				$fallbackNode = $node;
			}
		}
		$assistantNode ??= $fallbackNode;
		if (!is_array($assistantNode)) {
			throw new \RuntimeException('Agent text task could not resolve the configured assistant node.');
		}

		$docks = is_array($assistantNode['docks'] ?? null) ? $assistantNode['docks'] : [];
		$resourceId = trim((string)($docks['chatmodel'][0] ?? ''));
		if ($resourceId === '') {
			throw new \RuntimeException('Agent text task assistant node has no configured chat-model resource.');
		}

		$resourceDefinitions = $this->collectResourceGraph($flow, $resourceId);
		$resourceFlow = $this->flowFactory->createFromArray('strictflow', [
			'nodes' => [],
			'connections' => [],
			'resources' => $resourceDefinitions
		], $context);
		$model = $resourceFlow->getResources()[$resourceId] ?? null;
		if (!$model instanceof IAiChatModel) {
			throw new \RuntimeException('Agent text task resource is not an IAiChatModel: ' . $resourceId);
		}

		return $model;
	}

	/**
	 * Collects only the configured model and its recursive resource docks.
	 *
	 * @param array<string,mixed> $flow
	 * @return array<int,array<string,mixed>>
	 */
	private function collectResourceGraph(array $flow, string $rootResourceId): array {
		$definitions = [];
		$order = [];
		foreach ($flow['resources'] ?? [] as $definition) {
			if (!is_array($definition)) {
				continue;
			}
			$id = trim((string)($definition['id'] ?? ''));
			if ($id === '') {
				continue;
			}
			if (isset($definitions[$id])) {
				throw new \RuntimeException('Agent text task found duplicate resource id: ' . $id);
			}
			$definitions[$id] = $definition;
			$order[] = $id;
		}

		$required = [];
		$visit = function(string $resourceId) use (&$visit, &$required, $definitions): void {
			if (isset($required[$resourceId])) {
				return;
			}
			$definition = $definitions[$resourceId] ?? null;
			if (!is_array($definition)) {
				throw new \RuntimeException('Agent text task resource is not defined: ' . $resourceId);
			}
			$required[$resourceId] = true;
			$docks = is_array($definition['docks'] ?? null) ? $definition['docks'] : [];
			foreach ($docks as $targetIds) {
				foreach ((array)$targetIds as $targetId) {
					$targetId = trim((string)$targetId);
					if ($targetId !== '') {
						$visit($targetId);
					}
				}
			}
		};
		$visit($rootResourceId);

		$result = [];
		foreach ($order as $resourceId) {
			if (isset($required[$resourceId])) {
				$result[] = $definitions[$resourceId];
			}
		}
		return $result;
	}

	/** @param array<string,mixed> $configuration @return array{0:array<int,array<string,mixed>>,1:array<int,string>} */
	private function buildContextMessages(array $configuration, IAgentContext $context): array {
		$profileId = $this->normalizeTechnicalId((string)($configuration['context_profile'] ?? ''));
		if ($profileId === '') {
			return [[], []];
		}

		$contributors = [];
		$warnings = [];
		foreach ($this->contextProfileResolver->resolveComponents($profileId) as $component) {
			$presetId = trim((string)($component['preset'] ?? ''));
			if ($presetId === '') {
				continue;
			}
			$materialization = $this->presetMaterializer->materialize($presetId, $context);
			$warnings = array_merge($warnings, $materialization->getWarnings());
			$contributor = $materialization->getContextContributor();
			if ($contributor instanceof IAgentContextContributor) {
				$contributors[] = $contributor;
			}
		}

		return [
			$this->contextContributionService->buildMessages($contributors, $context),
			$warnings
		];
	}

	/** @param array<string,mixed> $configuration @return array{0:?array,1:array<int,string>} */
	private function buildToolCatalogMessage(array $configuration, IAgentContext $context): array {
		$profileIds = $this->normalizeTechnicalIds($configuration['tool_profiles'] ?? []);
		if ($profileIds === []) {
			return [null, []];
		}

		$definitions = [];
		$warnings = [];
		foreach ($this->toolProfileResolver->resolveComponents($profileIds) as $component) {
			$presetId = trim((string)($component['preset'] ?? ''));
			if ($presetId === '') {
				continue;
			}
			$materialization = $this->presetMaterializer->materialize($presetId, $context);
			$warnings = array_merge($warnings, $materialization->getWarnings());
			$tool = $materialization->getTool();
			if (!$tool instanceof IAgentTool) {
				continue;
			}
			foreach ($tool->getToolDefinitions() as $definition) {
				if (!is_array($definition)) {
					continue;
				}
				$function = is_array($definition['function'] ?? null) ? $definition['function'] : [];
				$name = trim((string)($function['name'] ?? ''));
				if ($name === '') {
					throw new \RuntimeException('Agent text task found a tool definition without a function name.');
				}
				if (isset($definitions[$name])) {
					throw new \RuntimeException('Agent text task found duplicate tool capability: ' . $name);
				}
				$definitions[$name] = [
					'name' => $name,
					'description' => trim((string)($function['description'] ?? ''))
				];
			}
		}

		if ($definitions === []) {
			return [null, $warnings];
		}
		ksort($definitions);
		$content = json_encode(array_values($definitions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($content)) {
			throw new \RuntimeException('Agent text task could not encode the tool capability catalog.');
		}

		return [[
			'role' => 'system',
			'content' => "Available configured capabilities are listed below. Describe only capabilities present in this catalog. Do not call tools.\n" . $content
		], $warnings];
	}

	/** @return array<int,string> */
	private function normalizeTechnicalIds(mixed $value): array {
		if (is_string($value)) {
			$value = explode(',', $value);
		}
		if (!is_array($value)) {
			return [];
		}
		$result = [];
		foreach ($value as $id) {
			$id = $this->normalizeTechnicalId((string)$id);
			if ($id !== '') {
				$result[$id] = $id;
			}
		}
		return array_values($result);
	}

	private function normalizeTechnicalId(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}

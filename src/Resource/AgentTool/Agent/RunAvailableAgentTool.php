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

namespace MissionBay\Resource\AgentTool\Agent;

use AssistantFoundation\Api\IAgentExecutionService;
use Base3\Api\ISchemaProvider;
use Base3\Settings\Api\ISettingsStore;
use InvalidArgumentException;
use MissionBay\Api\IAgentConfigValueResolver;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Dto\AgentInstructionBlock;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\AbstractAgentResource;
use Throwable;

/**
 * RunAvailableAgentTool
 *
 * Exposes all enabled configured MissionBay agents as a small catalog and
 * execution tool. The memory output stays compact: small installations get a
 * short inline list, larger installations are discovered through paged tools.
 */
class RunAvailableAgentTool extends AbstractAgentResource implements IAgentTool, IAgentContextContributor, ISchemaProvider {

	private const DEFAULT_SETTINGS_GROUP = 'agent';
	private const DEFAULT_TOOL_PREFIX = 'agent_catalog';
	private const DEFAULT_ASSISTANT_NODE_ID = 'assistant';
	private const MAX_TEXT_LENGTH = 12000;

	protected string $settingsGroup = self::DEFAULT_SETTINGS_GROUP;
	protected string $toolPrefix = self::DEFAULT_TOOL_PREFIX;
	protected string $listToolName = 'agent_catalog_list';
	protected string $describeToolName = 'agent_catalog_describe';
	protected string $runToolName = 'agent_catalog_run';
	protected bool $allowPromptOverride = true;
	protected bool $includeRawOutput = false;
	protected bool $memoryEnabled = true;
	protected int $memoryPriority = 45;
	protected int $memoryAgentLimit = 8;
	protected int $memoryPromptPreviewLength = 140;
	protected int $listDefaultPageSize = 10;
	protected int $listMaxPageSize = 30;
	protected int $listPromptPreviewLength = 240;
	protected int $describePromptMaxLength = 4000;

	public function __construct(
		private readonly IAgentConfigValueResolver $resolver,
		private readonly ISettingsStore $settingsStore,
		private readonly IAgentExecutionService $agentExecutionService,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'runavailableagenttool';
	}

	public function getDescription(): string {
		return 'Lets an agent discover and run any enabled configured MissionBay agent.';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'settings_group' => [
					'type' => 'string',
					'description' => 'SettingsStore group containing configured agents.',
					'default' => self::DEFAULT_SETTINGS_GROUP
				],
				'tool_prefix' => [
					'type' => 'string',
					'description' => 'Prefix for generated function names. Use a unique prefix when multiple catalog tools are attached.',
					'default' => self::DEFAULT_TOOL_PREFIX
				],
				'allow_prompt_override' => [
					'type' => 'boolean',
					'description' => 'If true, the calling agent may provide user_prompt to delegate a custom sub-task.',
					'default' => true
				],
				'include_raw_output' => [
					'type' => 'boolean',
					'description' => 'If true, run results include raw terminal flow output for debugging.',
					'default' => false
				],
				'memory_enabled' => [
					'type' => 'boolean',
					'description' => 'If true, inject compact system guidance for this catalog tool.',
					'default' => true
				],
				'memory_priority' => [
					'type' => 'integer',
					'description' => 'Memory priority. Lower values are loaded first.',
					'default' => 45
				],
				'memory_agent_limit' => [
					'type' => 'integer',
					'description' => 'Maximum number of enabled agents listed inline in memory. Above this, only catalog usage guidance is injected.',
					'default' => 8
				],
				'memory_prompt_preview_length' => [
					'type' => 'integer',
					'description' => 'Maximum prompt preview characters per inline memory agent.',
					'default' => 140
				],
				'list_default_page_size' => [
					'type' => 'integer',
					'description' => 'Default page size for the list tool.',
					'default' => 10
				],
				'list_max_page_size' => [
					'type' => 'integer',
					'description' => 'Maximum page size accepted by the list tool.',
					'default' => 30
				],
				'list_prompt_preview_length' => [
					'type' => 'integer',
					'description' => 'Maximum prompt preview characters in list detail=preview results.',
					'default' => 240
				],
				'describe_prompt_max_length' => [
					'type' => 'integer',
					'description' => 'Maximum default prompt characters returned by the describe tool.',
					'default' => 4000
				]
			],
			'required' => []
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->settingsGroup = $this->normalizeTechnicalKey($this->readConfigString($config, 'settings_group', self::DEFAULT_SETTINGS_GROUP));
		$this->toolPrefix = $this->normalizeToolName($this->readConfigString($config, 'tool_prefix', self::DEFAULT_TOOL_PREFIX));
		$this->allowPromptOverride = $this->readConfigBool($config, 'allow_prompt_override', true);
		$this->includeRawOutput = $this->readConfigBool($config, 'include_raw_output', false);
		$this->memoryEnabled = $this->readConfigBool($config, 'memory_enabled', true);
		$this->memoryPriority = $this->clampInt($this->readConfigInt($config, 'memory_priority', 45), 0, 1000);
		$this->memoryAgentLimit = $this->clampInt($this->readConfigInt($config, 'memory_agent_limit', 8), 0, 50);
		$this->memoryPromptPreviewLength = $this->clampInt($this->readConfigInt($config, 'memory_prompt_preview_length', 140), 40, 1000);
		$this->listDefaultPageSize = $this->clampInt($this->readConfigInt($config, 'list_default_page_size', 10), 1, 100);
		$this->listMaxPageSize = $this->clampInt($this->readConfigInt($config, 'list_max_page_size', 30), 1, 100);
		$this->listPromptPreviewLength = $this->clampInt($this->readConfigInt($config, 'list_prompt_preview_length', 240), 40, 2000);
		$this->describePromptMaxLength = $this->clampInt($this->readConfigInt($config, 'describe_prompt_max_length', 4000), 200, 20000);

		if($this->settingsGroup === '') {
			$this->settingsGroup = self::DEFAULT_SETTINGS_GROUP;
		}

		if($this->toolPrefix === '') {
			$this->toolPrefix = self::DEFAULT_TOOL_PREFIX;
		}

		if($this->listDefaultPageSize > $this->listMaxPageSize) {
			$this->listDefaultPageSize = $this->listMaxPageSize;
		}

		$this->listToolName = $this->toolPrefix . '_list';
		$this->describeToolName = $this->toolPrefix . '_describe';
		$this->runToolName = $this->toolPrefix . '_run';
	}

	// ----------------------------------------------------
	// Context contribution
	// ----------------------------------------------------

	public function contribute(IAgentContext $context): iterable {
		if (!$this->memoryEnabled) {
			return [];
		}

		$agents = $this->loadEnabledAgentRows();
		$count = count($agents);
		$content = 'Enabled agent catalog tool available. Use "' . $this->listToolName . '" to search agents, "' . $this->describeToolName . '" to inspect one, and "' . $this->runToolName . '" to run one by agent_id.';

		if ($count === 0) {
			$content .= ' No enabled configured agents are currently available.';
		}
		else if ($count <= $this->memoryAgentLimit) {
			$content .= "\nEnabled agents:";
			foreach ($agents as $agent) {
				$content .= "\n- " . $agent['id'] . ': ' . $this->formatInlineAgentLabel($agent, $this->memoryPromptPreviewLength);
			}
		}
		else {
			$content .= ' There are ' . $count . ' enabled agents. Use the list tool before choosing one.';
		}

		return [new AgentInstructionBlock(
			id: 'available-agent-catalog',
			content: $content,
			source: $this->id(),
			metadata: ['implementation' => static::getName()]
		)];
	}

	public function getPriority(): int {
		return $this->memoryPriority;
	}

	// ----------------------------------------------------
	// IAgentTool
	// ----------------------------------------------------

	public function getToolDefinitions(): array {
		return [
			[
				'type' => 'function',
				'label' => 'List Agents',
				'category' => 'agent',
				'tags' => ['agent', 'catalog', 'sub-agent', 'discovery'],
				'priority' => 30,
				'function' => [
					'name' => $this->listToolName,
					'description' => 'Searches and pages enabled configured agents. Use this before running a sub-agent when the best agent is not obvious.',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'search' => [
								'type' => 'string',
								'description' => 'Optional search text matched against agent id, label, prompt preview, LLM and policy.'
							],
							'page' => [
								'type' => 'integer',
								'description' => 'Page number starting at 1.'
							],
							'page_size' => [
								'type' => 'integer',
								'description' => 'Number of agents to return. The resource clamps this to the configured maximum.'
							],
							'detail' => [
								'type' => 'string',
								'description' => 'Result detail level. Use compact for IDs and labels, preview when prompt snippets are needed.',
								'enum' => ['compact', 'preview']
							]
						],
						'required' => []
					]
				]
			],
			[
				'type' => 'function',
				'label' => 'Describe Agent',
				'category' => 'agent',
				'tags' => ['agent', 'catalog', 'sub-agent', 'metadata'],
				'priority' => 31,
				'function' => [
					'name' => $this->describeToolName,
					'description' => 'Returns details for one enabled configured agent. Use this after list results when you need to confirm what an agent does before running it.',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'agent_id' => [
								'type' => 'string',
								'description' => 'Configured agent id from the catalog.'
							],
							'include_default_prompt' => [
								'type' => 'boolean',
								'description' => 'If true, include the selected agent default user prompt, shortened by configuration.'
							]
						],
						'required' => ['agent_id']
					]
				]
			],
			[
				'type' => 'function',
				'label' => 'Run Agent',
				'category' => 'agent',
				'tags' => ['agent', 'sub-agent', 'delegation'],
				'priority' => 32,
				'function' => [
					'name' => $this->runToolName,
					'description' => 'Runs one enabled configured agent by agent_id. Provide user_prompt to let the sub-agent solve a specific delegated task, or omit user_prompt to run its configured default prompt.',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'agent_id' => [
								'type' => 'string',
								'description' => 'Configured agent id from the catalog.'
							],
							'user_prompt' => [
								'type' => 'string',
								'description' => 'Optional custom prompt for the selected sub-agent. Use this for concrete delegated sub-tasks. If omitted or empty, the configured prompt of that agent is used.'
							],
							'context' => [
								'type' => 'object',
								'description' => 'Optional simple context values for the sub-agent. Only scalar values and nested arrays/objects are preserved.',
								'additionalProperties' => true
							]
						],
						'required' => ['agent_id']
					]
				]
			]
		];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): array {
		return match($name) {
			$this->listToolName => $this->toolListAgents($arguments),
			$this->describeToolName => $this->toolDescribeAgent($arguments),
			$this->runToolName => $this->toolRunAgent($arguments),
			default => throw new InvalidArgumentException('Unsupported tool: ' . $name)
		};
	}

	// ----------------------------------------------------
	// Catalog tools
	// ----------------------------------------------------

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	private function toolListAgents(array $arguments): array {
		$search = trim((string)($arguments['search'] ?? ''));
		$page = $this->readArgumentInt($arguments, 'page', 1);
		$pageSize = $this->readArgumentInt($arguments, 'page_size', $this->listDefaultPageSize);
		$page = max(1, $page);
		$pageSize = $this->clampInt($pageSize, 1, $this->listMaxPageSize);
		$detail = $this->normalizeListDetail((string)($arguments['detail'] ?? 'compact'));

		$agents = $this->loadEnabledAgentRows();
		$agents = $this->filterAgents($agents, $search);

		$total = count($agents);
		$totalPages = $pageSize > 0 ? (int)ceil($total / $pageSize) : 0;
		$offset = max(0, ($page - 1) * $pageSize);
		$pageRows = array_slice($agents, $offset, $pageSize);
		$rows = [];

		foreach($pageRows as $agent) {
			$rows[] = $this->formatAgentListRow($agent, $detail);
		}

		return [
			'ok' => true,
			'mode' => 'list',
			'settings_group' => $this->settingsGroup,
			'search' => $search,
			'detail' => $detail,
			'page' => $page,
			'page_size' => $pageSize,
			'total' => $total,
			'total_pages' => $totalPages,
			'has_more' => ($offset + $pageSize) < $total,
			'next_page' => ($offset + $pageSize) < $total ? $page + 1 : null,
			'agents' => $rows
		];
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	private function toolDescribeAgent(array $arguments): array {
		$agentId = $this->normalizeTechnicalKey((string)($arguments['agent_id'] ?? ''));

		if($agentId === '') {
			return $this->errorResult('Missing required parameter: agent_id.', 'missing_agent_id', '');
		}

		try {
			$settings = $this->loadEnabledAgentSettings($agentId);
		}
		catch(Throwable $e) {
			return $this->errorResult($e->getMessage(), 'agent_unavailable', $agentId);
		}

		$row = $this->buildAgentRow($agentId, $settings);
		$includeDefaultPrompt = $this->readArgumentBool($arguments, 'include_default_prompt', true);
		$result = [
			'ok' => true,
			'mode' => 'describe',
			'agent' => $this->formatAgentDescribeRow($row)
		];

		if($includeDefaultPrompt) {
			$result['agent']['default_user_prompt'] = $this->shortenText((string)$row['user_prompt'], $this->describePromptMaxLength);
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	private function toolRunAgent(array $arguments): array {
		$agentId = $this->normalizeTechnicalKey((string)($arguments['agent_id'] ?? ''));

		if($agentId === '') {
			return $this->errorResult('Missing required parameter: agent_id.', 'missing_agent_id', '');
		}

		try {
			$settings = $this->loadEnabledAgentSettings($agentId);
			$promptResult = $this->resolveUserPrompt($settings, $arguments);
			$prompt = $promptResult['prompt'];

			if($prompt === '') {
				return $this->errorResult('No user prompt was provided and the selected agent has no default prompt.', 'missing_prompt', $agentId);
			}

			$result = $this->agentExecutionService->run(
				$settings,
				$this->buildAgentInputs($settings, $prompt),
				$this->buildAgentContextVars($agentId, $settings, $arguments, $prompt, $promptResult['source'])
			);

			$output = $result->getOutput();
			$assistantNodeId = $this->getAssistantNodeId($settings);
			$message = $this->extractAssistantMessage($output, $assistantNodeId);

			if($message === null) {
				$error = $this->extractFlowError($output, $assistantNodeId);

				if($error !== '') {
					return $this->flowErrorResult($agentId, $error, $output);
				}

				return $this->flowErrorResult($agentId, 'Sub-agent finished, but no assistant message was returned. ' . $this->describeFlowOutput($output), $output);
			}

			return $this->successResult($agentId, $settings, $prompt, $promptResult['source'], $message, $output);
		}
		catch(Throwable $e) {
			return $this->errorResult('Sub-agent execution failed: ' . $e->getMessage(), 'execution_failed', $agentId);
		}
	}

	// ----------------------------------------------------
	// Agent loading
	// ----------------------------------------------------

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function loadEnabledAgentRows(): array {
		try {
			$group = $this->settingsStore->getGroup($this->settingsGroup);
		}
		catch(Throwable) {
			return [];
		}

		if(!is_array($group)) {
			return [];
		}

		$rows = [];

		foreach($group as $id => $settings) {
			if(!is_string($id) && !is_int($id)) {
				continue;
			}

			$id = $this->normalizeTechnicalKey((string)$id);

			if($id === '' || !is_array($settings) || !$this->isEnabled($settings)) {
				continue;
			}

			$rows[] = $this->buildAgentRow($id, $settings);
		}

		usort($rows, function(array $left, array $right): int {
			$result = strcmp($this->toLower((string)$left['label']), $this->toLower((string)$right['label']));

			if($result === 0) {
				$result = strcmp($this->toLower((string)$left['id']), $this->toLower((string)$right['id']));
			}

			return $result;
		});

		return $rows;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function loadEnabledAgentSettings(string $agentId): array {
		$settings = $this->settingsStore->get($this->settingsGroup, $agentId, []);

		if(!is_array($settings) || $settings === []) {
			throw new \RuntimeException('Configured agent not found: ' . $this->settingsGroup . '/' . $agentId);
		}

		if(!$this->isEnabled($settings)) {
			throw new \RuntimeException('Configured agent is disabled: ' . $this->settingsGroup . '/' . $agentId);
		}

		return $settings;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function buildAgentRow(string $id, array $settings): array {
		$policy = is_array($settings['policy'] ?? null) ? $settings['policy'] : [];
		$policyData = is_array($policy['data'] ?? null) ? $policy['data'] : [];
		$components = is_array($settings['agent_components'] ?? null) ? $settings['agent_components'] : [];
		$label = $this->normalizeLabel((string)($settings['label'] ?? ''));
		$userPrompt = $this->normalizeTextBlock((string)($settings['user_prompt'] ?? ''));

		return [
			'id' => $id,
			'label' => $label !== '' ? $label : $id,
			'user_prompt' => $userPrompt,
			'has_default_prompt' => trim($userPrompt) !== '',
			'llm' => trim((string)($settings['llm'] ?? '')),
			'policy' => $this->normalizeTechnicalKey((string)($policy['policy'] ?? '')),
			'policy_data_text' => $this->formatPolicyDataText($policyData),
			'component_count' => count($components)
		];
	}

	/**
	 * @param array<int,array<string,mixed>> $agents
	 * @return array<int,array<string,mixed>>
	 */
	private function filterAgents(array $agents, string $search): array {
		$search = trim($search);

		if($search === '') {
			return $agents;
		}

		$needle = $this->toLower($search);
		$result = [];

		foreach($agents as $agent) {
			$haystack = implode("\n", [
				(string)($agent['id'] ?? ''),
				(string)($agent['label'] ?? ''),
				(string)($agent['user_prompt'] ?? ''),
				(string)($agent['llm'] ?? ''),
				(string)($agent['policy'] ?? ''),
				(string)($agent['policy_data_text'] ?? '')
			]);

			if(str_contains($this->toLower($haystack), $needle)) {
				$result[] = $agent;
			}
		}

		return $result;
	}

	// ----------------------------------------------------
	// Execution helpers
	// ----------------------------------------------------

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> $arguments
	 * @return array{prompt:string,source:string}
	 */
	private function resolveUserPrompt(array $settings, array $arguments): array {
		if($this->allowPromptOverride) {
			$argumentPrompt = $this->normalizeTextBlock((string)($arguments['user_prompt'] ?? ''));

			if(trim($argumentPrompt) !== '') {
				return [
					'prompt' => $argumentPrompt,
					'source' => 'argument'
				];
			}
		}

		return [
			'prompt' => $this->normalizeTextBlock((string)($settings['user_prompt'] ?? '')),
			'source' => 'configured_agent'
		];
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function buildAgentInputs(array $settings, string $prompt): array {
		return [
			'system' => $this->normalizeTextBlock((string)($settings['system_prompt'] ?? '')),
			'prompt' => $prompt,
			'mode' => 'sub_agent_catalog'
		];
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	private function buildAgentContextVars(string $agentId, array $settings, array $arguments, string $prompt, string $promptSource): array {
		return [
			'sub_agent_id' => $agentId,
			'sub_agent_label' => trim((string)($settings['label'] ?? '')),
			'sub_agent_config' => $settings,
			'sub_agent_tool_id' => $this->getId(),
			'sub_agent_tool_name' => $this->runToolName,
			'sub_agent_tool_catalog' => true,
			'sub_agent_prompt' => $prompt,
			'sub_agent_prompt_source' => $promptSource,
			'sub_agent_call_context' => $this->normalizeContextPayload($arguments['context'] ?? [])
		];
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function getAssistantNodeId(array $settings): string {
		$nodeId = trim((string)($settings['agent_components_assistant_node'] ?? self::DEFAULT_ASSISTANT_NODE_ID));

		return $nodeId !== '' ? $nodeId : self::DEFAULT_ASSISTANT_NODE_ID;
	}

	/**
	 * @param array<string,mixed> $output
	 * @return ?array<string,mixed>
	 */
	private function extractAssistantMessage(array $output, string $assistantNodeId): ?array {
		if(isset($output[$assistantNodeId]['message']) && is_array($output[$assistantNodeId]['message'])) {
			return $output[$assistantNodeId]['message'];
		}

		if(isset($output['assistant']['message']) && is_array($output['assistant']['message'])) {
			return $output['assistant']['message'];
		}

		foreach($output as $nodeOutput) {
			if(is_array($nodeOutput) && isset($nodeOutput['message']) && is_array($nodeOutput['message'])) {
				return $nodeOutput['message'];
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $output
	 */
	private function extractFlowError(array $output, string $assistantNodeId): string {
		if(isset($output[$assistantNodeId]['error']) && is_scalar($output[$assistantNodeId]['error'])) {
			return trim((string)$output[$assistantNodeId]['error']);
		}

		if(isset($output['assistant']['error']) && is_scalar($output['assistant']['error'])) {
			return trim((string)$output['assistant']['error']);
		}

		foreach($output as $nodeOutput) {
			if(is_array($nodeOutput) && isset($nodeOutput['error']) && is_scalar($nodeOutput['error'])) {
				return trim((string)$nodeOutput['error']);
			}
		}

		return '';
	}

	/**
	 * @param array<string,mixed> $output
	 */
	private function describeFlowOutput(array $output): string {
		$nodeIds = array_keys($output);
		$nodeIds = array_map('strval', $nodeIds);
		$nodeIds = array_values(array_filter($nodeIds, static fn(string $id): bool => $id !== ''));

		if($nodeIds === []) {
			return 'No terminal node output was returned.';
		}

		return 'Terminal nodes: ' . implode(', ', $nodeIds) . '.';
	}

	// ----------------------------------------------------
	// Result formatting
	// ----------------------------------------------------

	/**
	 * @param array<string,mixed> $agent
	 * @return array<string,mixed>
	 */
	private function formatAgentListRow(array $agent, string $detail): array {
		$row = [
			'agent_id' => (string)$agent['id'],
			'label' => (string)$agent['label'],
			'has_default_prompt' => (bool)$agent['has_default_prompt'],
			'llm' => (string)$agent['llm'],
			'component_count' => (int)$agent['component_count']
		];

		if($detail === 'preview') {
			$row['default_prompt_preview'] = $this->shortenText((string)$agent['user_prompt'], $this->listPromptPreviewLength);
			$row['policy'] = (string)$agent['policy'];
			$row['policy_data'] = (string)$agent['policy_data_text'];
		}

		return $row;
	}

	/**
	 * @param array<string,mixed> $agent
	 * @return array<string,mixed>
	 */
	private function formatAgentDescribeRow(array $agent): array {
		return [
			'agent_id' => (string)$agent['id'],
			'label' => (string)$agent['label'],
			'has_default_prompt' => (bool)$agent['has_default_prompt'],
			'llm' => (string)$agent['llm'],
			'component_count' => (int)$agent['component_count'],
			'policy' => (string)$agent['policy'],
			'policy_data' => (string)$agent['policy_data_text']
		];
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> $message
	 * @param array<string,mixed> $output
	 * @return array<string,mixed>
	 */
	private function successResult(string $agentId, array $settings, string $prompt, string $promptSource, array $message, array $output): array {
		$result = [
			'ok' => true,
			'mode' => 'run',
			'agent_id' => $agentId,
			'agent_label' => trim((string)($settings['label'] ?? '')),
			'prompt_source' => $promptSource,
			'prompt' => $this->shortenText($prompt, self::MAX_TEXT_LENGTH),
			'message' => $this->normalizeMessageContent($message['content'] ?? ''),
			'message_id' => $this->normalizeMessageId($message['id'] ?? null)
		];

		if($this->includeRawOutput) {
			$result['raw_output'] = $output;
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $output
	 * @return array<string,mixed>
	 */
	private function flowErrorResult(string $agentId, string $message, array $output): array {
		$result = $this->errorResult($message, 'flow_output_missing_message', $agentId);
		$result['terminal_nodes'] = array_values(array_map('strval', array_keys($output)));

		if($this->includeRawOutput) {
			$result['raw_output'] = $output;
		}

		return $result;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function errorResult(string $message, string $errorCode, string $agentId): array {
		return [
			'ok' => false,
			'error_code' => $errorCode,
			'error' => $message,
			'agent_id' => $agentId,
			'list_tool' => $this->listToolName,
			'describe_tool' => $this->describeToolName,
			'run_tool' => $this->runToolName
		];
	}

	/**
	 * @param array<string,mixed> $agent
	 */
	private function formatInlineAgentLabel(array $agent, int $previewLength): string {
		$label = (string)$agent['label'];
		$preview = $this->shortenText((string)$agent['user_prompt'], $previewLength);

		if($preview === '') {
			return $label . ' (no default prompt)';
		}

		return $label . ' - ' . $preview;
	}

	/**
	 * @param array<string,mixed> $policyData
	 */
	private function formatPolicyDataText(array $policyData): string {
		if($policyData === []) {
			return '-';
		}

		$parts = [];

		foreach($policyData as $key => $value) {
			if(is_scalar($value) || $value === null) {
				$parts[] = (string)$key . ': ' . (string)$value;
				continue;
			}

			$parts[] = (string)$key . ': ' . $this->shortenText($this->encodeJson($value), 60);
		}

		return implode(', ', $parts);
	}

	// ----------------------------------------------------
	// Config and arguments
	// ----------------------------------------------------

	/**
	 * @param array<string,mixed> $config
	 */
	private function readConfigString(array $config, string $key, string $default): string {
		if(!array_key_exists($key, $config)) {
			return $default;
		}

		$value = $this->resolver->resolveValue($config[$key]);

		if($value === null) {
			return $default;
		}

		if(is_scalar($value)) {
			return trim((string)$value);
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return is_string($json) ? trim($json) : $default;
	}

	/**
	 * @param array<string,mixed> $config
	 */
	private function readConfigBool(array $config, string $key, bool $default): bool {
		if(!array_key_exists($key, $config)) {
			return $default;
		}

		$value = $this->resolver->resolveValue($config[$key]);

		return $this->toBool($value, $default);
	}

	/**
	 * @param array<string,mixed> $config
	 */
	private function readConfigInt(array $config, string $key, int $default): int {
		if(!array_key_exists($key, $config)) {
			return $default;
		}

		$value = $this->resolver->resolveValue($config[$key]);

		if($value === null || $value === '' || !is_numeric($value)) {
			return $default;
		}

		return (int)$value;
	}

	/**
	 * @param array<string,mixed> $arguments
	 */
	private function readArgumentInt(array $arguments, string $key, int $default): int {
		if(!array_key_exists($key, $arguments) || $arguments[$key] === null || $arguments[$key] === '' || !is_numeric($arguments[$key])) {
			return $default;
		}

		return (int)$arguments[$key];
	}

	/**
	 * @param array<string,mixed> $arguments
	 */
	private function readArgumentBool(array $arguments, string $key, bool $default): bool {
		if(!array_key_exists($key, $arguments)) {
			return $default;
		}

		return $this->toBool($arguments[$key], $default);
	}

	// ----------------------------------------------------
	// Normalization
	// ----------------------------------------------------

	/**
	 * @param array<string,mixed> $settings
	 */
	private function isEnabled(array $settings): bool {
		if(!array_key_exists('enabled', $settings)) {
			return true;
		}

		return $this->toBool($settings['enabled'], false);
	}

	private function normalizeListDetail(string $value): string {
		$value = strtolower(trim($value));

		return in_array($value, ['compact', 'preview'], true) ? $value : 'compact';
	}

	private function normalizeToolName(string $value): string {
		$value = trim($value);
		$value = preg_replace('/[^A-Za-z0-9_]+/', '_', $value) ?? '';
		$value = trim($value, '_');

		return substr($value, 0, 48);
	}

	private function normalizeTechnicalKey(string $value): string {
		$value = strtolower(trim($value));

		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	private function normalizeTextBlock(string $value): string {
		return str_replace(["\r\n", "\r"], "\n", $value);
	}

	private function normalizeLabel(string $value): string {
		return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
	}

	private function normalizeMessageId(mixed $id): string {
		if(is_scalar($id)) {
			$id = trim((string)$id);

			if($id !== '') {
				return $id;
			}
		}

		return uniqid('msg_', true);
	}

	private function normalizeMessageContent(mixed $content): string {
		if($content === null) {
			return '';
		}

		if(is_string($content)) {
			return $content;
		}

		if(is_bool($content)) {
			return $content ? 'true' : 'false';
		}

		if(is_int($content) || is_float($content)) {
			return (string)$content;
		}

		$json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return is_string($json) && $json !== 'null' ? $json : '';
	}

	private function shortenText(string $value, int $maxLength): string {
		$value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

		if($value === '' || strlen($value) <= $maxLength) {
			return $value;
		}

		return substr($value, 0, max(0, $maxLength - 3)) . '...';
	}

	private function normalizeContextPayload(mixed $value, int $depth = 0): array {
		if($depth > 5 || !is_array($value)) {
			return [];
		}

		$result = [];

		foreach($value as $key => $item) {
			if(!is_string($key) && !is_int($key)) {
				continue;
			}

			$key = (string)$key;

			if(is_scalar($item) || $item === null) {
				$result[$key] = $item;
				continue;
			}

			if(is_array($item)) {
				$result[$key] = $this->normalizeContextPayload($item, $depth + 1);
			}
		}

		return $result;
	}

	private function toBool(mixed $value, bool $default): bool {
		if($value === null || $value === '') {
			return $default;
		}

		if(is_bool($value)) {
			return $value;
		}

		if(is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));

		if(in_array($value, ['1', 'true', 'yes', 'on'], true)) {
			return true;
		}

		if(in_array($value, ['0', 'false', 'no', 'off'], true)) {
			return false;
		}

		return $default;
	}

	private function clampInt(int $value, int $min, int $max): int {
		return max($min, min($max, $value));
	}

	private function toLower(string $value): string {
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}

	private function encodeJson(mixed $value): string {
		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return is_string($json) ? $json : '';
	}
}

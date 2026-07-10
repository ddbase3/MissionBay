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
use AssistantFoundation\Api\IAgentMemory;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\AbstractAgentResource;
use Throwable;

/**
 * RunConfiguredAgentTool
 *
 * Exposes one configured MissionBay agent as a callable tool for another agent.
 * Multiple instances can be attached with different configured agent IDs and
 * different tool names.
 */
class RunConfiguredAgentTool extends AbstractAgentResource implements IAgentTool, IAgentMemory, ISchemaProvider {

	private const SETTINGS_GROUP = 'agent';
	private const DEFAULT_TOOL_NAME = 'run_configured_agent';
	private const DEFAULT_ASSISTANT_NODE_ID = 'assistant';
	private const MAX_TEXT_LENGTH = 12000;

	protected string $agentId = '';
	protected string $toolName = self::DEFAULT_TOOL_NAME;
	protected string $toolLabel = 'Run Agent';
	protected string $toolDescription = '';
	protected string $defaultUserPrompt = '';
	protected bool $allowPromptOverride = true;
	protected bool $includeRawOutput = false;
	protected bool $memoryEnabled = true;
	protected int $memoryPriority = 40;

	public function __construct(
		private readonly IAgentConfigValueResolver $resolver,
		private readonly ISettingsStore $settingsStore,
		private readonly IAgentExecutionService $agentExecutionService,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'runconfiguredagenttool';
	}

	public function getDescription(): string {
		return 'Exposes a configured MissionBay agent as a callable sub-agent tool.';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'agent_id' => [
					'type' => 'string',
					'description' => 'SettingsStore name of the configured agent in group "agent".'
				],
				'tool_name' => [
					'type' => 'string',
					'description' => 'Function name exposed to the assistant. Use a unique name when multiple instances are attached.',
					'default' => self::DEFAULT_TOOL_NAME
				],
				'tool_label' => [
					'type' => 'string',
					'description' => 'Human-readable tool label.',
					'default' => 'Run Agent'
				],
				'tool_description' => [
					'type' => 'string',
					'description' => 'Optional custom tool description. If empty, a description is generated from the selected agent.'
				],
				'default_user_prompt' => [
					'type' => 'string',
					'description' => 'Optional prompt override used when the tool call does not provide user_prompt. If empty, the configured agent prompt is used.'
				],
				'allow_prompt_override' => [
					'type' => 'boolean',
					'description' => 'If true, the calling agent may provide user_prompt to delegate a custom sub-task.',
					'default' => true
				],
				'include_raw_output' => [
					'type' => 'boolean',
					'description' => 'If true, tool results include raw terminal flow output for debugging.',
					'default' => false
				],
				'memory_enabled' => [
					'type' => 'boolean',
					'description' => 'If true, inject a compact system note explaining this sub-agent tool.',
					'default' => true
				],
				'memory_priority' => [
					'type' => 'integer',
					'description' => 'Memory priority. Lower values are loaded first.',
					'default' => 40
				]
			],
			'required' => ['agent_id']
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->agentId = $this->normalizeTechnicalKey($this->readConfigString($config, 'agent_id', ''));
		$this->toolName = $this->normalizeToolName($this->readConfigString($config, 'tool_name', self::DEFAULT_TOOL_NAME));
		$this->toolLabel = $this->readConfigString($config, 'tool_label', 'Run Agent');
		$this->toolDescription = $this->readConfigString($config, 'tool_description', '');
		$this->defaultUserPrompt = $this->normalizeTextBlock($this->readConfigString($config, 'default_user_prompt', ''));
		$this->allowPromptOverride = $this->readConfigBool($config, 'allow_prompt_override', true);
		$this->includeRawOutput = $this->readConfigBool($config, 'include_raw_output', false);
		$this->memoryEnabled = $this->readConfigBool($config, 'memory_enabled', true);
		$this->memoryPriority = $this->readConfigInt($config, 'memory_priority', 40);

		if($this->toolName === '') {
			$this->toolName = self::DEFAULT_TOOL_NAME;
		}

		if($this->toolLabel === '') {
			$this->toolLabel = 'Run Agent';
		}
	}

	// ----------------------------------------------------
	// IAgentMemory
	// ----------------------------------------------------

	public function loadNodeHistory(string $nodeId): array {
		if(!$this->memoryEnabled || $this->agentId === '') {
			return [];
		}

		$agentLabel = $this->getConfiguredAgentLabel();
		$content = 'Sub-agent tool available: call function "' . $this->toolName . '" to delegate work to configured agent "' . $this->agentId . '"';

		if($agentLabel !== '' && $agentLabel !== $this->agentId) {
			$content .= ' (' . $agentLabel . ')';
		}

		$content .= '. Provide user_prompt when you need the sub-agent to solve a specific sub-task. Omit user_prompt to use the configured default prompt.';

		return [[
			'role' => 'system',
			'content' => $content
		]];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		// no-op (this resource only injects a static tool hint)
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		// no-op
		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		// no-op
	}

	public function getPriority(): int {
		return $this->memoryPriority;
	}

	// ----------------------------------------------------
	// IAgentTool
	// ----------------------------------------------------

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => $this->toolLabel,
			'category' => 'agent',
			'tags' => ['agent', 'sub-agent', 'delegation'],
			'priority' => 35,
			'function' => [
				'name' => $this->toolName,
				'description' => $this->buildToolDescription(),
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'user_prompt' => [
							'type' => 'string',
							'description' => 'Optional custom prompt for the sub-agent. Use this to delegate a concrete sub-task. If omitted or empty, the configured prompt is used.'
						],
						'context' => [
							'type' => 'object',
							'description' => 'Optional simple context values for the sub-agent. Only scalar values and nested arrays/objects are preserved.',
							'additionalProperties' => true
						]
					],
					'required' => []
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): array {
		if($name !== $this->toolName) {
			throw new InvalidArgumentException('Unsupported tool: ' . $name);
		}

		if($this->agentId === '') {
			return $this->errorResult('No configured agent_id for sub-agent tool.', 'missing_agent_id');
		}

		try {
			$settings = $this->loadAgentSettings();
			$prompt = $this->resolveUserPrompt($settings, $arguments);

			if($prompt === '') {
				return $this->errorResult('No user prompt was provided and the configured agent has no default prompt.', 'missing_prompt');
			}

			$result = $this->agentExecutionService->run(
				$settings,
				$this->buildAgentInputs($settings, $prompt),
				$this->buildAgentContextVars($settings, $arguments, $prompt)
			);

			$output = $result->getOutput();
			$assistantNodeId = $this->getAssistantNodeId($settings);
			$message = $this->extractAssistantMessage($output, $assistantNodeId);

			if($message === null) {
				$error = $this->extractFlowError($output, $assistantNodeId);

				if($error !== '') {
					return $this->flowErrorResult($error, $output);
				}

				return $this->flowErrorResult('Sub-agent finished, but no assistant message was returned. ' . $this->describeFlowOutput($output), $output);
			}

			return $this->successResult($settings, $prompt, $message, $output);
		}
		catch(Throwable $e) {
			return $this->errorResult('Sub-agent execution failed: ' . $e->getMessage(), 'execution_failed');
		}
	}

	// ----------------------------------------------------
	// Tool execution
	// ----------------------------------------------------

	/**
	 * @return array<string,mixed>
	 */
	private function loadAgentSettings(): array {
		$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $this->agentId, []);

		if(!is_array($settings) || $settings === []) {
			throw new \RuntimeException('Configured agent not found: ' . self::SETTINGS_GROUP . '/' . $this->agentId);
		}

		return $settings;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> $arguments
	 */
	private function resolveUserPrompt(array $settings, array $arguments): string {
		if($this->allowPromptOverride) {
			$argumentPrompt = $this->normalizeTextBlock((string)($arguments['user_prompt'] ?? ''));

			if(trim($argumentPrompt) !== '') {
				return $argumentPrompt;
			}
		}

		if(trim($this->defaultUserPrompt) !== '') {
			return $this->defaultUserPrompt;
		}

		return $this->normalizeTextBlock((string)($settings['user_prompt'] ?? ''));
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function buildAgentInputs(array $settings, string $prompt): array {
		return [
			'system' => $this->normalizeTextBlock((string)($settings['system_prompt'] ?? '')),
			'prompt' => $prompt,
			'mode' => 'sub_agent'
		];
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	private function buildAgentContextVars(array $settings, array $arguments, string $prompt): array {
		return [
			'sub_agent_id' => $this->agentId,
			'sub_agent_label' => trim((string)($settings['label'] ?? '')),
			'sub_agent_config' => $settings,
			'sub_agent_tool_id' => $this->getId(),
			'sub_agent_tool_name' => $this->toolName,
			'sub_agent_prompt' => $prompt,
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

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> $message
	 * @param array<string,mixed> $output
	 * @return array<string,mixed>
	 */
	private function successResult(array $settings, string $prompt, array $message, array $output): array {
		$result = [
			'ok' => true,
			'agent_id' => $this->agentId,
			'agent_label' => trim((string)($settings['label'] ?? '')),
			'tool_name' => $this->toolName,
			'prompt' => $this->shortenText($prompt),
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
	private function flowErrorResult(string $message, array $output): array {
		$result = $this->errorResult($message, 'flow_output_missing_message');
		$result['terminal_nodes'] = array_values(array_map('strval', array_keys($output)));

		if($this->includeRawOutput) {
			$result['raw_output'] = $output;
		}

		return $result;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function errorResult(string $message, string $errorCode): array {
		return [
			'ok' => false,
			'error_code' => $errorCode,
			'error' => $message,
			'agent_id' => $this->agentId,
			'tool_name' => $this->toolName
		];
	}

	// ----------------------------------------------------
	// Tool metadata
	// ----------------------------------------------------

	private function buildToolDescription(): string {
		if($this->toolDescription !== '') {
			return $this->toolDescription;
		}

		$description = 'Runs the configured MissionBay sub-agent "' . ($this->agentId !== '' ? $this->agentId : 'not_configured') . '".';

		if($this->allowPromptOverride) {
			$description .= ' Provide user_prompt to delegate a concrete sub-task, or omit it to use the configured prompt.';
		}
		else {
			$description .= ' The sub-agent prompt is fixed by configuration.';
		}

		return $description;
	}

	private function getConfiguredAgentLabel(): string {
		if($this->agentId === '') {
			return '';
		}

		try {
			$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $this->agentId, []);
		}
		catch(Throwable) {
			return '';
		}

		if(!is_array($settings)) {
			return '';
		}

		return trim((string)($settings['label'] ?? ''));
	}

	// ----------------------------------------------------
	// Config helpers
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

	// ----------------------------------------------------
	// Normalization
	// ----------------------------------------------------

	private function normalizeToolName(string $value): string {
		$value = trim($value);
		$value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?? '';
		$value = trim($value, '_-');

		return substr($value, 0, 64);
	}

	private function normalizeTechnicalKey(string $value): string {
		$value = strtolower(trim($value));

		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	private function normalizeTextBlock(string $value): string {
		return str_replace(["\r\n", "\r"], "\n", $value);
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

	private function shortenText(string $value): string {
		$value = trim($value);

		if(strlen($value) <= self::MAX_TEXT_LENGTH) {
			return $value;
		}

		return substr($value, 0, self::MAX_TEXT_LENGTH - 3) . '...';
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
}

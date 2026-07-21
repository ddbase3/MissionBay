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

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionReview;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use Base3\Api\IOutputSchemaProvider;
use Base3\Event\Api\IEventManager;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
use MissionBay\Api\IConfirmableAgentTool;
use MissionBay\Audit\AgentToolAuditContext;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;

/**
 * ConfiguredAgentToolResource
 *
 * Wraps one docked IAgentTool and exposes a configured tool surface.
 * This allows per-agent naming, labels and metadata without changing the
 * underlying tool implementation.
 *
 * The wrapper is also the canonical execution audit boundary for configured
 * tools, independent from the orchestrator or transport that invokes it.
 */
class ConfiguredAgentToolResource extends AbstractAgentResource implements IAgentTool, IAgentMutationGuardedTool, IConfirmableAgentTool, IOutputSchemaProvider {

	private ?IAgentTool $tool = null;
	private bool $enabled = true;
	private string $namespace = '';
	private string $label = '';
	private string $description = '';
	private string $category = '';
	private array $tags = [];
	private ?int $priority = null;

	/**
	 * @var array<string,string> Effective tool name => original tool name
	 */
	private array $nameMap = [];

	/**
	 * @var array<string,string> Effective tool name => effective label
	 */
	private array $labelMap = [];

	public function __construct(
		private readonly IAgentConfigValueResolver $resolver,
		private readonly IEventManager $eventManager,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'configuredagenttoolresource';
	}

	public function getDescription(): string {
		return 'Wraps one agent tool and exposes configured tool metadata, including optional namespacing.';
	}

	/**
	 * @return AgentNodeDock[]
	 */
	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'tool',
				description: 'Tool that should be exposed through this configured wrapper.',
				interface: IAgentTool::class,
				maxConnections: 1,
				required: true
			)
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->enabled = $this->toBool($this->resolver->resolveValue($config['enabled'] ?? null), true);
		$this->namespace = $this->normalizeNamespace((string)($this->resolver->resolveValue($config['namespace'] ?? null) ?? ''));
		$this->label = trim((string)($this->resolver->resolveValue($config['label'] ?? null) ?? ''));
		$this->description = trim((string)($this->resolver->resolveValue($config['description'] ?? null) ?? ''));
		$this->category = trim((string)($this->resolver->resolveValue($config['category'] ?? null) ?? ''));
		$this->tags = $this->normalizeStringArray($this->resolver->resolveValue($config['tags'] ?? null));
		$this->priority = $this->resolveNullableInt($config['priority'] ?? null);
	}

	public function init(array $resources, IAgentContext $context): void {
		$this->tool = null;

		if (!empty($resources['tool'][0]) && $resources['tool'][0] instanceof IAgentTool) {
			$this->tool = $resources['tool'][0];
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function getToolDefinitions(): array {
		$this->nameMap = [];
		$this->labelMap = [];

		if (!$this->enabled || !$this->tool instanceof IAgentTool) {
			return [];
		}

		$definitions = [];

		foreach ($this->tool->getToolDefinitions() as $definition) {
			if (!is_array($definition)) {
				continue;
			}

			$originalName = trim((string)($definition['function']['name'] ?? ''));

			if ($originalName === '') {
				continue;
			}

			$effectiveName = $this->buildEffectiveToolName($originalName);
			$definition['function']['name'] = $effectiveName;

			if ($this->label !== '') {
				$definition['label'] = $this->label;
			}

			if ($this->description !== '') {
				$definition['function']['description'] = $this->description;
			}

			if ($this->category !== '') {
				$definition['category'] = $this->category;
			}

			if ($this->tags !== []) {
				$definition['tags'] = $this->tags;
			}

			if ($this->priority !== null) {
				$definition['priority'] = $this->priority;
			}

			$this->nameMap[$effectiveName] = $originalName;
			$this->labelMap[$effectiveName] = trim((string)($definition['label'] ?? $effectiveName));
			$definitions[] = $definition;
		}

		return $definitions;
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$tool = $this->requireTool($name);
		$originalName = $this->resolveOriginalToolName($name);
		$audit = $this->buildAuditData($name, $originalName, $context);

		$this->fireEvent(new MissionBayToolStartedEvent(
			$audit['node_id'],
			$audit['call_id'],
			$name,
			$audit['label'],
			$arguments,
			$audit['iteration'],
			'',
			$audit['call_index'],
			$audit['trace']
		));

		try {
			$result = $tool->callTool($originalName, $arguments, $context);

			$this->fireEvent(new MissionBayToolFinishedEvent(
				$audit['node_id'],
				$audit['call_id'],
				$name,
				$audit['label'],
				$arguments,
				$result,
				$audit['iteration'],
				'',
				$audit['call_index'],
				$audit['trace']
			));

			return $result;
		}
		catch (\Throwable $e) {
			$this->fireEvent(new MissionBayToolFailedEvent(
				$audit['node_id'],
				$audit['call_id'],
				$name,
				$audit['label'],
				$arguments,
				$e->getMessage(),
				get_class($e),
				$e->getCode(),
				$audit['iteration'],
				'',
				$audit['call_index'],
				$audit['trace']
			));

			throw $e;
		}
	}

	public function supportsConfirmation(): bool {
		return $this->tool instanceof IConfirmableAgentTool;
	}

	public function getConfirmationRequest(string $name, array $arguments, IAgentContext $context): ?array {
		$tool = $this->requireTool($name);

		if (!$tool instanceof IConfirmableAgentTool) {
			return null;
		}

		return $tool->getConfirmationRequest(
			$this->resolveOriginalToolName($name),
			$arguments,
			$context
		);
	}

	public function captureMutationCommitSnapshot(
		AgentAction $action,
		string $actionFingerprint,
		IAgentContext $context
	): AgentMutationCommitSnapshot {
		$tool = $this->requireGuardedTool($action->getName());

		return $tool->captureMutationCommitSnapshot(
			$this->translateAction($action),
			$actionFingerprint,
			$context
		);
	}

	public function getActionReview(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentActionReview {
		$tool = $this->requireGuardedTool($action->getName());

		return $tool->getActionReview(
			$this->translateAction($action),
			$snapshot,
			$context
		);
	}

	public function validateMutationCommit(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentMutationCommitDecision {
		$tool = $this->requireGuardedTool($action->getName());

		return $tool->validateMutationCommit(
			$this->translateAction($action),
			$snapshot,
			$context
		);
	}

	public function getOutputSchemas(): array {
		if (!$this->enabled || !$this->tool instanceof IOutputSchemaProvider) {
			return [];
		}

		if ($this->nameMap === []) {
			$this->getToolDefinitions();
		}

		$schemas = $this->tool->getOutputSchemas();
		$result = [];

		foreach ($this->nameMap as $effectiveName => $originalName) {
			if (!array_key_exists($originalName, $schemas)) {
				continue;
			}

			$result[$effectiveName] = $schemas[$originalName];
		}

		return $result;
	}

	private function requireTool(string $effectiveName): IAgentTool {
		if (!$this->enabled) {
			throw new \InvalidArgumentException('Configured tool is disabled: ' . $effectiveName);
		}

		if (!$this->tool instanceof IAgentTool) {
			throw new \InvalidArgumentException('Configured tool has no docked tool: ' . $effectiveName);
		}

		return $this->tool;
	}

	private function requireGuardedTool(string $effectiveName): IAgentMutationGuardedTool {
		$tool = $this->requireTool($effectiveName);

		if (!$tool instanceof IAgentMutationGuardedTool) {
			throw new \RuntimeException(
				'Configured mutation tool does not implement IAgentMutationGuardedTool: ' . $effectiveName
			);
		}

		return $tool;
	}

	private function resolveOriginalToolName(string $effectiveName): string {
		if (!isset($this->nameMap[$effectiveName])) {
			$this->getToolDefinitions();
		}

		$originalName = $this->nameMap[$effectiveName] ?? null;

		if ($originalName === null) {
			throw new \InvalidArgumentException('Unsupported configured tool: ' . $effectiveName);
		}

		return $originalName;
	}

	private function translateAction(AgentAction $action): AgentAction {
		return new AgentAction(
			$action->getId(),
			$action->getType(),
			$this->resolveOriginalToolName($action->getName()),
			$action->getInput(),
			$action->getMetadata()
		);
	}

	/**
	 * @return array{node_id:string,call_id:string,label:string,iteration:int,call_index:int,trace:array<string,mixed>}
	 */
	private function buildAuditData(string $effectiveName, string $originalName, IAgentContext $context): array {
		try {
			$metadata = AgentToolAuditContext::read($context);
		}
		catch (\Throwable) {
			$metadata = [];
		}

		$trace = is_array($metadata['trace'] ?? null) ? $metadata['trace'] : [];
		$source = $this->metadataString($metadata, 'source');

		if ($source === '') {
			$source = $this->readContextVar($context, 'mcp') === true
				? AgentToolAuditContext::SOURCE_MCP
				: AgentToolAuditContext::SOURCE_DIRECT;
		}

		$callId = $this->metadataString($metadata, 'call_id');
		if ($callId === '') {
			$callId = AgentToolAuditContext::generateCallId($source === AgentToolAuditContext::SOURCE_MCP ? 'mcp-call' : 'toolcall');
		}

		$nodeId = $this->metadataString($metadata, 'node_id');
		if ($nodeId === '') {
			$nodeId = $this->getId();
		}

		$label = $this->metadataString($metadata, 'label');
		if ($label === '') {
			$label = $this->labelMap[$effectiveName] ?? $effectiveName;
		}

		$trace['source'] = $source;
		$trace['resource_id'] = $this->getId();
		$trace['original_tool_name'] = $originalName;

		if ($source === AgentToolAuditContext::SOURCE_MCP) {
			$profileId = trim((string)($this->readContextVar($context, 'mcp_profile_id') ?? ''));
			$profileLabel = trim((string)($this->readContextVar($context, 'mcp_profile_label') ?? ''));

			if ($profileId !== '') {
				$trace['mcp_profile_id'] = $profileId;
				$trace['config_group'] = (string)($trace['config_group'] ?? 'mcp');
				$trace['config_name'] = (string)($trace['config_name'] ?? $profileId);
				$trace['chatbot_key'] = (string)($trace['chatbot_key'] ?? ('mcp:' . $profileId));
			}

			if ($profileLabel !== '') {
				$trace['mcp_profile_label'] = $profileLabel;
			}

			$trace['turn_id'] = (string)($trace['turn_id'] ?? $callId);
		}

		return [
			'node_id' => $nodeId,
			'call_id' => $callId,
			'label' => $label,
			'iteration' => max(0, (int)($metadata['iteration'] ?? 0)),
			'call_index' => max(0, (int)($metadata['call_index'] ?? 0)),
			'trace' => $trace
		];
	}

	private function fireEvent(object $event): void {
		try {
			$this->eventManager->fire($event);
		}
		catch (\Throwable) {
		}
	}

	/**
	 * @param array<string,mixed> $metadata
	 */
	private function metadataString(array $metadata, string $key): string {
		$value = $metadata[$key] ?? null;
		return is_scalar($value) ? trim((string)$value) : '';
	}

	private function readContextVar(IAgentContext $context, string $key): mixed {
		try {
			return $context->getVar($key);
		}
		catch (\Throwable) {
			return null;
		}
	}

	private function buildEffectiveToolName(string $originalName): string {
		if ($this->namespace === '') {
			return $originalName;
		}

		return $this->namespace . '__' . $originalName;
	}

	private function normalizeNamespace(string $namespace): string {
		$namespace = trim($namespace);

		if ($namespace === '') {
			return '';
		}

		if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $namespace)) {
			throw new \InvalidArgumentException('Configured tool namespace must match /^[A-Za-z_][A-Za-z0-9_]*$/.');
		}

		return $namespace;
	}

	private function resolveNullableInt(mixed $config): ?int {
		$value = $this->resolver->resolveValue($config);

		if ($value === null || $value === '') {
			return null;
		}

		return (int)$value;
	}

	private function normalizeStringArray(mixed $value): array {
		if ($value === null || $value === '') {
			return [];
		}

		if (is_string($value)) {
			$value = explode(',', $value);
		}

		if (!is_array($value)) {
			return [];
		}

		$result = [];

		foreach ($value as $item) {
			$item = trim((string)$item);

			if ($item === '') {
				continue;
			}

			$result[] = $item;
		}

		return array_values(array_unique($result));
	}

	private function toBool(mixed $value, bool $default): bool {
		if ($value === null || $value === '') {
			return $default;
		}

		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));

		if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
			return true;
		}

		if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
			return false;
		}

		return $default;
	}
}

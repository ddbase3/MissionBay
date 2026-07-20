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

namespace MissionBay\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAgentToolResultCache;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolCacheConfig;
use AssistantFoundation\Dto\AgentToolCacheEntry;
use AssistantFoundation\Dto\AgentToolCacheRecord;
use AssistantFoundation\Dto\AgentToolCacheRule;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentTool;
use MissionBay\Cache\AgentToolCacheKeyBuilder;

/**
 * AgentToolResultCacheStage
 *
 * Performs explicit opt-in cache lookup before tool execution and stores only
 * structurally verified successful results after execution.
 */
final class AgentToolResultCacheStage implements IAgentStage {

	public const CHECKPOINT_LOOKUP = 'lookup';
	public const CHECKPOINT_STORE = 'store';

	public function __construct(
		private readonly IAgentToolResultCache $cache,
		private readonly IEventManager $eventManager,
		private readonly AgentToolCacheKeyBuilder $keyBuilder,
		private readonly string $id = 'tool-cache-lookup',
		private readonly string $stageName = 'tool-cache-lookup',
		private readonly string $checkpoint = self::CHECKPOINT_LOOKUP
	) {
		if (!in_array($this->checkpoint, [self::CHECKPOINT_LOOKUP, self::CHECKPOINT_STORE], true)) {
			throw new \InvalidArgumentException('Unsupported tool-cache checkpoint: ' . $this->checkpoint);
		}
	}

	public static function getName(): string {
		return 'agenttoolresultcachestage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		if ($this->checkpoint === self::CHECKPOINT_STORE) {
			return 'Stores explicitly cacheable, structurally verified successful tool results with TTL and provenance.';
		}

		return 'Reuses explicitly cacheable tool results by stable tool identity, normalized arguments, scope, and TTL before execution.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		$config = $context->getVar(AgentToolLoopContextKeys::TOOL_CACHE_CONFIG);

		if (!$config instanceof AgentToolCacheConfig || !$config->isEnabled() || !$this->cache->isAvailable()) {
			return false;
		}

		if ($context->getVar(AgentToolLoopContextKeys::COMPLETED) === true) {
			return false;
		}

		if ((string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') !== '') {
			return false;
		}

		if ($this->checkpoint === self::CHECKPOINT_LOOKUP) {
			$calls = $context->getVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS);
			return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_TOOLS
				&& is_array($calls)
				&& $calls !== [];
		}

		$results = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_AFTER_TOOLS
			&& is_array($results)
			&& $results !== []
			&& $this->hasCurrentStructuralVerification($context);
	}

	public function process(IAgentContext $context): AgentStageResult {
		if ($this->checkpoint === self::CHECKPOINT_STORE) {
			return $this->store($context);
		}

		return $this->lookup($context);
	}

	private function lookup(IAgentContext $context): AgentStageResult {
		$config = $this->requireConfig($context);
		$calls = $context->getVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS);
		$tools = $context->getVar(AgentToolLoopContextKeys::TOOLS);
		$results = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$records = $context->getVar(AgentToolLoopContextKeys::TOOL_CACHE_RECORDS);
		$plans = $context->getVar(AgentToolLoopContextKeys::TOOL_CACHE_PLANS);
		$callIndexes = $context->getVar(AgentToolLoopContextKeys::TOOL_CALL_INDEXES);
		$executed = $context->getVar(AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS);
		$callIndex = (int)($context->getVar(AgentToolLoopContextKeys::CALL_INDEX) ?? 0);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);
		$eventCallback = $context->getVar(AgentToolLoopContextKeys::EVENT_CALLBACK);
		$trace = $context->getVar(AgentToolLoopContextKeys::TRACE);
		$logger = $context->getVar(AgentToolLoopContextKeys::LOGGER);

		$calls = is_array($calls) ? $calls : [];
		$tools = is_array($tools) ? $tools : [];
		$results = is_array($results) ? $results : [];
		$records = is_array($records) ? $records : [];
		$plans = is_array($plans) ? $plans : [];
		$callIndexes = is_array($callIndexes) ? $callIndexes : [];
		$executed = is_array($executed) ? $executed : [];
		$eventCallback = is_callable($eventCallback) ? $eventCallback : null;
		$trace = is_array($trace) ? $trace : [];

		$remaining = [];
		$summary = ['hits' => 0, 'misses' => 0, 'bypassed' => 0, 'errors' => 0];

		foreach ($calls as $call) {
			if (!$call instanceof AiToolCall) {
				$remaining[] = $call;
				continue;
			}

			$callId = trim($call->getId());
			if ($callId === '') {
				$callId = uniqid('toolcall_', true);
			}

			$callIndex++;
			$callIndexes[$callId] = $callIndex;
			$toolName = trim($call->getName());
			$arguments = $call->getArguments();
			$tool = $this->findTool($tools, $toolName, $logger);
			$identity = $this->resolveToolIdentity($tool);
			$resourceId = $this->resolveResourceId($tool);
			$implementationName = $this->resolveImplementationName($tool);
			$rule = $config->findRule($toolName, $resourceId, $implementationName);

			if (!$tool instanceof IAgentTool || !$rule instanceof AgentToolCacheRule) {
				$summary['bypassed']++;
				$records[] = new AgentToolCacheRecord(
					$iteration,
					$callId,
					$toolName,
					$identity,
					AgentToolCacheRecord::STATUS_BYPASS,
					reason: $tool instanceof IAgentTool ? 'no_matching_rule' : 'tool_not_found'
				);
				$remaining[] = $call;
				continue;
			}

			try {
				$scope = $this->resolveScope($config, $trace);
				$keyData = $this->keyBuilder->build(
					$config->getKeyNamespace(),
					$identity,
					$toolName,
					$arguments,
					$scope,
					$rule->getVariant()
				);
				$entry = $this->cache->get($keyData['key']);
				$plans[$callId] = [
					'cache_key' => $keyData['key'],
					'arguments_hash' => $keyData['arguments_hash'],
					'scope' => $scope,
					'ttl_seconds' => $rule->getTtlSeconds(),
					'tool_identity' => $identity,
					'tool' => $toolName,
					'rule' => $rule->toArray()
				];

				if (!$this->entryMatches($entry, $identity, $toolName, $keyData['arguments_hash'], $scope)) {
					if ($entry instanceof AgentToolCacheEntry) {
						$this->cache->delete($keyData['key']);
					}

					$summary['misses']++;
					$records[] = new AgentToolCacheRecord(
						$iteration,
						$callId,
						$toolName,
						$identity,
						AgentToolCacheRecord::STATUS_MISS,
						$keyData['key'],
						$scope,
						$rule->getTtlSeconds(),
						'cache_miss'
					);
					$remaining[] = $call;
					continue;
				}

				$summary['hits']++;
				$label = $this->resolveToolLabel($tool, $toolName, $logger);
				$cacheMetadata = [
					'hit' => true,
					'key' => $keyData['key'],
					'scope' => $scope,
					'ttl_seconds' => $rule->getTtlSeconds(),
					'created_at' => $entry->getCreatedAt(),
					'expires_at' => $entry->getExpiresAt(),
					'tool_identity' => $identity
				];
				$results[] = AgentToolResult::success(
					$callId,
					$toolName,
					$arguments,
					$entry->getOutput(),
					[
						'label' => $label,
						'iteration' => $iteration,
						'call_index' => $callIndex,
						'tool_call' => $call->getMetadata(),
						'cache' => $cacheMetadata
					]
				);
				$records[] = new AgentToolCacheRecord(
					$iteration,
					$callId,
					$toolName,
					$identity,
					AgentToolCacheRecord::STATUS_HIT,
					$keyData['key'],
					$scope,
					$rule->getTtlSeconds(),
					'cache_hit',
					$cacheMetadata
				);
				$executed[] = [
					'tool' => $toolName,
					'arguments' => $arguments,
					'result' => $entry->getOutput(),
					'cached' => true,
					'cache' => $cacheMetadata
				];
				$this->emitCachedToolEvents(
					$eventCallback,
					$callId,
					$toolName,
					$label,
					$arguments,
					$entry->getOutput(),
					$iteration,
					$callIndex,
					$trace,
					$cacheMetadata,
					$logger
				);
			} catch (\Throwable $e) {
				$summary['errors']++;
				$records[] = new AgentToolCacheRecord(
					$iteration,
					$callId,
					$toolName,
					$identity,
					AgentToolCacheRecord::STATUS_ERROR,
					reason: $e->getMessage()
				);
				$this->logError($logger, 'Tool-cache lookup failed (' . $toolName . '): ' . $e->getMessage());
				$remaining[] = $call;
			}
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => $remaining,
			AgentToolLoopContextKeys::TOOL_RESULTS => $results,
			AgentToolLoopContextKeys::TOOL_CACHE_RECORDS => $records,
			AgentToolLoopContextKeys::TOOL_CACHE_PLANS => $plans,
			AgentToolLoopContextKeys::TOOL_CALL_INDEXES => $callIndexes,
			AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS => $executed,
			AgentToolLoopContextKeys::CALL_INDEX => $callIndex,
			AgentToolLoopContextKeys::PHASE => $remaining === []
				? AgentToolLoopContextKeys::PHASE_AFTER_TOOLS
				: AgentToolLoopContextKeys::PHASE_TOOLS
		], ['tool_cache' => $summary]);
	}

	private function store(IAgentContext $context): AgentStageResult {
		$config = $this->requireConfig($context);
		$results = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$plans = $context->getVar(AgentToolLoopContextKeys::TOOL_CACHE_PLANS);
		$records = $context->getVar(AgentToolLoopContextKeys::TOOL_CACHE_RECORDS);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);
		$logger = $context->getVar(AgentToolLoopContextKeys::LOGGER);

		$results = is_array($results) ? $results : [];
		$plans = is_array($plans) ? $plans : [];
		$records = is_array($records) ? $records : [];
		$summary = ['stored' => 0, 'skipped' => 0, 'errors' => 0];

		foreach ($results as $result) {
			if (!$result instanceof AgentToolResult || !$result->isSuccess()) {
				continue;
			}

			$callId = $result->getCallId();
			$plan = $plans[$callId] ?? null;
			$cacheMeta = $result->getMetadata()['cache'] ?? null;

			if (!is_array($plan) || (is_array($cacheMeta) && ($cacheMeta['hit'] ?? false) === true)) {
				continue;
			}

			$toolName = (string)($plan['tool'] ?? $result->getToolName());
			$identity = (string)($plan['tool_identity'] ?? '');
			$key = (string)($plan['cache_key'] ?? '');
			$scope = (string)($plan['scope'] ?? '');
			$argumentsHash = (string)($plan['arguments_hash'] ?? '');
			$ttl = (int)($plan['ttl_seconds'] ?? 0);

			try {
				$size = $this->encodedSize($result->getOutput());
				if ($size > $config->getMaxEntryBytes()) {
					$summary['skipped']++;
					$records[] = new AgentToolCacheRecord(
						$iteration,
						$callId,
						$toolName,
						$identity,
						AgentToolCacheRecord::STATUS_SKIPPED,
						$key,
						$scope,
						$ttl,
						'entry_too_large',
						['entry_bytes' => $size, 'max_entry_bytes' => $config->getMaxEntryBytes()]
					);
					continue;
				}

				$created = new \DateTimeImmutable();
				$expires = $created->modify('+' . $ttl . ' seconds');
				$entry = new AgentToolCacheEntry(
					$identity,
					$toolName,
					$argumentsHash,
					$scope,
					$result->getOutput(),
					$created->format(DATE_ATOM),
					$expires->format(DATE_ATOM),
					[
						'iteration' => $iteration,
						'call_id' => $callId,
						'rule' => is_array($plan['rule'] ?? null) ? $plan['rule'] : []
					]
				);
				$this->cache->put($key, $entry, $ttl);
				$summary['stored']++;
				$records[] = new AgentToolCacheRecord(
					$iteration,
					$callId,
					$toolName,
					$identity,
					AgentToolCacheRecord::STATUS_STORED,
					$key,
					$scope,
					$ttl,
					'stored',
					['entry_bytes' => $size, 'expires_at' => $entry->getExpiresAt()]
				);
			} catch (\Throwable $e) {
				$summary['errors']++;
				$records[] = new AgentToolCacheRecord(
					$iteration,
					$callId,
					$toolName,
					$identity,
					AgentToolCacheRecord::STATUS_ERROR,
					$key,
					$scope,
					$ttl,
					$e->getMessage()
				);
				$this->logError($logger, 'Tool-cache store failed (' . $toolName . '): ' . $e->getMessage());
			}
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::TOOL_CACHE_RECORDS => $records
		], ['tool_cache' => $summary]);
	}

	private function requireConfig(IAgentContext $context): AgentToolCacheConfig {
		$config = $context->getVar(AgentToolLoopContextKeys::TOOL_CACHE_CONFIG);
		if (!$config instanceof AgentToolCacheConfig) {
			throw new \RuntimeException('Tool-cache stage requires AgentToolCacheConfig.');
		}

		return $config;
	}

	private function hasCurrentStructuralVerification(IAgentContext $context): bool {
		$verifications = $context->getVar(AgentToolLoopContextKeys::RESULT_VERIFICATIONS);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);

		if (!is_array($verifications)) {
			return false;
		}

		for ($i = count($verifications) - 1; $i >= 0; $i--) {
			$verification = $verifications[$i];
			if (!$verification instanceof AgentResultVerification) {
				continue;
			}

			if ($verification->getIteration() !== $iteration) {
				continue;
			}

			if ($verification->getVerifier() === AgentResultVerificationStage::VERIFIER) {
				return $verification->isVerified();
			}
		}

		return false;
	}

	private function resolveScope(AgentToolCacheConfig $config, array $trace): string {
		return match ($config->getScope()) {
			AgentToolCacheConfig::SCOPE_GLOBAL => 'global',
			AgentToolCacheConfig::SCOPE_CHATBOT => 'chatbot:' . (string)($trace['chatbot_key'] ?? 'unknown_chatbot'),
			AgentToolCacheConfig::SCOPE_TURN => 'turn:' . (string)($trace['turn_id'] ?? 'unknown_turn'),
			AgentToolCacheConfig::SCOPE_CUSTOM => 'custom:' . $config->getScopeKey(),
			default => 'configuration:' . (string)($trace['config_group'] ?? 'unknown_group') . ':' . (string)($trace['config_name'] ?? 'unknown_config')
		};
	}

	/**
	 * @param array<int,mixed> $tools
	 */
	private function findTool(array $tools, string $toolName, mixed $logger): ?IAgentTool {
		foreach ($tools as $tool) {
			if (!$tool instanceof IAgentTool) {
				continue;
			}

			try {
				foreach ($tool->getToolDefinitions() as $definition) {
					if (($definition['function']['name'] ?? '') === $toolName) {
						return $tool;
					}
				}
			} catch (\Throwable $e) {
				$this->logError($logger, 'Tool-cache tool lookup failed: ' . $e->getMessage());
			}
		}

		return null;
	}

	private function resolveToolIdentity(?IAgentTool $tool): string {
		if (!$tool instanceof IAgentTool) {
			return 'unknown-tool';
		}

		$implementation = $this->resolveImplementationName($tool);
		$resourceId = $this->resolveResourceId($tool);
		return $resourceId === '' ? $implementation : $implementation . ':' . $resourceId;
	}

	private function resolveImplementationName(?IAgentTool $tool): string {
		if (!$tool instanceof IAgentTool) {
			return '';
		}

		try {
			$name = trim($tool::getName());
			if ($name !== '') {
				return $name;
			}
		} catch (\Throwable $e) {
		}

		return get_class($tool);
	}

	private function resolveResourceId(?IAgentTool $tool): string {
		if ($tool instanceof IAgentResource) {
			return trim($tool->getId());
		}

		if (is_object($tool) && method_exists($tool, 'getId')) {
			try {
				return trim((string)$tool->getId());
			} catch (\Throwable $e) {
			}
		}

		return '';
	}

	private function resolveToolLabel(IAgentTool $tool, string $toolName, mixed $logger): string {
		try {
			foreach ($tool->getToolDefinitions() as $definition) {
				if (($definition['function']['name'] ?? '') === $toolName) {
					return (string)($definition['label'] ?? $toolName);
				}
			}
		} catch (\Throwable $e) {
			$this->logError($logger, 'Tool-cache label lookup failed: ' . $e->getMessage());
		}

		return $toolName;
	}

	private function entryMatches(
		?AgentToolCacheEntry $entry,
		string $identity,
		string $toolName,
		string $argumentsHash,
		string $scope
	): bool {
		return $entry instanceof AgentToolCacheEntry
			&& $entry->getToolIdentity() === $identity
			&& $entry->getToolName() === $toolName
			&& $entry->getArgumentsHash() === $argumentsHash
			&& $entry->getScope() === $scope;
	}

	private function encodedSize(mixed $value): int {
		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new \RuntimeException('Tool result is not serializable and cannot be cached.');
		}

		return strlen($json);
	}

	/**
	 * @param ?callable $eventCallback
	 * @param array<string,mixed> $trace
	 * @param array<string,mixed> $cacheMetadata
	 */
	private function emitCachedToolEvents(
		?callable $eventCallback,
		string $callId,
		string $toolName,
		string $label,
		array $arguments,
		mixed $result,
		int $iteration,
		int $callIndex,
		array $trace,
		array $cacheMetadata,
		mixed $logger
	): void {
		$payload = [
			'call_id' => $callId,
			'tool' => $toolName,
			'label' => $label,
			'args' => $arguments,
			'iteration' => $iteration,
			'call_index' => $callIndex,
			'cached' => true,
			'cache' => $cacheMetadata,
			'turn_id' => (string)($trace['turn_id'] ?? 'unknown_turn'),
			'chatbot_key' => (string)($trace['chatbot_key'] ?? 'unknown_chatbot')
		];

		if ($eventCallback !== null) {
			try {
				$eventCallback('tool.started', $payload);
				$eventCallback('tool.finished', $payload + ['result' => $result]);
			} catch (\Throwable $e) {
				$this->logError($logger, 'Cached tool UI event failed: ' . $e->getMessage());
			}
		}

	}

	private function logError(mixed $logger, string $message): void {
		if ($logger instanceof ILogger) {
			$logger->log('agenttoolresultcachestage', '[ERROR] ' . $message);
		}
	}
}

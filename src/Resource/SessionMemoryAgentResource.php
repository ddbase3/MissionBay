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
use AssistantFoundation\Api\IAgentConversationMemory;
use Base3\Api\ISchemaProvider;
use Base3\Logger\Api\ILogger;
use Base3\Session\Api\ISession;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * Session-backed visible conversation history.
 *
 * The store is encoded into small scalar chunks. This works with ISession
 * implementations that do not reliably persist nested arrays and replaces the
 * former ILIAS-only duplicate implementation. Existing array and ILIAS chunk
 * formats are read once and migrated on the next write.
 */
class SessionMemoryAgentResource extends AbstractAgentResource implements IAgentConversationMemory, ISchemaProvider {

	private const FORMAT_KEY = 'mb_memory_format';
	private const CHUNK_COUNT_KEY = 'mb_memory_chunk_count';
	private const CHUNK_KEY_PREFIX = 'mb_memory_chunk_';
	private const FORMAT = 'php-serialize-base64-v1';
	private const CHUNK_SIZE = 700;

	private const LEGACY_ARRAY_KEY = 'mb_memory';
	private const LEGACY_ILIAS_FORMAT_KEY = 'mb_memory_ilias_chunk_format';
	private const LEGACY_ILIAS_COUNT_KEY = 'mb_memory_ilias_chunk_count';
	private const LEGACY_ILIAS_CHUNK_PREFIX = 'mb_memory_ilias_chunk_';

	private ?ILogger $logger = null;
	private string $namespace = 'default';
	private int $max = 20;
	private int $priority = 80;
	private string $conversationScope = 'default';

	public function __construct(
		private readonly ISession $session,
		private readonly IAgentConfigValueResolver $resolver,
		?string $id = null
	) {
		parent::__construct($id);
		$this->ensureStarted();
	}

	public static function getName(): string {
		return 'sessionmemoryagentresource';
	}

	public function getDescription(): string {
		return 'Provides robust session-backed visible conversation history through ISession.';
	}

	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'namespace' => [
					'type' => 'string',
					'description' => 'Session memory namespace used to isolate memory buckets.',
					'default' => 'default'
				],
				'max' => [
					'type' => 'integer',
					'description' => 'Maximum number of visible chat messages stored per node. At least 2 are required for one complete user/assistant turn; 10 or more are recommended for normal chat.',
					'default' => 20,
					'minimum' => 2
				],
				'priority' => [
					'type' => 'integer',
					'description' => 'Memory priority. Lower values are loaded first.',
					'default' => 80
				]
			],
			'required' => []
		];
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger for memory events.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$namespace = trim((string)($this->resolver->resolveValue($config['namespace'] ?? null) ?? 'default'));
		$this->namespace = $namespace !== '' ? $namespace : 'default';
		$this->max = max(2, (int)($this->resolver->resolveValue($config['max'] ?? null) ?? 20));
		$this->priority = (int)($this->resolver->resolveValue($config['priority'] ?? null) ?? 80);
		$this->ensureInitialized();
	}

	public function init(array $resources, IAgentContext $context): void {
		$logger = $resources['logger'][0] ?? null;
		if ($logger instanceof ILogger) {
			$this->logger = $logger;
		}

		$this->conversationScope = $this->resolveConversationScope($context);
		$this->ensureInitialized();
		$this->log('initialized');
	}

	public function loadNodeHistory(string $nodeId): array {
		$this->ensureInitialized();
		$storageNodeId = $this->storageNodeId($nodeId);
		$nodes = $this->nodes();
		$history = $nodes[$storageNodeId] ?? null;

		if (!is_array($history)) {
			$history = $this->loadLegacyNodeHistory($nodeId);
			if ($history !== []) {
				$nodes[$storageNodeId] = $history;
				$this->setNodes($nodes);
			}
		}

		$history = is_array($history) ? array_values($history) : [];
		$this->log('load history for ' . $storageNodeId . ': ' . count($history) . ' [' . $this->session->getId() . ']');

		return $history;
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		$this->ensureInitialized();
		$storageNodeId = $this->storageNodeId($nodeId);
		$nodes = $this->nodes();
		$history = is_array($nodes[$storageNodeId] ?? null)
			? array_values($nodes[$storageNodeId])
			: $this->loadLegacyNodeHistory($nodeId);
		$history[] = $message;
		$this->trimNodeHistory($history);
		$nodes[$storageNodeId] = $history;
		$this->setNodes($nodes);
		$this->log('append message for ' . $storageNodeId . ' (len=' . count($history) . ')');
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		$this->ensureInitialized();
		$storageNodeId = $this->storageNodeId($nodeId);
		$nodes = $this->nodes();
		$history = $nodes[$storageNodeId] ?? null;

		if (!is_array($history)) {
			$history = $this->loadLegacyNodeHistory($nodeId);
		}
		if (!is_array($history)) {
			return false;
		}

		foreach ($history as &$entry) {
			if (is_array($entry) && ($entry['id'] ?? null) === $messageId) {
				$entry['feedback'] = $feedback;
				unset($entry);
				$nodes[$storageNodeId] = $history;
				$this->setNodes($nodes);
				return true;
			}
		}
		unset($entry);

		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		$this->ensureInitialized();
		$storageNodeId = $this->storageNodeId($nodeId);
		$nodes = $this->nodes();
		unset($nodes[$storageNodeId], $nodes[$nodeId]);
		$this->setNodes($nodes);

		$store = $this->readMemoryStore();
		$legacyBucket = $store[$this->namespace]['nodes'] ?? null;
		if (is_array($legacyBucket) && array_key_exists($nodeId, $legacyBucket)) {
			unset($store[$this->namespace]['nodes'][$nodeId]);
			$this->writeMemoryStore($store);
		}

		$this->log('reset history for ' . $storageNodeId);
	}

	public function getPriority(): int {
		return $this->priority;
	}

	private function ensureStarted(): void {
		if (!$this->session->started()) {
			$this->session->start();
		}
	}

	private function ensureInitialized(): void {
		if (!$this->session->started()) {
			return;
		}

		$store = $this->readMemoryStore();
		$changed = $this->session->get(self::FORMAT_KEY) !== self::FORMAT;

		$bucketKey = $this->bucketKey();
		if (!isset($store[$bucketKey]) || !is_array($store[$bucketKey])) {
			$store[$bucketKey] = ['nodes' => []];
			$changed = true;
		}
		if (!isset($store[$bucketKey]['nodes']) || !is_array($store[$bucketKey]['nodes'])) {
			$store[$bucketKey]['nodes'] = [];
			$changed = true;
		}

		if ($changed) {
			$this->writeMemoryStore($store);
		}
	}

	private function readMemoryStore(): array {
		$current = $this->readChunkStore(self::FORMAT_KEY, self::CHUNK_COUNT_KEY, self::CHUNK_KEY_PREFIX);
		if ($current !== null) {
			return $current;
		}

		$legacyArray = $this->session->get(self::LEGACY_ARRAY_KEY, []);
		if (is_array($legacyArray) && $legacyArray !== []) {
			return $legacyArray;
		}

		$legacyIlias = $this->readChunkStore(
			self::LEGACY_ILIAS_FORMAT_KEY,
			self::LEGACY_ILIAS_COUNT_KEY,
			self::LEGACY_ILIAS_CHUNK_PREFIX
		);

		return $legacyIlias ?? [];
	}

	private function readChunkStore(string $formatKey, string $countKey, string $prefix): ?array {
		if ($this->session->get($formatKey) !== self::FORMAT) {
			return null;
		}

		$count = (int)$this->session->get($countKey, 0);
		if ($count < 1 || $count > 10000) {
			return null;
		}

		$encoded = '';
		for ($index = 0; $index < $count; $index++) {
			$chunk = $this->session->get($this->chunkKey($prefix, $index));
			if (!is_string($chunk)) {
				return null;
			}
			$encoded .= $chunk;
		}

		$serialized = base64_decode($encoded, true);
		if (!is_string($serialized)) {
			return null;
		}

		$store = @unserialize($serialized, ['allowed_classes' => false]);

		return is_array($store) ? $store : null;
	}

	private function writeMemoryStore(array $store): void {
		if (!$this->session->started()) {
			return;
		}

		$this->clearChunkStore(self::FORMAT_KEY, self::CHUNK_COUNT_KEY, self::CHUNK_KEY_PREFIX);
		$this->clearChunkStore(self::LEGACY_ILIAS_FORMAT_KEY, self::LEGACY_ILIAS_COUNT_KEY, self::LEGACY_ILIAS_CHUNK_PREFIX);
		$this->session->remove(self::LEGACY_ARRAY_KEY);

		$chunks = str_split(base64_encode(serialize($store)), self::CHUNK_SIZE);
		$this->session->set(self::FORMAT_KEY, self::FORMAT);
		$this->session->set(self::CHUNK_COUNT_KEY, count($chunks));

		foreach ($chunks as $index => $chunk) {
			$this->session->set($this->chunkKey(self::CHUNK_KEY_PREFIX, (int)$index), $chunk);
		}
	}

	private function clearChunkStore(string $formatKey, string $countKey, string $prefix): void {
		$count = max(0, (int)$this->session->get($countKey, 0));
		$this->session->remove($formatKey);
		$this->session->remove($countKey);

		for ($index = 0; $index < $count; $index++) {
			$this->session->remove($this->chunkKey($prefix, $index));
		}
	}

	private function chunkKey(string $prefix, int $index): string {
		return $prefix . str_pad((string)$index, 5, '0', STR_PAD_LEFT);
	}

	private function nodes(): array {
		$bucket = $this->readMemoryStore()[$this->bucketKey()] ?? [];
		$nodes = is_array($bucket) ? ($bucket['nodes'] ?? []) : [];

		return is_array($nodes) ? $nodes : [];
	}

	private function setNodes(array $nodes): void {
		$store = $this->readMemoryStore();
		$bucketKey = $this->bucketKey();
		$store[$bucketKey] = is_array($store[$bucketKey] ?? null)
			? $store[$bucketKey]
			: [];
		$store[$bucketKey]['nodes'] = $nodes;
		$this->writeMemoryStore($store);
	}

	private function bucketKey(): string {
		return $this->namespace . '::' . $this->id();
	}

	private function storageNodeId(string $nodeId): string {
		return $this->conversationScope . '::' . $nodeId;
	}

	private function loadLegacyNodeHistory(string $nodeId): array {
		$store = $this->readMemoryStore();
		$candidates = [
			$store[$this->bucketKey()]['nodes'][$nodeId] ?? null,
			$store[$this->namespace]['nodes'][$nodeId] ?? null
		];

		foreach ($candidates as $candidate) {
			if (is_array($candidate) && $candidate !== []) {
				return array_values($candidate);
			}
		}

		return [];
	}

	private function resolveConversationScope(IAgentContext $context): string {
		foreach (['conversation_id', 'thread_id', 'chat_thread_id'] as $key) {
			$value = $this->readContextScalar($context, $key);
			if ($value !== '') {
				return $key . ':' . $value;
			}
		}

		$group = $this->readContextScalar($context, 'chatbot_config_group');
		$name = $this->readContextScalar($context, 'chatbot_config_name');
		if ($group !== '' || $name !== '') {
			return 'chatbot:' . $group . ':' . $name;
		}

		$agentId = $this->readContextScalar($context, 'agent_id');
		if ($agentId !== '') {
			return 'agent:' . $agentId;
		}

		return 'default';
	}

	private function readContextScalar(IAgentContext $context, string $key): string {
		try {
			$value = $context->getVar($key);
		} catch (\Throwable) {
			return '';
		}

		return is_scalar($value) ? trim((string)$value) : '';
	}

	private function trimNodeHistory(array &$history): void {
		if (count($history) > $this->max) {
			$history = array_values(array_slice($history, -$this->max));
		}
	}

	private function log(string $message): void {
		$this->logger?->log('sessionmemory', '[bucket=' . $this->bucketKey() . '][scope=' . $this->conversationScope . '] ' . $message);
	}
}

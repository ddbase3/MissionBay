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
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationScope;
use Base3\Api\ISchemaProvider;
use Base3\Logger\Api\ILogger;
use Base3\Session\Api\ISession;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * Session-backed conversation metadata and visible message history.
 *
 * The complete store is encoded into scalar chunks because host session
 * adapters are not required to preserve nested arrays reliably.
 */
class SessionMemoryAgentResource extends AbstractAgentResource implements IAgentConversationMemory, ISchemaProvider {

	private const FORMAT_KEY = 'base3_missionbay_conversation_memory_format';
	private const CHUNK_COUNT_KEY = 'base3_missionbay_conversation_memory_chunk_count';
	private const CHUNK_KEY_PREFIX = 'base3_missionbay_conversation_memory_chunk_';
	private const FORMAT = 'php-serialize-base64-v2';
	private const CHUNK_SIZE = 700;

	private ?ILogger $logger = null;
	private ?AgentConversationScope $scope = null;
	private string $namespace = 'default';
	private int $max = 20;
	private int $maxConversations = 50;
	private int $priority = 80;

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
		return 'Provides session-backed multi-conversation history through ISession.';
	}

	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'namespace' => [
					'type' => 'string',
					'description' => 'Session memory namespace used to isolate memory stores.',
					'default' => 'default'
				],
				'max' => [
					'type' => 'integer',
					'description' => 'Maximum number of visible messages stored per conversation and node.',
					'default' => 20,
					'minimum' => 2
				],
				'max_conversations' => [
					'type' => 'integer',
					'description' => 'Maximum number of conversations stored per owner and channel.',
					'default' => 50,
					'minimum' => 1
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
		$this->maxConversations = max(1, (int)($this->resolver->resolveValue($config['max_conversations'] ?? null) ?? 50));
		$this->priority = (int)($this->resolver->resolveValue($config['priority'] ?? null) ?? 80);
	}

	public function init(array $resources, IAgentContext $context): void {
		$logger = $resources['logger'][0] ?? null;
		if ($logger instanceof ILogger) {
			$this->logger = $logger;
		}

		$this->bindConversationScope($this->scopeFromContext($context));
		$this->log('initialized');
	}

	public function bindConversationScope(AgentConversationScope $scope): void {
		$this->scope = $scope;
		$this->ensureChannel();

		if ($scope->hasConversationId()) {
			$conversation = $this->getConversation($scope->getConversationId());
			if ($conversation === null) {
				$this->createConversation($scope->getConversationId());
			}
			else {
				$this->activateConversation($scope->getConversationId());
			}
		}
	}

	public function listConversations(): array {
		$channel = $this->channel();
		$rows = is_array($channel['conversations'] ?? null) ? $channel['conversations'] : [];
		$conversations = [];

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$conversations[] = AgentConversation::fromArray($row);
		}

		usort($conversations, static function(AgentConversation $left, AgentConversation $right): int {
			$result = strcmp($right->getLastActiveAt(), $left->getLastActiveAt());
			return $result !== 0 ? $result : strcmp($right->getCreatedAt(), $left->getCreatedAt());
		});

		return $conversations;
	}

	public function getConversation(string $conversationId): ?AgentConversation {
		$conversationId = $this->requireConversationId($conversationId);
		$row = $this->channel()['conversations'][$conversationId] ?? null;

		return is_array($row) ? AgentConversation::fromArray($row) : null;
	}

	public function getActiveConversation(): ?AgentConversation {
		return $this->listConversations()[0] ?? null;
	}

	public function createConversation(
		?string $conversationId = null,
		string $title = '',
		string $titleSource = AgentConversation::TITLE_SOURCE_TEMPORARY,
		string $openingMessage = ''
	): AgentConversation {
		$conversationId = $conversationId === null || trim($conversationId) === ''
			? $this->createTechnicalId('conversation')
			: $this->requireConversationId($conversationId);

		if ($this->getConversation($conversationId) !== null) {
			throw new \RuntimeException('Conversation already exists: ' . $conversationId);
		}
		if (count($this->listConversations()) >= $this->maxConversations) {
			throw new \RuntimeException('Conversation limit reached for this session channel.');
		}

		$now = $this->now();
		$row = [
			'id' => $conversationId,
			'title' => $this->normalizeTitle($title, $now),
			'title_source' => $titleSource,
			'opening_message' => $openingMessage,
			'created_at' => $now,
			'updated_at' => $now,
			'last_active_at' => $now,
			'nodes' => []
		];
		$conversation = AgentConversation::fromArray($row);
		$channel = $this->channel();
		$channel['conversations'][$conversationId] = $row;
		$this->setChannel($channel);
		$this->scope = $this->requireScope()->withConversationId($conversationId);
		$this->log('created conversation ' . $conversationId);

		return $conversation;
	}

	public function activateConversation(string $conversationId): AgentConversation {
		return $this->touchConversation($conversationId);
	}

	public function renameConversation(
		string $conversationId,
		string $title,
		string $titleSource = AgentConversation::TITLE_SOURCE_MANUAL
	): AgentConversation {
		$conversationId = $this->requireConversationId($conversationId);
		$conversation = $this->getConversation($conversationId);
		if ($conversation === null) {
			throw new \RuntimeException('Conversation not found: ' . $conversationId);
		}
		if (
			$titleSource === AgentConversation::TITLE_SOURCE_AUTOMATIC
			&& $conversation->getTitleSource() === AgentConversation::TITLE_SOURCE_MANUAL
		) {
			return $conversation;
		}

		$channel = $this->channel();
		$channel['conversations'][$conversationId]['title'] = $this->normalizeTitle($title);
		$channel['conversations'][$conversationId]['title_source'] = $titleSource;
		$channel['conversations'][$conversationId]['updated_at'] = $this->now();
		$this->setChannel($channel);

		return $this->requireConversation($conversationId);
	}

	public function deleteConversation(string $conversationId): void {
		$conversationId = $this->requireConversationId($conversationId);
		$channel = $this->channel();
		if (!isset($channel['conversations'][$conversationId])) {
			return;
		}

		unset($channel['conversations'][$conversationId]);
		$this->setChannel($channel);
		if ($this->requireScope()->getConversationId() === $conversationId) {
			$this->scope = new AgentConversationScope(
				$this->requireScope()->getOwnerKey(),
				$this->requireScope()->getChannelId()
			);
		}
		$this->log('deleted conversation ' . $conversationId);
	}

	public function touchConversation(string $conversationId): AgentConversation {
		$conversationId = $this->requireConversationId($conversationId);
		$this->requireConversation($conversationId);
		$channel = $this->channel();
		$channel['conversations'][$conversationId]['last_active_at'] = $this->now();
		$this->setChannel($channel);
		$this->scope = $this->requireScope()->withConversationId($conversationId);

		return $this->requireConversation($conversationId);
	}

	public function loadNodeHistory(string $nodeId): array {
		$conversation = $this->requireCurrentConversation();
		$nodeId = $this->requireNodeId($nodeId);
		$channel = $this->channel();
		$history = $channel['conversations'][$conversation->getId()]['nodes'][$nodeId] ?? [];
		$history = is_array($history) ? array_values($history) : [];
		$this->log('loaded ' . count($history) . ' messages for ' . $nodeId);

		return $history;
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		$conversation = $this->requireCurrentConversation();
		$nodeId = $this->requireNodeId($nodeId);
		$channel = $this->channel();
		$history = $channel['conversations'][$conversation->getId()]['nodes'][$nodeId] ?? [];
		$history = is_array($history) ? array_values($history) : [];
		$history[] = $message;
		$this->trimNodeHistory($history);
		$now = $this->now();
		$channel['conversations'][$conversation->getId()]['nodes'][$nodeId] = $history;
		$channel['conversations'][$conversation->getId()]['updated_at'] = $now;
		$channel['conversations'][$conversation->getId()]['last_active_at'] = $now;
		$this->setChannel($channel);
		$this->log('appended message for ' . $nodeId);
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		return $this->updateNodeHistoryMessageMetadata($nodeId, $messageId, [
			'feedback' => $feedback
		]);
	}

	public function updateNodeHistoryMessageMetadata(string $nodeId, string $messageId, array $metadata): bool {
		$conversation = $this->requireCurrentConversation();
		$nodeId = $this->requireNodeId($nodeId);
		$channel = $this->channel();
		$history = $channel['conversations'][$conversation->getId()]['nodes'][$nodeId] ?? null;
		if (!is_array($history)) {
			return false;
		}

		unset($metadata['id'], $metadata['role'], $metadata['content']);
		if ($metadata === []) {
			return false;
		}

		foreach ($history as &$entry) {
			if (!is_array($entry) || (string)($entry['id'] ?? '') !== $messageId) {
				continue;
			}
			$entry = array_merge($entry, $metadata);
			unset($entry);
			$channel['conversations'][$conversation->getId()]['nodes'][$nodeId] = $history;
			$channel['conversations'][$conversation->getId()]['updated_at'] = $this->now();
			$this->setChannel($channel);
			return true;
		}
		unset($entry);

		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		$conversation = $this->requireCurrentConversation();
		$nodeId = $this->requireNodeId($nodeId);
		$channel = $this->channel();
		unset($channel['conversations'][$conversation->getId()]['nodes'][$nodeId]);
		$channel['conversations'][$conversation->getId()]['updated_at'] = $this->now();
		$this->setChannel($channel);
		$this->log('reset history for ' . $nodeId);
	}

	public function getPriority(): int {
		return $this->priority;
	}

	private function scopeFromContext(IAgentContext $context): AgentConversationScope {
		$channelId = $this->contextString($context, 'conversation_channel_id');
		if ($channelId === '') {
			throw new \RuntimeException('Conversation memory requires context variable conversation_channel_id.');
		}

		return new AgentConversationScope(
			hash('sha256', 'session:' . $this->session->getId()),
			$channelId,
			$this->contextString($context, 'conversation_id')
		);
	}

	private function contextString(IAgentContext $context, string $key): string {
		$value = $context->getVar($key);

		return is_scalar($value) ? trim((string)$value) : '';
	}

	private function requireCurrentConversation(): AgentConversation {
		$scope = $this->requireScope();
		if ($scope->hasConversationId()) {
			$conversation = $this->getConversation($scope->getConversationId());
			return $conversation ?? $this->createConversation($scope->getConversationId());
		}

		return $this->getActiveConversation() ?? $this->createConversation();
	}

	private function requireConversation(string $conversationId): AgentConversation {
		$conversation = $this->getConversation($conversationId);
		if ($conversation === null) {
			throw new \RuntimeException('Conversation not found: ' . $conversationId);
		}

		return $conversation;
	}

	private function requireScope(): AgentConversationScope {
		if (!$this->scope instanceof AgentConversationScope) {
			throw new \RuntimeException('Conversation scope has not been bound.');
		}

		return $this->scope;
	}

	private function ensureStarted(): void {
		if (!$this->session->started() && !$this->session->start()) {
			throw new \RuntimeException('Session conversation memory could not start the session.');
		}
	}

	private function ensureChannel(): void {
		$store = $this->readStore();
		$key = $this->channelKey();
		if (is_array($store['channels'][$key] ?? null)) {
			return;
		}

		$scope = $this->requireScope();
		$store['channels'][$key] = [
			'namespace' => $this->namespace,
			'resource_id' => $this->id(),
			'owner_key' => $scope->getOwnerKey(),
			'channel_id' => $scope->getChannelId(),
			'conversations' => []
		];
		$this->writeStore($store);
	}

	/** @return array<string,mixed> */
	private function channel(): array {
		$this->ensureChannel();
		$channel = $this->readStore()['channels'][$this->channelKey()] ?? [];

		return is_array($channel) ? $channel : [];
	}

	/** @param array<string,mixed> $channel */
	private function setChannel(array $channel): void {
		$store = $this->readStore();
		$store['channels'][$this->channelKey()] = $channel;
		$this->writeStore($store);
	}

	private function channelKey(): string {
		$scope = $this->requireScope();

		return hash('sha256', implode('|', [
			$this->namespace,
			$this->id(),
			$scope->getOwnerKey(),
			$scope->getChannelId()
		]));
	}

	/** @return array<string,mixed> */
	private function readStore(): array {
		if ($this->session->get(self::FORMAT_KEY) !== self::FORMAT) {
			return ['channels' => []];
		}

		$count = (int)$this->session->get(self::CHUNK_COUNT_KEY, 0);
		if ($count < 1 || $count > 10000) {
			return ['channels' => []];
		}

		$encoded = '';
		for ($index = 0; $index < $count; $index++) {
			$chunk = $this->session->get($this->chunkKey($index));
			if (!is_string($chunk)) {
				return ['channels' => []];
			}
			$encoded .= $chunk;
		}

		$serialized = base64_decode($encoded, true);
		if (!is_string($serialized)) {
			return ['channels' => []];
		}

		$store = @unserialize($serialized, ['allowed_classes' => false]);
		if (!is_array($store) || !is_array($store['channels'] ?? null)) {
			return ['channels' => []];
		}

		return $store;
	}

	/** @param array<string,mixed> $store */
	private function writeStore(array $store): void {
		$oldCount = max(0, (int)$this->session->get(self::CHUNK_COUNT_KEY, 0));
		for ($index = 0; $index < $oldCount; $index++) {
			$this->session->remove($this->chunkKey($index));
		}

		$chunks = str_split(base64_encode(serialize($store)), self::CHUNK_SIZE);
		$this->session->set(self::FORMAT_KEY, self::FORMAT);
		$this->session->set(self::CHUNK_COUNT_KEY, count($chunks));
		foreach ($chunks as $index => $chunk) {
			$this->session->set($this->chunkKey((int)$index), $chunk);
		}
	}

	private function chunkKey(int $index): string {
		return self::CHUNK_KEY_PREFIX . str_pad((string)$index, 5, '0', STR_PAD_LEFT);
	}

	private function requireConversationId(string $conversationId): string {
		$conversationId = trim($conversationId);
		if ($conversationId === '' || strlen($conversationId) > 100 || preg_match('/^[A-Za-z0-9._:-]+$/', $conversationId) !== 1) {
			throw new \InvalidArgumentException('Invalid conversation id.');
		}

		return $conversationId;
	}

	private function requireNodeId(string $nodeId): string {
		$nodeId = trim($nodeId);
		if ($nodeId === '' || strlen($nodeId) > 100) {
			throw new \InvalidArgumentException('Invalid conversation node id.');
		}

		return $nodeId;
	}

	private function normalizeTitle(string $title, string $now = ''): string {
		$title = trim($title);
		if ($title === '') {
			$timestamp = $now !== '' ? strtotime($now) : time();
			$title = 'Chat ' . date('d.m.Y H:i', $timestamp ?: time());
		}

		return $this->truncateText($title, 255);
	}

	private function truncateText(string $value, int $maxLength): string {
		if (function_exists('mb_substr')) {
			return mb_substr($value, 0, $maxLength);
		}

		return substr($value, 0, $maxLength);
	}

	private function createTechnicalId(string $prefix): string {
		return $prefix . '-' . bin2hex(random_bytes(20));
	}

	private function now(): string {
		return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
	}

	/** @param array<int,mixed> $history */
	private function trimNodeHistory(array &$history): void {
		if (count($history) > $this->max) {
			$history = array_values(array_slice($history, -$this->max));
		}
	}

	private function log(string $message): void {
		$scope = $this->scope;
		$channel = $scope?->getChannelId() ?? '';
		$conversation = $scope?->getConversationId() ?? '';
		$this->logger?->log(
			'sessionmemory',
			'[namespace=' . $this->namespace . '][channel=' . $channel . '][conversation=' . $conversation . '] ' . $message
		);
	}
}

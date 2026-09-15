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
	private const MAX_CHUNKS = 10000;

	private ?ILogger $logger = null;
	private ?AgentConversationScope $scope = null;
	private string $namespace = 'default';
	private int $max = 20;
	private int $priority = 80;
	private bool $trimHistory = false;

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
					'description' => 'Maximum number of visible messages retained per conversation and node when trimming is enabled.',
					'default' => 20,
					'minimum' => 2
				],
				'trim' => [
					'type' => 'boolean',
					'description' => 'Whether node history is trimmed to the configured maximum.',
					'default' => false
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
		$this->trimHistory = $this->toBool($this->resolver->resolveValue($config['trim'] ?? null), false);
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
			if ($result !== 0) return $result;
			$result = strcmp($right->getCreatedAt(), $left->getCreatedAt());
			return $result !== 0 ? $result : strcmp($right->getId(), $left->getId());
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
			? $this->createPublicId('conversation')
			: $this->requireConversationId($conversationId);

		if ($this->getConversation($conversationId) !== null) {
			throw new \RuntimeException('Conversation already exists: ' . $conversationId);
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
		$row = $channel['conversations'][$conversationId];
		$row['title'] = $this->normalizeTitle($title);
		$row['title_source'] = $titleSource;
		$row['updated_at'] = $this->now();
		AgentConversation::fromArray($row);
		$channel['conversations'][$conversationId] = $row;
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
		$messageId = $this->normalizeMessageId($message['id'] ?? null);
		$role = trim((string)($message['role'] ?? ''));
		$content = (string)($message['content'] ?? '');
		$extra = $message;
		unset($extra['id'], $extra['role'], $extra['content']);

		$channel = $this->channel();
		$history = $channel['conversations'][$conversation->getId()]['nodes'][$nodeId] ?? [];
		$history = is_array($history) ? array_values($history) : [];
		foreach ($history as $entry) {
			if (is_array($entry) && (string)($entry['id'] ?? '') === $messageId) {
				throw new \RuntimeException('Conversation message already exists: ' . $messageId);
			}
		}

		$storedMessage = [
			'id' => $messageId,
			'role' => $role,
			'content' => $content
		];
		if ($extra !== []) {
			$storedMessage = array_merge($storedMessage, $extra);
		}
		$history[] = $storedMessage;
		$this->trimNodeHistory($history);

		$now = $this->now();
		$channel['conversations'][$conversation->getId()]['nodes'][$nodeId] = $history;
		$channel['conversations'][$conversation->getId()]['updated_at'] = $now;
		$channel['conversations'][$conversation->getId()]['last_active_at'] = $now;
		$this->setChannel($channel);
		$this->log('appended message for ' . $nodeId . ' (message_id=' . $messageId . ')');
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		return $this->updateNodeHistoryMessageMetadata($nodeId, $messageId, [
			'feedback' => $feedback
		]);
	}

	public function updateNodeHistoryMessageMetadata(string $nodeId, string $messageId, array $metadata): bool {
		$conversation = $this->requireCurrentConversation();
		$nodeId = $this->requireNodeId($nodeId);
		$messageId = $this->requireMessageId($messageId);
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
			$this->resolveOwnerKey(),
			$channelId,
			$this->contextString($context, 'conversation_id')
		);
	}

	private function resolveOwnerKey(): string {
		$sessionId = trim($this->session->getId());
		if ($sessionId === '') {
			throw new \RuntimeException('Session conversation memory requires a session identity.');
		}

		return hash('sha256', 'session:' . $sessionId);
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

	/** @return array<string,mixed> */
	private function channel(): array {
		$channel = $this->readStore()['channels'][$this->channelKey()] ?? null;
		if (is_array($channel)) {
			return $channel;
		}

		$scope = $this->requireScope();
		return [
			'namespace' => $this->namespace,
			'owner_key' => $scope->getOwnerKey(),
			'channel_id' => $scope->getChannelId(),
			'conversations' => []
		];
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
			$scope->getOwnerKey(),
			$scope->getChannelId()
		]));
	}

	/** @return array<string,mixed> */
	private function readStore(): array {
		$hasFormat = $this->session->has(self::FORMAT_KEY);
		$hasChunkCount = $this->session->has(self::CHUNK_COUNT_KEY);
		if (!$hasFormat && !$hasChunkCount) {
			return ['channels' => []];
		}
		if (!$hasFormat || !$hasChunkCount || $this->session->get(self::FORMAT_KEY) !== self::FORMAT) {
			throw new \RuntimeException('Session conversation memory contains an invalid store format.');
		}

		$countValue = $this->session->get(self::CHUNK_COUNT_KEY);
		if (!is_int($countValue) && !(is_string($countValue) && ctype_digit($countValue))) {
			throw new \RuntimeException('Session conversation memory contains an invalid chunk count.');
		}
		$count = (int)$countValue;
		if ($count < 1 || $count > self::MAX_CHUNKS) {
			throw new \RuntimeException('Session conversation memory contains an invalid chunk count.');
		}

		$encoded = '';
		for ($index = 0; $index < $count; $index++) {
			$chunk = $this->session->get($this->chunkKey($index));
			if (!is_string($chunk)) {
				throw new \RuntimeException('Session conversation memory contains an incomplete store.');
			}
			$encoded .= $chunk;
		}

		$serialized = base64_decode($encoded, true);
		if (!is_string($serialized)) {
			throw new \RuntimeException('Session conversation memory contains invalid encoded data.');
		}

		$store = @unserialize($serialized, ['allowed_classes' => false]);
		if (!is_array($store) || !is_array($store['channels'] ?? null)) {
			throw new \RuntimeException('Session conversation memory contains invalid serialized data.');
		}

		return $store;
	}

	/** @param array<string,mixed> $store */
	private function writeStore(array $store): void {
		$chunks = str_split(base64_encode(serialize($store)), self::CHUNK_SIZE);
		$count = count($chunks);
		if ($count < 1 || $count > self::MAX_CHUNKS) {
			throw new \RuntimeException('Session conversation memory exceeds the supported session store size.');
		}

		$oldCountValue = $this->session->get(self::CHUNK_COUNT_KEY, 0);
		$oldCount = is_int($oldCountValue) || (is_string($oldCountValue) && ctype_digit($oldCountValue))
			? min(self::MAX_CHUNKS, max(0, (int)$oldCountValue))
			: 0;

		foreach ($chunks as $index => $chunk) {
			$this->session->set($this->chunkKey((int)$index), $chunk);
		}
		for ($index = $count; $index < $oldCount; $index++) {
			$this->session->remove($this->chunkKey($index));
		}
		$this->session->set(self::CHUNK_COUNT_KEY, $count);
		$this->session->set(self::FORMAT_KEY, self::FORMAT);

		if ($this->readStore() !== $store) {
			throw new \RuntimeException('Session conversation memory could not be verified after writing.');
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

	private function normalizeMessageId(mixed $messageId): string {
		$messageId = is_scalar($messageId) ? trim((string)$messageId) : '';
		return $messageId !== '' ? $this->requireMessageId($messageId) : $this->createPublicId('message');
	}

	private function requireMessageId(string $messageId): string {
		$messageId = trim($messageId);
		if ($messageId === '' || strlen($messageId) > 100) {
			throw new \InvalidArgumentException('Invalid conversation message id.');
		}

		return $messageId;
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

	private function createPublicId(string $prefix): string {
		return $prefix . '-' . bin2hex(random_bytes(20));
	}

	private function now(): string {
		return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
	}

	/** @param array<int,mixed> $history */
	private function trimNodeHistory(array &$history): void {
		if ($this->trimHistory && count($history) > $this->max) {
			$history = array_values(array_slice($history, -$this->max));
		}
	}

	private function toBool(mixed $value, bool $default): bool {
		if ($value === null || $value === '') return $default;
		if (is_bool($value)) return $value;
		if (is_int($value)) return $value !== 0;
		$value = strtolower(trim((string)$value));
		if (in_array($value, ['1', 'true', 'yes', 'on'], true)) return true;
		if (in_array($value, ['0', 'false', 'no', 'off'], true)) return false;
		return $default;
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

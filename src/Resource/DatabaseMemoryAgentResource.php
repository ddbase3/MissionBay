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
use Base3\Api\IClassMap;
use Base3\Api\ISchemaProvider;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use MissionBay\Service\ConversationContentCipher;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;
use CredentialFoundation\Api\IEncryptionProfileService;

/**
 * Database-backed conversation metadata and visible message history.
 *
 * Tables are created and upgraded only by the MissionBay migration provider.
 * Storage keys are generated before INSERT operations. The implementation does
 * not use transactions, insertId(), affectedRows(), or database error-state
 * methods so it remains compatible with minimal BASE3 IDatabase implementations.
 */
class DatabaseMemoryAgentResource extends AbstractAgentResource implements IAgentConversationMemory, ISchemaProvider {

	private const CONVERSATION_TABLE = 'base3_missionbay_conversation';
	private const MESSAGE_TABLE = 'base3_missionbay_conversation_message';

	private ?ILogger $logger = null;
	private ?AgentConversationScope $scope = null;
	private ?IAgentContext $context = null;
	private readonly ConversationContentCipher $cipher;
	private ?string $legacyBackfilledOwnerId = null;
	private string $namespace = 'default';
	private int $max = 20;
	private int $priority = 80;
	private bool $trimHistory = false;

	public function __construct(
		private readonly IDatabase $database,
		private readonly IAgentConfigValueResolver $resolver,
		private readonly IUsermanager $usermanager,
		private readonly IClassMap $classmap,
		private readonly ITranslation $translation,
		private readonly IEncryptionProfileService $encryption,
		?string $id = null
	) {
		parent::__construct($id);
		$this->cipher = new ConversationContentCipher($encryption, $classmap);
	}

	public static function getName(): string {
		return 'databasememoryagentresource';
	}

	public function getDescription(): string {
		return 'Provides persistent multi-conversation history through IDatabase.';
	}

	/**
	 * Identifies one runtime memory resource in the central encryption registry.
	 * The namespace disambiguates MissionBay consumers from other providers.
	 */
	public static function encryptionTargetId(string $resourceId): string {
		return 'missionbay:' . $resourceId;
	}

	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'namespace' => [
					'type' => 'string',
					'description' => $this->translation->translate('ConversationMemory', 'database', 'namespace'),
					'default' => 'default'
				],
				'max' => [
					'type' => 'integer',
					'description' => $this->translation->translate('ConversationMemory', 'database', 'max'),
					'default' => 20, 'minimum' => 2
				],
				'trim' => [
					'type' => 'boolean',
					'description' => $this->translation->translate('ConversationMemory', 'database', 'trim'),
					'default' => false
				],
				'priority' => [
					'type' => 'integer',
					'description' => $this->translation->translate('ConversationMemory', 'database', 'priority'),
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

		// The preset materializer also initializes the router's unused child.
		// Only resolve and bind this scope when the database branch is selected.
		$this->context = $context;
	}

	public function bindConversationScope(AgentConversationScope $scope): void {
		if ($scope->getOwnerType() !== AgentConversationScope::OWNER_USER
			|| $scope->getOwnerId() !== $this->requireCurrentUserId()) {
			throw new \RuntimeException('Database conversation memory requires the current authenticated user.');
		}
		$this->requireChatbotSourceReference($scope->getChannelId());
		$this->scope = $scope;
		$this->backfillLegacyOwner($scope->getOwnerId());
		$this->backfillSourceReference($scope->getChannelId());
		if (!$scope->hasConversationId()) {
			return;
		}

		$conversation = $this->getConversation($scope->getConversationId());
		if ($conversation === null) {
			$this->createConversation($scope->getConversationId());
		}
		else {
			$this->activateConversation($scope->getConversationId());
		}
	}

	public function listConversations(): array {
		$scope = $this->requireScope();
		$this->database->connect();
		$rows = $this->database->multiQuery(
			'SELECT conversation_id, title, title_source, opening_message, encryption_method, encryption_key_id, created_at, updated_at, last_active_at'
			. ' FROM ' . self::CONVERSATION_TABLE
			. ' WHERE owner_type=' . $this->quote($scope->getOwnerType())
			. ' AND owner_id=' . $this->quote($scope->getOwnerId())
			. ' AND channel_id=' . $this->quote($scope->getChannelId())
			. ' AND memory_namespace=' . $this->quote($this->namespace)
			. ' ORDER BY last_active_at DESC, created_at DESC, conversation_id DESC'
		);

		$conversations = [];
		foreach ($rows as $row) {
			if (is_array($row)) {
				$conversations[] = $this->conversationFromRow($row);
			}
		}

		return $conversations;
	}

	public function getConversation(string $conversationId): ?AgentConversation {
		$row = $this->findConversationRow($this->requireConversationId($conversationId));

		return $row !== null ? $this->conversationFromRow($row) : null;
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
		$scope = $this->requireScope();
		$conversationId = $conversationId === null || trim($conversationId) === ''
			? $this->createPublicId('conversation')
			: $this->requireConversationId($conversationId);
		if ($this->findConversationRow($conversationId) !== null) {
			throw new \RuntimeException('Conversation already exists: ' . $conversationId);
		}

		$now = $this->now();
		$storageKey = $this->createStorageKey();
		$title = $this->normalizeTitle($title, $now);
		AgentConversation::fromArray([
			'id' => $conversationId,
			'title' => $title,
			'title_source' => $titleSource,
			'opening_message' => $openingMessage,
			'created_at' => $now,
			'updated_at' => $now,
			'last_active_at' => $now
		]);

		$writeKey = $this->encryption->getWriteKey(self::encryptionTargetId($this->getId()));
		$method = $writeKey?->getMethod() ?? 'none';
		$keyId = $writeKey?->getKeyId();
		[$configGroup, $configName] = $this->requireChatbotSourceReference($scope->getChannelId());
		$this->database->connect();
		$this->database->nonQuery(
			'INSERT INTO ' . self::CONVERSATION_TABLE
			. ' (conversation_key, owner_type, owner_id, channel_id, memory_namespace, conversation_id, chatbot_config_group, chatbot_config_name, title, title_source, opening_message, encryption_method, encryption_key_id, created_at, updated_at, last_active_at) VALUES ('
			. $this->quote($storageKey) . ', '
			. $this->quote($scope->getOwnerType()) . ', '
			. $this->quote($scope->getOwnerId()) . ', '
			. $this->quote($scope->getChannelId()) . ', '
			. $this->quote($this->namespace) . ', '
			. $this->quote($conversationId) . ', '
			. $this->nullableQuote($configGroup) . ', '
			. $this->nullableQuote($configName) . ', '
			. $this->quote($this->cipher->encode($title, $method, $keyId)) . ', '
			. $this->quote($titleSource) . ', '
			. $this->nullableEncrypted($openingMessage, $method, $keyId) . ', '
			. $this->quote($method) . ', '
			. ($keyId === null ? 'NULL' : $this->quote($keyId)) . ', '
			. $this->quote($now) . ', '
			. $this->quote($now) . ', '
			. $this->quote($now) . ')'
		);

		$row = $this->findConversationRow($conversationId);
		if ($row === null || (string)($row['conversation_key'] ?? '') !== $storageKey) {
			throw new \RuntimeException('Conversation could not be verified after creation.');
		}

		$this->scope = $scope->withConversationId($conversationId);
		$this->log('created conversation ' . $conversationId);

		return $this->conversationFromRow($row);
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
		$row = $this->requireConversationRow($conversationId);
		if (
			$titleSource === AgentConversation::TITLE_SOURCE_AUTOMATIC
			&& (string)($row['title_source'] ?? '') === AgentConversation::TITLE_SOURCE_MANUAL
		) {
			return $this->conversationFromRow($row);
		}

		$title = $this->normalizeTitle($title);
		AgentConversation::fromArray([
			'id' => $conversationId,
			'title' => $title,
			'title_source' => $titleSource,
			'opening_message' => $this->decryptedNullable($row['opening_message'] ?? null, $row),
			'created_at' => (string)($row['created_at'] ?? ''),
			'updated_at' => $this->now(),
			'last_active_at' => (string)($row['last_active_at'] ?? '')
		]);

		$now = $this->now();
		$this->database->nonQuery(
			'UPDATE ' . self::CONVERSATION_TABLE
			. ' SET title=' . $this->quote($this->cipher->encode($title, $this->rowMethod($row), $this->rowKeyId($row)))
			. ', title_source=' . $this->quote($titleSource)
			. ', updated_at=' . $this->quote($now)
			. ' WHERE conversation_key=' . $this->quote((string)$row['conversation_key'])
		);

		$updated = $this->requireConversationRow($conversationId);
		if ($this->decryptRowField((string)$updated['title'], $updated) !== $title || (string)$updated['title_source'] !== $titleSource) {
			throw new \RuntimeException('Conversation title could not be verified after update.');
		}

		return $this->conversationFromRow($updated);
	}

	public function deleteConversation(string $conversationId): void {
		$conversationId = $this->requireConversationId($conversationId);
		$row = $this->findConversationRow($conversationId);
		if ($row === null) {
			return;
		}

		$conversationKey = (string)$row['conversation_key'];
		$this->database->nonQuery(
			'DELETE FROM ' . self::CONVERSATION_TABLE
			. ' WHERE conversation_key=' . $this->quote($conversationKey)
		);
		if ($this->findConversationRow($conversationId) !== null) {
			throw new \RuntimeException('Conversation could not be verified as deleted.');
		}
		$message = $this->database->singleQuery(
			'SELECT message_key FROM ' . self::MESSAGE_TABLE
			. ' WHERE conversation_key=' . $this->quote($conversationKey)
			. ' LIMIT 1'
		);
		if (is_array($message)) {
			throw new \RuntimeException('Conversation messages were not deleted with their conversation.');
		}

		$scope = $this->requireScope();
		if ($scope->getConversationId() === $conversationId) {
			$this->scope = new AgentConversationScope($scope->getOwnerType(), $scope->getOwnerId(), $scope->getChannelId());
		}
		$this->log('deleted conversation ' . $conversationId);
	}

	public function deleteConversationsByOwner(string $ownerType, string $ownerId): void {
		if (!in_array($ownerType, [AgentConversationScope::OWNER_USER, AgentConversationScope::OWNER_SESSION], true)
			|| $ownerId === '' || strlen($ownerId) > 191) {
			throw new \InvalidArgumentException('Invalid conversation owner.');
		}
		// Lifecycle deletion also works after the owner has been deleted in the host.
		$legacyKey = $ownerType === AgentConversationScope::OWNER_USER
			? hash('sha256', 'user:' . $ownerId)
			: $ownerId;
		$condition = '(owner_type=' . $this->quote($ownerType)
			. ' AND owner_id=' . $this->quote($ownerId) . ')'
			. ' OR (owner_type IS NULL AND owner_key=' . $this->quote($legacyKey) . ')';
		$this->database->connect();
		$this->database->nonQuery(
			'DELETE FROM ' . self::CONVERSATION_TABLE . ' WHERE ' . $condition
		);
		$remaining = $this->database->singleQuery(
			'SELECT conversation_key FROM ' . self::CONVERSATION_TABLE
			. ' WHERE ' . $condition . ' LIMIT 1'
		);
		if (is_array($remaining)) {
			throw new \RuntimeException('Conversations could not be verified as deleted for owner.');
		}
		if ($this->scope?->getOwnerType() === $ownerType && $this->scope->getOwnerId() === $ownerId) {
			$this->scope = null;
		}
	}

	public function deleteConversationsByChannel(string $channelId): void {
		$channelId = $this->requireChannelId($channelId);
		$this->database->connect();
		$this->database->nonQuery(
			'DELETE FROM ' . self::CONVERSATION_TABLE
			. ' WHERE channel_id=' . $this->quote($channelId)
		);

		$remaining = $this->database->singleQuery(
			'SELECT conversation_key FROM ' . self::CONVERSATION_TABLE
			. ' WHERE channel_id=' . $this->quote($channelId)
			. ' LIMIT 1'
		);
		if (is_array($remaining)) {
			throw new \RuntimeException('Conversations could not be verified as deleted for channel.');
		}

		if ($this->scope?->getChannelId() === $channelId) {
			$this->scope = null;
		}
	}

	public function touchConversation(string $conversationId): AgentConversation {
		$conversationId = $this->requireConversationId($conversationId);
		$row = $this->requireConversationRow($conversationId);
		$now = $this->now();
		$this->database->nonQuery(
			'UPDATE ' . self::CONVERSATION_TABLE
			. ' SET last_active_at=' . $this->quote($now)
			. ' WHERE conversation_key=' . $this->quote((string)$row['conversation_key'])
		);

		$updated = $this->requireConversationRow($conversationId);
		if ((string)($updated['last_active_at'] ?? '') !== $now) {
			throw new \RuntimeException('Conversation activity could not be verified after update.');
		}
		$this->scope = $this->requireScope()->withConversationId($conversationId);

		return $this->conversationFromRow($updated);
	}

	public function loadNodeHistory(string $nodeId): array {
		$row = $this->requireCurrentConversationRow();
		$nodeId = $this->requireNodeId($nodeId);
		$rows = $this->database->multiQuery(
			'SELECT message_id, role, content, payload, encryption_method, encryption_key_id'
			. ' FROM ' . self::MESSAGE_TABLE
			. ' WHERE conversation_key=' . $this->quote((string)$row['conversation_key'])
			. ' AND node_id=' . $this->quote($nodeId)
			. ' ORDER BY created_at ASC, message_key ASC'
		);

		$history = [];
		foreach ($rows as $messageRow) {
			if (!is_array($messageRow)) {
				continue;
			}
			$message = [
				'id' => (string)($messageRow['message_id'] ?? ''),
				'role' => (string)($messageRow['role'] ?? ''),
				'content' => $this->decryptRowField((string)($messageRow['content'] ?? ''), $messageRow)
			];
			$payload = $this->decodePayload($this->decryptedNullable($messageRow['payload'] ?? null, $messageRow));
			$history[] = $payload !== [] ? array_merge($message, $payload) : $message;
		}

		$this->log('loaded ' . count($history) . ' messages for ' . $nodeId);

		return $history;
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		$row = $this->requireCurrentConversationRow();
		$nodeId = $this->requireNodeId($nodeId);
		$messageId = $this->normalizeMessageId($message['id'] ?? null);
		$role = trim((string)($message['role'] ?? ''));
		$content = (string)($message['content'] ?? '');
		$extra = $message;
		unset($extra['id'], $extra['role'], $extra['content']);
		$payload = $extra !== [] ? $this->encodePayload($extra) : null;
		$messageKey = $this->createStorageKey();
		$now = $this->now();

		$writeKey = $this->encryption->getWriteKey(self::encryptionTargetId($this->getId()));
		$method = $writeKey?->getMethod() ?? 'none';
		$keyId = $writeKey?->getKeyId();
		$this->database->nonQuery(
			'INSERT INTO ' . self::MESSAGE_TABLE
			. ' (message_key, conversation_key, node_id, message_id, role, content, payload, encryption_method, encryption_key_id, created_at) VALUES ('
			. $this->quote($messageKey) . ', '
			. $this->quote((string)$row['conversation_key']) . ', '
			. $this->quote($nodeId) . ', '
			. $this->quote($messageId) . ', '
			. $this->quote($role) . ', '
			. $this->quote($this->cipher->encode($content, $method, $keyId)) . ', '
			. ($payload === null ? 'NULL' : $this->quote($this->cipher->encode($payload, $method, $keyId))) . ', '
			. $this->quote($method) . ', '
			. ($keyId === null ? 'NULL' : $this->quote($keyId)) . ', '
			. $this->quote($now) . ')'
		);

		$stored = $this->database->singleQuery(
			'SELECT message_key FROM ' . self::MESSAGE_TABLE
			. ' WHERE message_key=' . $this->quote($messageKey)
			. ' LIMIT 1'
		);
		if (!is_array($stored) || (string)($stored['message_key'] ?? '') !== $messageKey) {
			throw new \RuntimeException('Conversation message could not be verified after creation.');
		}

		$this->database->nonQuery(
			'UPDATE ' . self::CONVERSATION_TABLE
			. ' SET updated_at=' . $this->quote($now)
			. ', last_active_at=' . $this->quote($now)
			. ' WHERE conversation_key=' . $this->quote((string)$row['conversation_key'])
		);
		$updatedConversation = $this->requireConversationRow($this->requireScope()->getConversationId());
		if (
			(string)($updatedConversation['updated_at'] ?? '') !== $now
			|| (string)($updatedConversation['last_active_at'] ?? '') !== $now
		) {
			throw new \RuntimeException('Conversation activity could not be verified after appending a message.');
		}
		$this->trimNodeHistory((string)$row['conversation_key'], $nodeId);
		$this->log('appended message for ' . $nodeId . ' (message_id=' . $messageId . ')');
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		return $this->updateNodeHistoryMessageMetadata($nodeId, $messageId, [
			'feedback' => $feedback
		]);
	}

	public function updateNodeHistoryMessageMetadata(string $nodeId, string $messageId, array $metadata): bool {
		$row = $this->requireCurrentConversationRow();
		$nodeId = $this->requireNodeId($nodeId);
		$messageId = $this->requireMessageId($messageId);
		unset($metadata['id'], $metadata['role'], $metadata['content']);
		if ($metadata === []) {
			return false;
		}

		$messageRow = $this->database->singleQuery(
			'SELECT message_key, payload, encryption_method, encryption_key_id FROM ' . self::MESSAGE_TABLE
			. ' WHERE conversation_key=' . $this->quote((string)$row['conversation_key'])
			. ' AND node_id=' . $this->quote($nodeId)
			. ' AND message_id=' . $this->quote($messageId)
			. ' LIMIT 1'
		);
		if (!is_array($messageRow)) {
			return false;
		}

		$payload = array_merge(
			$this->decodePayload($this->decryptedNullable($messageRow['payload'] ?? null, $messageRow)),
			$metadata
		);
		$encoded = $this->encodePayload($payload);
		$messageKey = (string)($messageRow['message_key'] ?? '');
		$this->database->nonQuery(
			'UPDATE ' . self::MESSAGE_TABLE
			. ' SET payload=' . $this->quote($this->cipher->encode($encoded, $this->rowMethod($messageRow), $this->rowKeyId($messageRow)))
			. ' WHERE message_key=' . $this->quote($messageKey)
		);

		$verified = $this->database->singleQuery(
			'SELECT payload, encryption_method, encryption_key_id FROM ' . self::MESSAGE_TABLE
			. ' WHERE message_key=' . $this->quote($messageKey)
			. ' LIMIT 1'
		);
		if (!is_array($verified)) {
			return false;
		}
		$verifiedPayload = $this->decodePayload($this->decryptedNullable($verified['payload'] ?? null, $verified));

		foreach ($metadata as $key => $value) {
			if (!array_key_exists($key, $verifiedPayload) || $verifiedPayload[$key] !== $value) {
				return false;
			}
		}

		return true;
	}

	public function resetNodeHistory(string $nodeId): void {
		$row = $this->requireCurrentConversationRow();
		$nodeId = $this->requireNodeId($nodeId);
		$conversationKey = (string)$row['conversation_key'];
		$this->database->nonQuery(
			'DELETE FROM ' . self::MESSAGE_TABLE
			. ' WHERE conversation_key=' . $this->quote($conversationKey)
			. ' AND node_id=' . $this->quote($nodeId)
		);
		$remaining = $this->database->singleQuery(
			'SELECT message_key FROM ' . self::MESSAGE_TABLE
			. ' WHERE conversation_key=' . $this->quote($conversationKey)
			. ' AND node_id=' . $this->quote($nodeId)
			. ' LIMIT 1'
		);
		if (is_array($remaining)) {
			throw new \RuntimeException('Conversation node history could not be verified as reset.');
		}
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
			AgentConversationScope::OWNER_USER,
			$this->requireCurrentUserId(),
			$channelId,
			$this->contextString($context, 'conversation_id')
		);
	}

	private function requireCurrentUserId(): string {
		$user = $this->usermanager->getUser();
		$id = $user !== null && (is_string($user->id) || is_int($user->id))
			? (string)$user->id : '';
		if (trim($id) === '' || strlen($id) > 191) {
			throw new \RuntimeException('Database conversation memory requires an authenticated native user ID.');
		}
		return $id;
	}

	private function backfillLegacyOwner(string $userId): void {
		if ($this->legacyBackfilledOwnerId === $userId) {
			return;
		}
		$this->database->connect();
		$legacyHash = hash('sha256', 'user:' . $userId);
		$this->database->nonQuery(
			'UPDATE ' . self::CONVERSATION_TABLE
			. ' SET owner_type=' . $this->quote(AgentConversationScope::OWNER_USER)
			. ', owner_id=' . $this->quote($userId)
			. ' WHERE owner_type IS NULL AND owner_key=' . $this->quote($legacyHash)
		);
		$unresolved = $this->database->singleQuery(
			'SELECT conversation_key FROM ' . self::CONVERSATION_TABLE
			. ' WHERE owner_type IS NULL AND owner_key=' . $this->quote($legacyHash) . ' LIMIT 1'
		);
		if (is_array($unresolved)) {
			throw new \RuntimeException('Legacy conversation owner migration could not be verified.');
		}
		$this->legacyBackfilledOwnerId = $userId;
	}

	/**
	 * A persisted chatbot channel must be attributable to one configured instance.
	 * Non-chatbot channels, including scheduled agents, do not require a chatbot reference.
	 *
	 * @return array{0:string,1:string}
	 */
	private function requireChatbotSourceReference(string $channelId): array {
		$group = $this->context instanceof IAgentContext
			? $this->contextString($this->context, 'chatbot_config_group') : '';
		$name = $this->context instanceof IAgentContext
			? $this->contextString($this->context, 'chatbot_config_name') : '';
		if (str_starts_with($channelId, 'chatbot:')
			&& ($group === '' || $name === '' || strlen($group) > 191 || strlen($name) > 191)) {
			throw new \RuntimeException($this->translation->translate('ConversationMemory', 'database', 'invalid_source_reference'));
		}
		return [$group, $name];
	}

	private function backfillSourceReference(string $channelId): void {
		if (!$this->context instanceof IAgentContext) {
			return;
		}
		$group = $this->contextString($this->context, 'chatbot_config_group');
		$name = $this->contextString($this->context, 'chatbot_config_name');
		if ($group === '' || $name === '' || strlen($group) > 191 || strlen($name) > 191) {
			return;
		}
		$this->database->nonQuery(
			'UPDATE ' . self::CONVERSATION_TABLE
			. ' SET chatbot_config_group=' . $this->quote($group)
			. ', chatbot_config_name=' . $this->quote($name)
			. ' WHERE channel_id=' . $this->quote($channelId)
			. ' AND (chatbot_config_group IS NULL OR chatbot_config_name IS NULL)'
		);
	}

	private function contextString(IAgentContext $context, string $key): string {
		$value = $context->getVar($key);

		return is_scalar($value) ? trim((string)$value) : '';
	}

	/** @return array<string,mixed>|null */
	private function findConversationRow(string $conversationId): ?array {
		$scope = $this->requireScope();
		$this->database->connect();

		return $this->database->singleQuery(
			'SELECT conversation_key, conversation_id, title, title_source, opening_message, encryption_method, encryption_key_id, created_at, updated_at, last_active_at'
			. ' FROM ' . self::CONVERSATION_TABLE
			. ' WHERE owner_type=' . $this->quote($scope->getOwnerType())
			. ' AND owner_id=' . $this->quote($scope->getOwnerId())
			. ' AND channel_id=' . $this->quote($scope->getChannelId())
			. ' AND memory_namespace=' . $this->quote($this->namespace)
			. ' AND conversation_id=' . $this->quote($conversationId)
			. ' LIMIT 1'
		);
	}

	/** @return array<string,mixed> */
	private function requireConversationRow(string $conversationId): array {
		$row = $this->findConversationRow($conversationId);
		if ($row === null) {
			throw new \RuntimeException('Conversation not found: ' . $conversationId);
		}

		return $row;
	}

	/** @return array<string,mixed> */
	private function requireCurrentConversationRow(): array {
		$scope = $this->requireScope();
		if ($scope->hasConversationId()) {
			$row = $this->findConversationRow($scope->getConversationId());
			if ($row !== null) {
				return $row;
			}
			$this->createConversation($scope->getConversationId());
			return $this->requireConversationRow($scope->getConversationId());
		}

		$active = $this->getActiveConversation();
		if ($active === null) {
			$active = $this->createConversation();
		}
		$this->scope = $scope->withConversationId($active->getId());

		return $this->requireConversationRow($active->getId());
	}

	private function requireScope(): AgentConversationScope {
		if (!$this->scope instanceof AgentConversationScope) {
			if (!$this->context instanceof IAgentContext) {
				throw new \RuntimeException('Conversation scope has not been bound.');
			}
			$this->bindConversationScope($this->scopeFromContext($this->context));
		}
		if ($this->scope->getOwnerType() !== AgentConversationScope::OWNER_USER
			|| $this->scope->getOwnerId() !== $this->requireCurrentUserId()) {
			throw new \RuntimeException('Database conversation scope no longer matches the current user.');
		}
		return $this->scope;
	}

	/** @param array<string,mixed> $row */
	private function conversationFromRow(array $row): AgentConversation {
		return AgentConversation::fromArray([
			'id' => $row['conversation_id'] ?? '',
			'title' => $this->decryptRowField((string)($row['title'] ?? ''), $row),
			'title_source' => $row['title_source'] ?? AgentConversation::TITLE_SOURCE_TEMPORARY,
			'opening_message' => $this->decryptedNullable($row['opening_message'] ?? null, $row),
			'created_at' => $row['created_at'] ?? '',
			'updated_at' => $row['updated_at'] ?? '',
			'last_active_at' => $row['last_active_at'] ?? ''
		]);
	}

	private function trimNodeHistory(string $conversationKey, string $nodeId): void {
		if (!$this->trimHistory) {
			return;
		}

		$rows = $this->database->multiQuery(
			'SELECT message_key FROM ' . self::MESSAGE_TABLE
			. ' WHERE conversation_key=' . $this->quote($conversationKey)
			. ' AND node_id=' . $this->quote($nodeId)
			. ' ORDER BY created_at DESC, message_key DESC'
		);
		if (count($rows) <= $this->max) {
			return;
		}

		$keys = [];
		foreach (array_slice($rows, $this->max) as $row) {
			$key = is_array($row) ? trim((string)($row['message_key'] ?? '')) : '';
			if ($key !== '') {
				$keys[] = $this->quote($key);
			}
		}
		if ($keys !== []) {
			$this->database->nonQuery(
				'DELETE FROM ' . self::MESSAGE_TABLE
				. ' WHERE message_key IN (' . implode(', ', $keys) . ')'
			);
		}
	}

	/** @return array<string,mixed> */
	private function decodePayload(mixed $payload): array {
		if (!is_string($payload) || trim($payload) === '') {
			return [];
		}
		$decoded = json_decode($payload, true);

		return is_array($decoded) ? $decoded : [];
	}

	/** @param array<string,mixed> $payload */
	private function encodePayload(array $payload): string {
		$encoded = json_encode(
			$payload,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
		);
		if (!is_string($encoded)) {
			throw new \RuntimeException('Conversation message payload could not be encoded.');
		}

		return $encoded;
	}

	/** @param array<string,mixed> $row */
	private function rowMethod(array $row): string {
		return (string)($row['encryption_method'] ?? 'none');
	}

	/** @param array<string,mixed> $row */
	private function rowKeyId(array $row): ?string {
		$id = $row['encryption_key_id'] ?? null;
		return is_string($id) && $id !== '' ? $id : null;
	}

	/** @param array<string,mixed> $row */
	private function decryptRowField(string $value, array $row): string {
		return $this->cipher->decode($value, $this->rowMethod($row), $this->rowKeyId($row));
	}

	/** @param array<string,mixed> $row */
	private function decryptedNullable(mixed $value, array $row): string {
		return $value === null ? '' : $this->decryptRowField((string)$value, $row);
	}

	private function nullableEncrypted(string $value, string $method, ?string $keyId): string {
		return $value === '' ? 'NULL' : $this->quote($this->cipher->encode($value, $method, $keyId));
	}

	private function quote(string $value): string {
		return "'" . $this->database->escape($value) . "'";
	}

	private function nullableQuote(string $value): string {
		return $value === '' ? 'NULL' : $this->quote($value);
	}


	private function requireChannelId(string $channelId): string {
		$channelId = trim($channelId);
		if ($channelId === '' || strlen($channelId) > 191 || preg_match('/^[A-Za-z0-9._:-]+$/', $channelId) !== 1) {
			throw new \InvalidArgumentException('Invalid conversation channel id.');
		}

		return $channelId;
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

		return function_exists('mb_substr') ? mb_substr($title, 0, 255) : substr($title, 0, 255);
	}

	private function createPublicId(string $prefix): string {
		return $prefix . '-' . bin2hex(random_bytes(20));
	}

	private function createStorageKey(): string {
		return bin2hex(random_bytes(20));
	}

	private function now(): string {
		return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
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
		$this->logger?->log(
			'dbmemory',
			'[namespace=' . $this->namespace . ']'
			. '[channel=' . ($scope?->getChannelId() ?? '') . ']'
			. '[conversation=' . ($scope?->getConversationId() ?? '') . '] '
			. $message
		);
	}
}

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

namespace Base3\MissionBay\Resource;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentConversationMemory;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\AssistantFoundation\Dto\AgentConversation;
use Base3\AssistantFoundation\Dto\AgentConversationScope;
use Base3\MissionBay\Agent\AgentNodeDock;
use Base3\MissionBay\Api\IAgentConfigValueResolver;

/**
 * Applies profile-level read/write settings to one conversation memory.
 */
class ConfiguredAgentMemoryResource extends AbstractAgentResource implements IAgentConversationMemory {

	private ?IAgentConversationMemory $memory = null;
	private bool $enabled = true;
	private bool $readEnabled = true;
	private bool $writeEnabled = true;
	private ?int $priority = null;

	public function __construct(
		private readonly IAgentConfigValueResolver $resolver,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'configuredagentmemoryresource';
	}

	public function getDescription(): string {
		return 'Wraps one configured conversation memory and applies read/write settings.';
	}

	/** @return AgentNodeDock[] */
	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'memory',
				description: 'Configured conversation-memory resource.',
				interface: IAgentConversationMemory::class,
				maxConnections: 1,
				required: true
			)
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);
		$this->enabled = $this->toBool($this->resolver->resolveValue($config['enabled'] ?? null), true);
		$this->readEnabled = $this->toBool($this->resolver->resolveValue($config['read_enabled'] ?? null), true);
		$this->writeEnabled = $this->toBool($this->resolver->resolveValue($config['write_enabled'] ?? null), true);
		$this->priority = $this->resolveNullableInt($config['priority'] ?? null);
	}

	public function init(array $resources, IAgentContext $context): void {
		$candidate = $resources['memory'][0] ?? null;
		$this->memory = $candidate instanceof IAgentConversationMemory ? $candidate : null;
		if ($this->enabled && !$this->memory instanceof IAgentConversationMemory) {
			throw new \RuntimeException('Configured conversation memory requires one IAgentConversationMemory resource.');
		}
	}

	public function bindConversationScope(AgentConversationScope $scope): void {
		$this->requireMemory()->bindConversationScope($scope);
	}

	public function listConversations(): array {
		return $this->canRead() ? $this->requireMemory()->listConversations() : [];
	}

	public function getConversation(string $conversationId): ?AgentConversation {
		return $this->canRead() ? $this->requireMemory()->getConversation($conversationId) : null;
	}

	public function getActiveConversation(): ?AgentConversation {
		return $this->canRead() ? $this->requireMemory()->getActiveConversation() : null;
	}

	public function createConversation(
		?string $conversationId = null,
		string $title = '',
		string $titleSource = AgentConversation::TITLE_SOURCE_TEMPORARY,
		string $openingMessage = ''
	): AgentConversation {
		$this->requireWrite();
		return $this->requireMemory()->createConversation($conversationId, $title, $titleSource, $openingMessage);
	}

	public function activateConversation(string $conversationId): AgentConversation {
		$this->requireWrite();
		return $this->requireMemory()->activateConversation($conversationId);
	}

	public function renameConversation(
		string $conversationId,
		string $title,
		string $titleSource = AgentConversation::TITLE_SOURCE_MANUAL
	): AgentConversation {
		$this->requireWrite();
		return $this->requireMemory()->renameConversation($conversationId, $title, $titleSource);
	}

	public function deleteConversation(string $conversationId): void {
		$this->requireWrite();
		$this->requireMemory()->deleteConversation($conversationId);
	}

	public function deleteConversationsByOwner(string $ownerType, string $ownerId): void {
		$this->requireLifecycleMemory()->deleteConversationsByOwner($ownerType, $ownerId);
	}

	public function deleteConversationsByChannel(string $channelId): void {
		$this->requireLifecycleMemory()->deleteConversationsByChannel($channelId);
	}

	public function touchConversation(string $conversationId): AgentConversation {
		$this->requireWrite();
		return $this->requireMemory()->touchConversation($conversationId);
	}

	public function loadNodeHistory(string $nodeId): array {
		return $this->canRead() ? $this->requireMemory()->loadNodeHistory($nodeId) : [];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		if ($this->canWrite()) {
			$this->requireMemory()->appendNodeHistory($nodeId, $message);
		}
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		return $this->canWrite()
			? $this->requireMemory()->setFeedback($nodeId, $messageId, $feedback)
			: false;
	}

	public function updateNodeHistoryMessageMetadata(string $nodeId, string $messageId, array $metadata): bool {
		return $this->canWrite()
			? $this->requireMemory()->updateNodeHistoryMessageMetadata($nodeId, $messageId, $metadata)
			: false;
	}

	public function resetNodeHistory(string $nodeId): void {
		if ($this->canWrite()) {
			$this->requireMemory()->resetNodeHistory($nodeId);
		}
	}

	public function getPriority(): int {
		if ($this->priority !== null) {
			return $this->priority;
		}
		return $this->memory?->getPriority() ?? 100;
	}

	public function getWrappedMemory(): ?IAgentMemory {
		return $this->memory;
	}

	public function isReadEnabled(): bool {
		return $this->canRead();
	}

	public function isWriteEnabled(): bool {
		return $this->canWrite();
	}

	/** Lifecycle deletion is permitted on a read/write-disabled memory, but never silently skipped. */
	private function requireLifecycleMemory(): IAgentConversationMemory {
		if (!$this->memory instanceof IAgentConversationMemory) {
			throw new \RuntimeException('Configured conversation memory is unavailable for lifecycle deletion.');
		}
		return $this->memory;
	}

	private function requireMemory(): IAgentConversationMemory {
		if (!$this->enabled || !$this->memory instanceof IAgentConversationMemory) {
			throw new \RuntimeException('Configured conversation memory is not available.');
		}

		return $this->memory;
	}

	private function canRead(): bool {
		return $this->enabled && $this->readEnabled && $this->memory instanceof IAgentConversationMemory;
	}

	private function canWrite(): bool {
		return $this->enabled && $this->writeEnabled && $this->memory instanceof IAgentConversationMemory;
	}

	private function requireWrite(): void {
		if (!$this->canWrite()) {
			throw new \RuntimeException('Configured conversation memory is not writable.');
		}
	}

	private function resolveNullableInt(mixed $config): ?int {
		$value = $this->resolver->resolveValue($config);
		if ($value === null || $value === '') return null;
		return (int)$value;
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
}

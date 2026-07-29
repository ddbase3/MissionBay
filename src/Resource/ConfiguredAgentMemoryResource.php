<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationScope;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;

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

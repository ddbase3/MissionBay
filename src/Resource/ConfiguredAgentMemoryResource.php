<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Api\IAgentMemory;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentMemoryRoleProvider;

/**
 * Applies profile-level read/write settings to one conversation memory.
 */
class ConfiguredAgentMemoryResource extends AbstractAgentResource implements IAgentConversationMemory, IAgentMemoryRoleProvider {

	private ?IAgentMemory $memory = null;
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
				interface: IAgentMemory::class,
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
		$this->memory = $candidate instanceof IAgentMemory ? $candidate : null;
	}

	public function loadNodeHistory(string $nodeId): array {
		if (!$this->enabled || !$this->readEnabled || !$this->memory instanceof IAgentMemory) {
			return [];
		}
		return $this->memory->loadNodeHistory($nodeId);
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		if (!$this->enabled || !$this->writeEnabled || !$this->memory instanceof IAgentMemory) {
			return;
		}
		$this->memory->appendNodeHistory($nodeId, $message);
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		if (!$this->enabled || !$this->writeEnabled || !$this->memory instanceof IAgentMemory) {
			return false;
		}
		return $this->memory->setFeedback($nodeId, $messageId, $feedback);
	}

	public function resetNodeHistory(string $nodeId): void {
		if (!$this->enabled || !$this->writeEnabled || !$this->memory instanceof IAgentMemory) {
			return;
		}
		$this->memory->resetNodeHistory($nodeId);
	}

	public function getPriority(): int {
		if ($this->priority !== null) {
			return $this->priority;
		}
		return $this->memory instanceof IAgentMemory ? $this->memory->getPriority() : 100;
	}

	public function providesConversationMemory(): bool {
		return $this->enabled && $this->memory instanceof IAgentMemory;
	}

	public function providesContextContributions(): bool {
		return false;
	}

	public function usesLegacyMemorySemantics(): bool {
		if (!$this->enabled || !$this->memory instanceof IAgentMemory) {
			return false;
		}
		if ($this->memory instanceof IAgentMemoryRoleProvider) {
			return $this->memory->usesLegacyMemorySemantics();
		}
		return !($this->memory instanceof IAgentConversationMemory)
			&& !($this->memory instanceof IAgentContextContributor);
	}

	public function getWrappedMemory(): ?IAgentMemory {
		return $this->memory;
	}

	public function isReadEnabled(): bool {
		return $this->enabled && $this->readEnabled;
	}

	public function isWriteEnabled(): bool {
		return $this->enabled && $this->writeEnabled;
	}

	public function getConfiguredRole(): string {
		return 'conversation-memory';
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

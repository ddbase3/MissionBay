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

use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;

/**
 * ConfiguredAgentMemoryResource
 *
 * Wraps one docked IAgentMemory and exposes configurable runtime behavior.
 * The first use case is per-agent memory ordering through getPriority().
 */
class ConfiguredAgentMemoryResource extends AbstractAgentResource implements IAgentMemory {

	private ?IAgentMemory $memory = null;
	private bool $enabled = true;
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
		return 'Wraps one agent memory and exposes configurable runtime behavior, including priority.';
	}

	/**
	 * @return AgentNodeDock[]
	 */
	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'memory',
				description: 'Memory that should be exposed through this configured wrapper.',
				interface: IAgentMemory::class,
				maxConnections: 1,
				required: true
			)
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->enabled = $this->toBool($this->resolver->resolveValue($config['enabled'] ?? null), true);
		$this->priority = $this->resolveNullableInt($config['priority'] ?? null);
	}

	public function init(array $resources, IAgentContext $context): void {
		$this->memory = null;

		if (!empty($resources['memory'][0]) && $resources['memory'][0] instanceof IAgentMemory) {
			$this->memory = $resources['memory'][0];
		}
	}

	public function loadNodeHistory(string $nodeId): array {
		if (!$this->enabled || !$this->memory instanceof IAgentMemory) {
			return [];
		}

		return $this->memory->loadNodeHistory($nodeId);
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		if (!$this->enabled || !$this->memory instanceof IAgentMemory) {
			return;
		}

		$this->memory->appendNodeHistory($nodeId, $message);
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		if (!$this->enabled || !$this->memory instanceof IAgentMemory) {
			return false;
		}

		return $this->memory->setFeedback($nodeId, $messageId, $feedback);
	}

	public function resetNodeHistory(string $nodeId): void {
		if (!$this->enabled || !$this->memory instanceof IAgentMemory) {
			return;
		}

		$this->memory->resetNodeHistory($nodeId);
	}

	public function getPriority(): int {
		if ($this->priority !== null) {
			return $this->priority;
		}

		if ($this->memory instanceof IAgentMemory) {
			return $this->memory->getPriority();
		}

		return 100;
	}

	private function resolveNullableInt(mixed $config): ?int {
		$value = $this->resolver->resolveValue($config);

		if ($value === null || $value === '') {
			return null;
		}

		return (int)$value;
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

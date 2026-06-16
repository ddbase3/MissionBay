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
use MissionBay\Api\IAgentTool;

/**
 * ConfiguredAgentToolResource
 *
 * Wraps one docked IAgentTool and exposes a configured tool surface.
 * This allows per-agent naming, labels and metadata without changing the
 * underlying tool implementation.
 */
class ConfiguredAgentToolResource extends AbstractAgentResource implements IAgentTool {

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

	public function __construct(
		private readonly IAgentConfigValueResolver $resolver,
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
			$definitions[] = $definition;
		}

		return $definitions;
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		if (!$this->enabled) {
			throw new \InvalidArgumentException('Configured tool is disabled: ' . $name);
		}

		if (!$this->tool instanceof IAgentTool) {
			throw new \InvalidArgumentException('Configured tool has no docked tool: ' . $name);
		}

		if (!isset($this->nameMap[$name])) {
			$this->getToolDefinitions();
		}

		$originalName = $this->nameMap[$name] ?? null;

		if ($originalName === null) {
			throw new \InvalidArgumentException('Unsupported configured tool: ' . $name);
		}

		return $this->tool->callTool($originalName, $arguments, $context);
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

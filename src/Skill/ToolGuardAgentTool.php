<?php declare(strict_types=1);

namespace MissionBay\Skill;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentTool;

final class ToolGuardAgentTool implements IAgentTool {

	/**
	 * @var array<string,bool>
	 */
	private array $allowedMap = [];

	/**
	 * @param string[] $allowedToolNames
	 */
	public function __construct(
		private IAgentTool $inner,
		array $allowedToolNames
	) {
		foreach ($allowedToolNames as $name) {
			$name = (string)$name;
			if ($name !== '') {
				$this->allowedMap[$name] = true;
			}
		}
	}

	public static function getName(): string {
		// IBase requirement, delegate name is fine but stable.
		return 'toolguardagenttool';
	}

	public function getToolDefinitions(): array {
		$defs = $this->inner->getToolDefinitions();
		$out = [];

		foreach ($defs as $def) {
			$name = (string)($def['function']['name'] ?? '');
			if ($name === '' || !$this->isAllowed($name)) {
				continue;
			}
			$out[] = $def;
		}

		return $out;
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		if (!$this->isAllowed($name)) {
			throw new \RuntimeException('Tool not allowed: ' . $name);
		}

		return $this->inner->callTool($name, $arguments, $context);
	}

	private function isAllowed(string $toolName): bool {
		return isset($this->allowedMap[$toolName]);
	}
}

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

namespace MissionBay\Profile;

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

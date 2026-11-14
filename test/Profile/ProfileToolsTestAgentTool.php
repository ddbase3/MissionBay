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

namespace Base3\MissionBay\Test\Profile;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Api\IAgentTool;

final class ProfileToolsTestAgentTool implements IAgentTool {

	/** @param array<int,array<string,mixed>> $definitions */
	public function __construct(private readonly array $definitions = []) {}

	public static function getName(): string {
		return 'profiletoolstestagenttool';
	}

	public function getToolDefinitions(): array {
		return $this->definitions;
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return ['name' => $name, 'arguments' => $arguments];
	}
}

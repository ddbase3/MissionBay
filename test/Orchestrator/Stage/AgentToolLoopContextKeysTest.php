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

namespace Base3\MissionBay\Test\Orchestrator\Stage;

use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentToolLoopContextKeysTest extends TestCase {

	public function testTemporaryKeysAreUniqueAndNamespaced(): void {
		$keys = AgentToolLoopContextKeys::getTemporaryRuntimeKeys();
		$this->assertNotEmpty($keys);
		$this->assertSame($keys, array_values(array_unique($keys)));
		foreach ($keys as $key) {
			$this->assertTrue(str_starts_with($key, AgentToolLoopContextKeys::PREFIX));
		}
	}

	public function testTemporaryKeysIncludeTransientCapabilitiesButNotPersistentMessages(): void {
		$keys = AgentToolLoopContextKeys::getTemporaryRuntimeKeys();
		foreach ([
			AgentToolLoopContextKeys::MODEL,
			AgentToolLoopContextKeys::TOOLS,
			AgentToolLoopContextKeys::TOOL_DEFINITIONS,
			AgentToolLoopContextKeys::MUTATION_TOOL_NAMES,
			AgentToolLoopContextKeys::RESUME_HANDLE,
			AgentToolLoopContextKeys::EXECUTION_STATUS
		] as $key) {
			$this->assertTrue(in_array($key, $keys, true));
		}
		$this->assertFalse(in_array(AgentToolLoopContextKeys::MESSAGES, $keys, true));
		$this->assertFalse(in_array(AgentToolLoopContextKeys::STAGE_TRACE, $keys, true));
	}
}

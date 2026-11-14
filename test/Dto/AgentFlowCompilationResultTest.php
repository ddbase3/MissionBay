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

namespace Base3\MissionBay\Test\Dto;

use Base3\MissionBay\Dto\AgentFlowCompilation;
use PHPUnit\Framework\TestCase;

final class AgentFlowCompilationResultTest extends TestCase {

	public function testResultPreservesCompiledGraphAndWarnings(): void {
		$flow = ['nodes' => [['id' => 'start']], 'connections' => []];
		$warnings = ['unused resource', 'incomplete optional port'];
		$result = new AgentFlowCompilation($flow, $warnings);
		$this->assertSame($flow, $result->getFlow());
		$this->assertSame($warnings, $result->getWarnings());
	}

	public function testResultDefaultsToNoWarningsAndAnEmptyGraph(): void {
		$result = new AgentFlowCompilation([]);
		$this->assertSame([], $result->getFlow());
		$this->assertSame([], $result->getWarnings());
	}
}

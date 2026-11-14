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

use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Profile\ProfilePlan;
use Base3\MissionBay\Profile\ToolDefFilter;
use PHPUnit\Framework\TestCase;

final class ToolDefFilterTest extends TestCase {

	public function testUnrestrictedPlanKeepsDefinitionsAndReportsMissingRequiredTools(): void {
		$tool = $this->tool([['function' => ['name' => 'b']], ['function' => ['name' => 'a']]]);
		$result = (new ToolDefFilter())->filter([$tool], new ProfilePlan('p', null, null, ['missing', 'b', 'missing']));
		$this->assertNull($result['allowedToolNames']);
		$this->assertSame([['function' => ['name' => 'b']], ['function' => ['name' => 'a']]], $result['toolDefs']);
		$this->assertSame(['missing'], $result['report']->getMissingRequiredTools());
		$this->assertFalse($result['report']->isFeasible());
	}

	public function testAllowedNamesAreSortedDeduplicatedAndIntersectAvailable(): void {
		$first = $this->tool([['function' => ['name' => 'x']], ['function' => ['name' => 'b']]]);
		$second = $this->tool([['function' => ['name' => 'a']], ['function' => ['name' => 'x']]]);
		$result = (new ToolDefFilter())->filter([$first, $second], new ProfilePlan('p', null, ['a', 'x', 'x', '', 'missing'], ['x']));
		$this->assertSame(['a', 'x'], $result['allowedToolNames']);
		$this->assertSame(['x', 'a', 'x'], array_map(static fn(array $def): string => $def['function']['name'], $result['toolDefs']));
		$this->assertTrue($result['report']->isFeasible());
	}

	public function testExplicitEmptyAllowedListRemovesAllTools(): void {
		$result = (new ToolDefFilter())->filter([$this->tool([['function' => ['name' => 'x']]])], new ProfilePlan('p', null, [], ['x']));
		$this->assertSame([], $result['allowedToolNames']);
		$this->assertSame([], $result['toolDefs']);
		$this->assertTrue($result['report']->isFeasible());
	}

	public function testInvalidAndNamelessDefinitionsAreOmitted(): void {
		$result = (new ToolDefFilter())->filter([$this->tool([['function' => []], ['function' => ['name' => 'ok']]])], new ProfilePlan('p'));
		$this->assertSame([['function' => ['name' => 'ok']]], $result['toolDefs']);
	}

	private function tool(array $definitions): IAgentTool {
		$tool = $this->createStub(IAgentTool::class);
		$tool->method('getToolDefinitions')->willReturn($definitions);
		return $tool;
	}
}

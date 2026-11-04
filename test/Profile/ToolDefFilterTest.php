<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use MissionBay\Api\IAgentTool;
use MissionBay\Profile\ProfilePlan;
use MissionBay\Profile\ToolDefFilter;
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

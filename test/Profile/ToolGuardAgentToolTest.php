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
use Base3\MissionBay\Profile\ToolGuardAgentTool;
use PHPUnit\Framework\TestCase;

final class ToolGuardAgentToolTest extends TestCase {

	public function testOnlyAllowedDefinitionsAreVisible(): void {
		$inner = $this->createStub(IAgentTool::class);
		$inner->method('getToolDefinitions')->willReturn([
			['function' => ['name' => 'write']],
			['function' => ['name' => 'read']],
			['function' => ['name' => '']],
			['function' => ['name' => 'read']]
		]);
		$guard = new ToolGuardAgentTool($inner, ['read', '', 'read']);
		$this->assertSame([['function' => ['name' => 'read']], ['function' => ['name' => 'read']]], $guard->getToolDefinitions());
		$this->assertSame([], $guard->getOutputSchemas());
		$this->assertSame('toolguardagenttool', $guard::getName());
	}

	public function testRejectedToolNameCannotReachDelegate(): void {
		$inner = $this->createStub(IAgentTool::class);
		$guard = new ToolGuardAgentTool($inner, ['read']);
		$this->expectException(\RuntimeException::class);
		$guard->callTool('write', ['v' => 1], $this->createStub(IAgentContext::class));
	}

	public function testAuthorizedCallForwardsArgumentsAndContext(): void {
		$context = $this->createStub(IAgentContext::class);
		$inner = $this->createStub(IAgentTool::class);
		$inner->method('callTool')->willReturnCallback(static function(string $name, array $arguments, IAgentContext $actual) use ($context): array {
			return [$name, $arguments, $actual === $context];
		});
		$guard = new ToolGuardAgentTool($inner, ['read']);
		$this->assertSame(['read', ['id' => 1], true], $guard->callTool('read', ['id' => 1], $context));
	}

	public function testSourceMetadataDefaultsToUnderlyingTechnicalName(): void {
		$inner = new ProfileToolsTestAgentTool();
		$guard = new ToolGuardAgentTool($inner, []);
		$this->assertSame('profiletoolstestagenttool', $guard->getCapabilitySourceId());
		$this->assertSame('profiletoolstestagenttool', $guard->getCapabilitySourceLabel());
		$this->assertSame('', $guard->getCapabilitySourceDescription());
	}
}

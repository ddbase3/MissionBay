<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentTool;
use MissionBay\Profile\ToolGuardAgentTool;
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

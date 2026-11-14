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

namespace Base3\MissionBay\Test\Resource\AgentTool\Log;

use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\AgentTool\Log\LogAgentTool;
use PHPUnit\Framework\TestCase;

final class LogAgentToolTest extends TestCase {

	public function testToolDefinitionRequiresOnlyTheMessage(): void {
		$records = [];
		$tool = $this->tool($records);
		$definitions = $tool->getToolDefinitions();
		$this->assertSame('logagenttool', LogAgentTool::getName());
		$this->assertSame(['message'], $definitions[0]['function']['parameters']['required']);
		$this->assertSame('agent_log', $definitions[0]['function']['name']);
		$this->assertSame('string', $tool->getSchema()['properties']['scope']['type']);
	}

	public function testConfiguredScopeCannotBeOverriddenByAgentArgumentsOrData(): void {
		$records = [];
		$tool = $this->tool($records);
		$tool->setConfig(['scope' => 'project', 'defaultlevel' => 'warning']);
		$result = $tool->callTool('agent_log', [
			'message' => '  Hello  ',
			'scope' => 'evil',
			'data' => ['scope' => 'spoof', 'agentToolId' => 'spoof', 'extra' => 42]
		], new AgentContext());
		$this->assertSame(['ok' => true, 'logged' => true, 'scope' => 'project', 'level' => 'warning'], $result);
		$this->assertCount(1, $records);
		$this->assertSame(['warning', 'Hello'], array_slice($records[0], 0, 2));
		$this->assertSame(['scope' => 'project', 'agentToolId' => 'audit-tool', 'extra' => 42], $records[0][2]);
	}

	public function testExplicitLevelIsNormalized(): void {
		$records = [];
		$tool = $this->tool($records);
		$result = $tool->callTool('agent_log', ['message' => 'message', 'level' => ' DEBUG '], new AgentContext());
		$this->assertSame('debug', $result['level']);
		$this->assertSame('debug', $records[0][0]);
	}

	public function testMissingMessageReportsErrorAndLogsWithinFixedScope(): void {
		$records = [];
		$tool = $this->tool($records);
		$tool->setConfig(['scope' => 'testing']);
		$result = $tool->callTool('agent_log', ['message' => '  '], new AgentContext());
		$this->assertSame('missing_message', $result['error_code']);
		$this->assertFalse($result['ok']);
		$this->assertSame('error', $records[0][0]);
		$this->assertSame('testing', $records[0][2]['scope']);
	}

	public function testUnrecognizedLevelAndInvalidDataAreRejected(): void {
		$records = [];
		$tool = $this->tool($records);
		$levelResult = $tool->callTool('agent_log', ['message' => 'hi', 'level' => 'trace'], new AgentContext());
		$dataResult = $tool->callTool('agent_log', ['message' => 'hi', 'data' => 'not an object'], new AgentContext());
		$this->assertSame('unsupported_level', $levelResult['error_code']);
		$this->assertSame('invalid_data', $dataResult['error_code']);
		$this->assertCount(2, $records);
	}

	public function testUnknownToolIsRejectedWithoutLogging(): void {
		$records = [];
		$tool = $this->tool($records);
		try {
			$tool->callTool('agent_delete', ['message' => 'anything'], new AgentContext());
			$this->fail('Unsupported tool must fail.');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('Unsupported tool', $e->getMessage());
		}
		$this->assertSame([], $records);
	}

	public function testInitWritesAnAuditableEntryWithConfiguredScope(): void {
		$records = [];
		$tool = $this->tool($records);
		$tool->setConfig(['scope' => 'operations', 'defaultlevel' => 'notice']);
		$tool->init([], new AgentContext());
		$this->assertCount(1, $records);
		$this->assertSame('info', $records[0][0]);
		$this->assertStringContainsString('Initialized scope=operations', $records[0][1]);
		$this->assertSame('operations', $records[0][2]['scope']);
	}

	private function tool(array &$records): LogAgentTool {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(static fn(mixed $value): mixed => $value);
		$settings = $this->createStub(ISettingsStore::class);
		$logger = $this->createStub(ILogger::class);
		$logger->method('logLevel')->willReturnCallback(static function(string $level, string|\Stringable $message, array $context) use (&$records): void {
			$records[] = [$level, (string)$message, $context];
		});
		return new LogAgentTool($resolver, $settings, $logger, 'audit-tool');
	}
}

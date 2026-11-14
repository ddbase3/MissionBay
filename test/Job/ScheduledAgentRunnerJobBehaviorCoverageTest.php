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

namespace Base3\MissionBay\Test\Job;

use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use Base3\AssistantFoundation\Api\IAgentConfigurationService;
use Base3\MissionBay\Job\ScheduledAgentRunnerJob;
use PHPUnit\Framework\TestCase;

final class ScheduledAgentRunnerJobBehaviorCoverageTest extends TestCase {

	private function job(array $agents = [], array $configuration = []): ScheduledAgentRunnerJob {
		$configurations = $this->createStub(IAgentConfigurationService::class);
		$configurations->method('getAgents')->willReturn($agents);
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn($configuration);
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn([]);
		return new ScheduledAgentRunnerJob($configurations, $classMap, $config, $this->createStub(IAgentExecutionService::class));
	}

	public function testJobIsInactiveWithoutExplicitConfiguration(): void {
		$job = $this->job();
		$this->assertFalse($job->isActive());
		$this->assertSame(1, $job->getPriority());
	}

	public function testJobRespectsExplicitActivationAndPriority(): void {
		$job = $this->job([], ['scheduledagentrunnerjob.active' => 1, 'scheduledagentrunnerjob.priority' => 11]);
		$this->assertTrue($job->isActive());
		$this->assertSame(11, $job->getPriority());
	}

	public function testEmptyAgentGroupCompletesWithoutExecutions(): void {
		$this->assertSame('Scheduled agents done - total:0 active:0 due:0 ran:0 skipped:0 disabled:0 errors:0', $this->job()->go());
	}

	public function testDisabledAgentIsCountedWithoutLoadingTimingPolicy(): void {
		$output = $this->job(['disabled' => ['enabled' => false, 'policy' => ['policy' => 'not-registered']]])->go();
		$this->assertStringContainsString('total:1', $output);
		$this->assertStringContainsString('disabled:1', $output);
		$this->assertStringContainsString('errors:0', $output);
	}

	public function testAgentWithoutTimingPolicyDoesNotExecute(): void {
		$output = $this->job(['scheduled-a' => ['enabled' => true]])->go();
		$this->assertStringContainsString('active:1', $output);
		$this->assertStringContainsString('due:0', $output);
		$this->assertStringContainsString('errors:1', $output);
		$this->assertStringContainsString('no timing policy', $output);
	}

	public function testUnknownTimingPolicyDoesNotExecuteAgent(): void {
		$output = $this->job(['scheduled-a' => ['enabled' => true, 'policy' => ['policy' => 'unknown-policy']]])->go();
		$this->assertStringContainsString('errors:1', $output);
		$this->assertStringContainsString('unknown timing policy', $output);
	}

	public function testMalformedAgentSettingsAreReportedWithoutFatalErrors(): void {
		$output = $this->job(['invalid' => 'string-not-array'])->go();
		$this->assertStringContainsString('total:1', $output);
		$this->assertStringContainsString('errors:1', $output);
		$this->assertStringContainsString('not an array', $output);
	}

	public function testInvalidTechnicalAgentIdCannotBeDispatched(): void {
		$output = $this->job(['!!!' => ['enabled' => true]])->go();
		$this->assertStringContainsString('errors:1', $output);
		$this->assertStringContainsString('empty id', $output);
	}

	public function testMixedEnabledAndDisabledAgentRecordsAreCountedSeparately(): void {
		$output = $this->job([
			'first' => ['enabled' => false],
			'second' => ['enabled' => 'no'],
			'third' => ['enabled' => true]
		])->go();
		$this->assertStringContainsString('total:3', $output);
		$this->assertStringContainsString('disabled:2', $output);
		$this->assertStringContainsString('active:1', $output);
	}

	public function testSettingsBackendFailureIsReportedWithoutLeakingAnException(): void {
		$configurations = $this->createStub(IAgentConfigurationService::class);
		$configurations->method('getAgents')->willThrowException(new \RuntimeException('backend unavailable'));
		$config = $this->createStub(IConfiguration::class);
		$job = new ScheduledAgentRunnerJob($configurations, $this->createStub(IClassMap::class), $config, $this->createStub(IAgentExecutionService::class));
		$this->assertSame('Scheduled agent runner failed: backend unavailable', $job->go());
	}
}

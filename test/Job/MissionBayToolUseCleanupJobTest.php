<?php declare(strict_types=1);

namespace MissionBay\Test\Job;

use Base3\Configuration\Api\IConfiguration;
use Base3\Database\Api\IDatabase;
use MissionBay\Job\MissionBayToolUseCleanupJob;
use PHPUnit\Framework\TestCase;

final class MissionBayToolUseCleanupJobTest extends TestCase {

	public function testUsesDailyWindowPolicyAndIsActiveByDefault(): void {
		$database = $this->createMock(IDatabase::class);
		$configuration = $this->createMock(IConfiguration::class);
		$configuration
			->method('get')
			->with('job')
			->willReturn([]);

		$job = new MissionBayToolUseCleanupJob($database, $configuration);

		$this->assertTrue($job->isActive());
		$this->assertSame(1, $job->getPriority());
		$this->assertSame([
			'policy' => 'dailywindowjobpolicy',
			'data' => [
				'from' => '02:00',
				'to' => '04:00'
			]
		], $job->getPolicyDefinition());
	}

	public function testConfigurationCanDisableCleanupJob(): void {
		$database = $this->createMock(IDatabase::class);
		$configuration = $this->createMock(IConfiguration::class);
		$configuration
			->method('get')
			->with('job')
			->willReturn([
				'missionbaytoolusecleanupjob.active' => 0,
				'missionbaytoolusecleanupjob.priority' => 7
			]);

		$job = new MissionBayToolUseCleanupJob($database, $configuration);

		$this->assertFalse($job->isActive());
		$this->assertSame(7, $job->getPriority());
	}

	public function testCleanupRequiresCreateAndUpdateToBeOlderThan24Hours(): void {
		$database = $this->createMock(IDatabase::class);
		$configuration = $this->createMock(IConfiguration::class);

		$database->expects($this->once())->method('connect');
		$database->method('connected')->willReturn(true);
		$database
			->expects($this->once())
			->method('singleQuery')
			->with("SHOW TABLES LIKE 'base3_missionbay_tooluse'")
			->willReturn(['base3_missionbay_tooluse']);
		$database
			->method('escape')
			->willReturnCallback(static fn(string $value): string => $value);

		$earliestCutoff = time() - (24 * 3600) - 2;
		$latestCutoff = time() - (24 * 3600) + 2;

		$database
			->expects($this->once())
			->method('nonQuery')
			->with($this->callback(function(string $sql) use ($earliestCutoff, $latestCutoff): bool {
				$this->assertStringContainsString('DELETE FROM `base3_missionbay_tooluse`', $sql);
				$this->assertStringContainsString('`created_at` <', $sql);
				$this->assertStringContainsString('`updated_at` <', $sql);
				$this->assertStringContainsString('ORDER BY `created_at` ASC, `id` ASC', $sql);
				$this->assertStringContainsString('LIMIT 100000', $sql);

				preg_match_all("/'([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2})'/", $sql, $matches);
				$this->assertCount(2, $matches[1]);
				$this->assertSame($matches[1][0], $matches[1][1]);

				$cutoff = strtotime($matches[1][0] . ' UTC');
				$this->assertNotFalse($cutoff);
				$this->assertGreaterThanOrEqual($earliestCutoff, $cutoff);
				$this->assertLessThanOrEqual($latestCutoff, $cutoff);

				return true;
			}));

		$job = new MissionBayToolUseCleanupJob($database, $configuration);
		$result = $job->go();

		$this->assertStringStartsWith('Tool-use cleanup done', $result);
	}

	public function testCleanupSkipsWhenToolUseTableDoesNotExist(): void {
		$database = $this->createMock(IDatabase::class);
		$configuration = $this->createMock(IConfiguration::class);

		$database->expects($this->once())->method('connect');
		$database->method('connected')->willReturn(true);
		$database
			->expects($this->once())
			->method('singleQuery')
			->with("SHOW TABLES LIKE 'base3_missionbay_tooluse'")
			->willReturn(null);
		$database->expects($this->never())->method('nonQuery');

		$job = new MissionBayToolUseCleanupJob($database, $configuration);

		$this->assertSame(
			'Skip (base3_missionbay_tooluse does not exist)',
			$job->go()
		);
	}
}

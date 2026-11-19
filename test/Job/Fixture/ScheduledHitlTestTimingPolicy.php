<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Job\Fixture;

use Base3\CoreFoundation\Worker\Api\IJobExecutionPolicy;

final class ScheduledHitlTestTimingPolicy implements IJobExecutionPolicy {

	public int $marked = 0;
	public bool $due = true;

	public static function getName(): string { return 'scheduledhitltesttimingpolicy'; }
	public function getSchema(): array { return ['type' => 'object']; }
	public function setData(array $data) {}
	public function shouldRun(string $jobName) { return $this->due; }
	public function markRun(string $jobName) { $this->marked++; }
	public function getReason() { return 'Not due'; }
}

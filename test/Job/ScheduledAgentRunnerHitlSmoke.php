<?php declare(strict_types=1);

use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Dto\AgentSuspensionScope;
use Base3\Core\ClassMap;
use Base3\Core\Configuration\ArrayConfiguration;
use Base3\MissionBay\Job\ScheduledAgentRunnerJob;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlAgentConfigurations;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlSuspensionRepository;
use Base3\MissionBay\Test\Job\Fixture\ScheduledHitlTestExecution;
use Base3\MissionBay\Test\Job\Fixture\ScheduledHitlTestTimingPolicy;
use Base3\MissionBay\Test\Orchestrator\ApprovalMemoryStateStore;

$root = dirname(__DIR__, 4);
foreach (['DIR_ROOT' => $root . '/', 'DIR_PLUGIN' => $root . '/plugin/', 'DIR_TEST' => $root . '/test/'] as $name => $value) {
	if (!defined($name)) define($name, $value);
}
require_once DIR_ROOT . 'bootstrap/Autoloader.php';
Base3\Bootstrap\Autoloader::register();

function checkScheduled(bool $ok, string $message): void {
	if (!$ok) throw new RuntimeException($message);
}

$settings = new AgentAdminHitlAgentConfigurations();
$settings->settings['policy'] = ['policy' => 'test', 'data' => []];
$state = new ApprovalMemoryStateStore();
$repo = new AgentAdminHitlSuspensionRepository();
$repo->scope = AgentSuspensionScope::forConversation('scheduled-agent:jarvis', 'scheduled-run:jarvis', 'scheduled-agent:jarvis');
$execution = new ScheduledHitlTestExecution($repo);
$timing = new ScheduledHitlTestTimingPolicy();
$map = (new ReflectionClass(ClassMap::class))->newInstanceWithoutConstructor();
$job = new ScheduledAgentRunnerJob($settings, $map, new ArrayConfiguration(['job' => ['scheduledagentrunnerjob.active' => 1]]), $execution, $repo, $state);
(new ReflectionProperty(ScheduledAgentRunnerJob::class, 'policiesById'))->setValue($job, ['test' => $timing]);

$execution->statuses = [AgentExecutionStatus::AWAITING_APPROVAL, AgentExecutionStatus::COMPLETED];
$first = $job->go();
checkScheduled(str_contains($first, 'pending:1') && str_contains($first, 'ran:0') && $timing->marked === 0, 'Suspension was marked successful.');
checkScheduled($job->hasHeldRun('jarvis'), 'Suspended run has no durable hold marker.');
checkScheduled($execution->lastRequest->getContext()['agent_id'] === 'jarvis', 'Job agent id was not set.');
$second = $job->go();
checkScheduled($execution->calls === 1 && str_contains($second, 'pending:1'), 'Job restarted a suspended execution.');
$invalid = false;
try { $job->resumePendingReview('jarvis', new AgentResume('wrong-handle')); } catch (RuntimeException $e) { $invalid = true; }
checkScheduled($invalid && $execution->calls === 1, 'Foreign resume handle was accepted.');
$resumed = $job->resumePendingReview('jarvis', new AgentResume('job-resume-handle'));
checkScheduled($resumed->getAgentResult()->isCompleted() && $timing->marked === 1 && !$job->hasHeldRun('jarvis'), 'Resume did not finish and mark the job.');
checkScheduled($execution->lastRequest->getInputs()['resume']['resume_handle'] === 'job-resume-handle', 'Wrong resume invocation.');

$execution->statuses = [AgentExecutionStatus::AWAITING_APPROVAL];
$third = $job->go();
checkScheduled(str_contains($third, 'pending:1') && $timing->marked === 1, 'Second suspension did not preserve first success.');
$repo->pending = null;
$expired = $job->go();
checkScheduled(str_contains($expired, 'skipped:1') && $execution->calls === 3 && $job->hasHeldRun('jarvis'), 'Expired suspension caused automatic replay.');
$job->resetExpiredRun('jarvis');
checkScheduled(!$job->hasHeldRun('jarvis'), 'Operator reset did not release the hold.');
$job->go();
checkScheduled($execution->calls === 4 && $timing->marked === 2, 'Operator rearm did not enable the next run.');

$execution->statuses = [AgentExecutionStatus::FAILED];
$failed = $job->go();
checkScheduled(str_contains($failed, 'errors:1') && $timing->marked === 2 && $job->hasHeldRun('jarvis'), 'Failed job was marked successful or retried automatically.');
$job->resetExpiredRun('jarvis');
$execution->statuses = [AgentExecutionStatus::COMPLETED];
$job->go();
checkScheduled($timing->marked === 3, 'Rearmed job did not complete.');

$execution->statuses = [AgentExecutionStatus::AWAITING_APPROVAL];
$job->go();
$resetLive = false;
try { $job->resetExpiredRun('jarvis'); } catch (RuntimeException $e) { $resetLive = true; }
checkScheduled($resetLive && $job->hasHeldRun('jarvis'), 'Live suspension could be reset.');

echo "Scheduled agent lifecycle, holds and resume: OK\n";

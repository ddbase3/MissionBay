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

namespace Base3\MissionBay\Job;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use Base3\AssistantFoundation\Api\IAgentConfigurationService;
use Base3\CoreFoundation\Worker\Api\IJob;
use Base3\CoreFoundation\Worker\Api\IJobExecutionPolicy;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentExecutionResult;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Dto\AgentSuspensionScope;
use Base3\AssistantFoundation\Dto\AgentSuspensionState;
use Base3\AssistantFoundation\Api\IAgentSuspensionRepository;
use Base3\CoreFoundation\State\Api\IStateStore;
use Throwable;

/**
 * ScheduledAgentRunnerJob
 *
 * Dispatcher job for scheduled runtime-selectable agents.
 *
 * The job runs whenever the worker cycle reaches it, then evaluates the
 * configured execution policy of each active agent record individually.
 */
final class ScheduledAgentRunnerJob implements IJob {

	private const SETTINGS_GROUP = 'agent';
	private const DEFAULT_PRIORITY = 1;
	private const MAX_ERROR_DETAILS = 5;
	private const EXECUTION_LOCK_TTL_SECONDS = 3600;
	private const LOCK_PREFIX = 'missionbay.scheduled_agent.lock.';
	private const HOLD_PREFIX = 'missionbay.scheduled_agent.hold.';

	private ?array $missionbayIliasConf = null;

	/**
	 * @var array<string,IJobExecutionPolicy>|null
	 */
	private ?array $policiesById = null;

	public function __construct(
		private readonly IAgentConfigurationService $agentConfigurations,
		private readonly IClassMap $classMap,
		private readonly IConfiguration $configuration,
		private readonly IAgentExecutionService $agentExecutionService,
		private readonly IAgentSuspensionRepository $suspensionRepository,
		private readonly IStateStore $stateStore
	) {}

	public static function getName(): string {
		return 'scheduledagentrunnerjob';
	}

	public function isActive() {
		$conf = $this->getMissionbayIliasConf();
		return ((int)($conf['scheduledagentrunnerjob.active'] ?? 0)) === 1;
	}

	public function getPriority() {
		$conf = $this->getMissionbayIliasConf();
		return (int)($conf['scheduledagentrunnerjob.priority'] ?? self::DEFAULT_PRIORITY);
	}

	public function go() {
		$stats = [
			'total' => 0,
			'active' => 0,
			'disabled' => 0,
			'due' => 0,
			'ran' => 0,
			'pending' => 0,
			'skipped' => 0,
			'errors' => 0
		];
		$errors = [];

		try {
			$group = $this->agentConfigurations->getAgents();
		}
		catch (Throwable $e) {
			return 'Scheduled agent runner failed: ' . $e->getMessage();
		}

		if (!is_array($group) || $group === []) {
			return $this->formatResult($stats, $errors);
		}

		foreach ($group as $agentId => $settings) {
			$stats['total']++;
			$agentId = $this->normalizeTechnicalKey((string)$agentId);
			if ($agentId === '') {
				$stats['errors']++;
				$this->addError($errors, 'Skipped scheduled agent with empty id.');
				continue;
			}
			if (!is_array($settings)) {
				$stats['errors']++;
				$this->addError($errors, 'Scheduled agent is not an array: ' . $agentId);
				continue;
			}
			if (!$this->isEnabled($settings)) {
				$stats['disabled']++;
				continue;
			}
			$stats['active']++;

			$lockToken = null;
			try {
				$lockToken = $this->acquireAgentLock($agentId);
				if ($lockToken === null) {
					$stats['skipped']++;
					continue;
				}
				if ($this->getPendingReview($agentId) !== null) {
					$stats['pending']++;
					continue;
				}
				if ($this->hasHeldRun($agentId)) {
					$stats['skipped']++;
					$this->addError($errors, 'Scheduled agent requires operator review before restart: ' . $agentId);
					continue;
				}

				$policy = $this->getPolicyForAgent($agentId, $settings, $errors);
				if ($policy === null) {
					$stats['errors']++;
					continue;
				}
				$jobName = $this->buildAgentJobName($agentId);
				$policy->setData($this->getPolicyDataForAgent($agentId, $settings));
				if (!$policy->shouldRun($jobName)) {
					$stats['skipped']++;
					continue;
				}
				$stats['due']++;
				// Reserve this run before possible external mutations.
				$this->setHeldRun($agentId);
				$result = $this->executeAgent($agentId, $settings, null);
				$status = $this->resultStatus($result);
				if ($status === AgentExecutionStatus::COMPLETED) {
					$policy->markRun($jobName);
					$this->clearHeldRun($agentId);
					$stats['ran']++;
				} elseif (AgentExecutionStatus::isSuspended($status)) {
					if ($this->getPendingReview($agentId) === null) {
						throw new \RuntimeException('Agent reported a suspension that was not persisted.');
					}
					$stats['pending']++;
				} else {
					throw new \RuntimeException('Agent did not complete successfully (status: ' . $status . ').');
				}
			}
			catch (Throwable $e) {
				$stats['errors']++;
				$this->addError($errors, 'Scheduled agent execution failed for "' . $agentId . '": ' . $e->getMessage());
			}
			finally {
				if ($lockToken !== null) {
					$this->releaseAgentLock($agentId, $lockToken);
				}
			}
		}

		return $this->formatResult($stats, $errors);
	}

	/** Returns only the existing authoritative suspended execution, never a new prompt. */
	public function getPendingReview(string $agentId): ?AgentSuspensionState {
		return $this->suspensionRepository->findPending($this->buildSuspensionScope($agentId));
	}

	/**
	 * Continues an existing scheduled execution. The caller must authorize the
	 * administrator before invoking this method; it never accepts actor data.
	 */
	public function resumePendingReview(string $agentId, AgentResume $resume): AgentExecutionResult {
		$agentId = $this->normalizeTechnicalKey($agentId);
		$settings = $this->agentConfigurations->getAgent($agentId);
		if (!is_array($settings) || !$this->isEnabled($settings)) {
			throw new \RuntimeException('Scheduled agent is missing or disabled.');
		}
		$lockToken = $this->acquireAgentLock($agentId);
		if ($lockToken === null) {
			throw new \RuntimeException('Scheduled agent execution is already in progress.');
		}
		try {
			$pending = $this->getPendingReview($agentId);
			if ($pending === null || !hash_equals($pending->getResumeHandle(), $resume->getResumeHandle())) {
				throw new \RuntimeException('Scheduled agent approval is unavailable or expired.');
			}
			$result = $this->executeAgent($agentId, $settings, $resume);
			if ($this->resultStatus($result) === AgentExecutionStatus::COMPLETED) {
				$errors = [];
				$policy = $this->getPolicyForAgent($agentId, $settings, $errors);
				if ($policy === null) {
					throw new \RuntimeException('Scheduled agent timing policy is missing after resume.');
				}
				$policy->setData($this->getPolicyDataForAgent($agentId, $settings));
				$policy->markRun($this->buildAgentJobName($agentId));
				$this->clearHeldRun($agentId);
			}
			return $result;
		}
		finally {
			$this->releaseAgentLock($agentId, $lockToken);
		}
	}


	/** Prevents reruns of a partially completed flow after its suspension expires. */
	public function hasHeldRun(string $agentId): bool {
		return $this->stateStore->has(self::HOLD_PREFIX . $this->normalizeTechnicalKey($agentId));
	}

	/** Explicit operator rearm is allowed only after the real suspension has expired. */
	public function resetExpiredRun(string $agentId): void {
		$agentId = $this->normalizeTechnicalKey($agentId);
		if ($agentId === '' || !$this->agentConfigurations->hasAgent($agentId)) {
			throw new \RuntimeException('Scheduled agent no longer exists.');
		}
		$lockToken = $this->acquireAgentLock($agentId);
		if ($lockToken === null) {
			throw new \RuntimeException('Scheduled agent execution is in progress.');
		}
		try {
			if ($this->getPendingReview($agentId) !== null) {
				throw new \RuntimeException('A live suspension must be reviewed, not reset.');
			}
			$this->clearHeldRun($agentId);
		} finally {
			$this->releaseAgentLock($agentId, $lockToken);
		}
	}

	private function setHeldRun(string $agentId): void {
		$this->stateStore->set(self::HOLD_PREFIX . $agentId, gmdate('c'));
		$this->stateStore->flush();
	}

	private function clearHeldRun(string $agentId): void {
		$this->stateStore->delete(self::HOLD_PREFIX . $agentId);
		$this->stateStore->flush();
	}

	private function executeAgent(string $agentId, array $settings, ?AgentResume $resume): AgentExecutionResult {
		$inputs = $this->buildAgentInputs($settings);
		if ($resume !== null) {
			$inputs['resume'] = $resume->toArray();
		}
		return $this->agentExecutionService->execute(new AgentExecutionRequest(
			$settings,
			$inputs,
			$this->buildAgentContextVars($agentId, $settings)
		));
	}

	private function resultStatus(AgentExecutionResult $result): string {
		$agentResult = $result->getAgentResult();
		if ($agentResult === null) {
			return 'missing_agent_result';
		}
		return $agentResult->getStatus();
	}

	private function buildSuspensionScope(string $agentId): string {
		$context = $this->buildAgentContextVars($agentId, []);
		return AgentSuspensionScope::forConversation(
			$context['conversation_channel_id'],
			$context['conversation_id'],
			$context['agent_suspension_subject']
		);
	}

	private function acquireAgentLock(string $agentId): ?string {
		$token = bin2hex(random_bytes(16));
		if (!$this->stateStore->setIfNotExists(self::LOCK_PREFIX . $agentId, $token, self::EXECUTION_LOCK_TTL_SECONDS)) {
			return null;
		}
		$this->stateStore->flush();
		return $token;
	}

	private function releaseAgentLock(string $agentId, string $token): void {
		$key = self::LOCK_PREFIX . $agentId;
		if ($this->stateStore->get($key) === $token) {
			$this->stateStore->delete($key);
			$this->stateStore->flush();
		}
	}

	private function getMissionbayIliasConf(): array {
		if ($this->missionbayIliasConf === null) {
			$this->missionbayIliasConf = (array)$this->configuration->get('job');
		}
		return $this->missionbayIliasConf;
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function isEnabled(array $settings): bool {
		if (!array_key_exists('enabled', $settings)) {
			return true;
		}

		return $this->toBool($settings['enabled']);
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function getPolicyForAgent(string $agentId, array $settings, array &$errors): ?IJobExecutionPolicy {
		$definition = is_array($settings['policy'] ?? null) ? $settings['policy'] : [];
		$policyId = $this->normalizeTechnicalKey((string)($definition['policy'] ?? ''));

		if ($policyId === '') {
			$this->addError($errors, 'Scheduled agent has no timing policy: ' . $agentId);
			return null;
		}

		$policies = $this->getPoliciesById();

		if (!isset($policies[$policyId])) {
			$this->addError($errors, 'Scheduled agent uses unknown timing policy "' . $policyId . '": ' . $agentId);
			return null;
		}

		return $policies[$policyId];
	}

	/**
	 * @return array<string,IJobExecutionPolicy>
	 */
	private function getPoliciesById(): array {
		if ($this->policiesById !== null) {
			return $this->policiesById;
		}

		$rows = [];
		$policies = $this->classMap->getInstancesByInterface(IJobExecutionPolicy::class);

		foreach ($policies as $policy) {
			if (!$policy instanceof IJobExecutionPolicy) {
				continue;
			}

			$id = $this->normalizeTechnicalKey((string)$policy::getName());

			if ($id === '') {
				continue;
			}

			$rows[$id] = $policy;
		}

		$this->policiesById = $rows;

		return $rows;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function getPolicyDataForAgent(string $agentId, array $settings): array {
		$definition = is_array($settings['policy'] ?? null) ? $settings['policy'] : [];
		$data = is_array($definition['data'] ?? null) ? $definition['data'] : [];

		if (trim((string)($data['id'] ?? '')) === '') {
			$data['id'] = $agentId;
		}

		return $data;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function buildAgentInputs(array $settings): array {
		$userPrompt = $this->normalizeTextBlock((string)($settings['user_prompt'] ?? ''));

		return [
			'system' => $this->normalizeTextBlock((string)($settings['system_prompt'] ?? '')),
			'prompt' => $userPrompt,
			'mode' => 'scheduled_agent'
		];
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function buildAgentContextVars(string $agentId, array $settings): array {
		$policy = is_array($settings['policy'] ?? null) ? $settings['policy'] : [];

		return [
			'conversation_channel_id' => 'scheduled-agent:' . $agentId,
			'conversation_id' => 'scheduled-run:' . $agentId,
			'agent_suspension_subject' => 'scheduled-agent:' . $agentId,
			'scheduled_agent_id' => $agentId,
			'agent_id' => $agentId,
			'agent_config_group' => 'agent',
			'scheduled_agent_label' => trim((string)($settings['label'] ?? '')),
			'scheduled_agent_config' => $settings,
			'scheduled_agent_policy' => $policy
		];
	}

	private function buildAgentJobName(string $agentId): string {
		return self::getName() . '.' . $agentId;
	}

	/**
	 * @param array<string,int> $stats
	 * @param array<int,string> $errors
	 */
	private function formatResult(array $stats, array $errors): string {
		$message = 'Scheduled agents done'
			. ' - total:' . (int)$stats['total']
			. ' active:' . (int)$stats['active']
			. ' due:' . (int)$stats['due']
			. ' ran:' . (int)$stats['ran']
			. ' pending:' . (int)$stats['pending']
			. ' skipped:' . (int)$stats['skipped']
			. ' disabled:' . (int)$stats['disabled']
			. ' errors:' . (int)$stats['errors'];

		if ($errors !== []) {
			$message .= ' - ' . implode(' | ', $errors);
		}

		return $message;
	}

	/**
	 * @param array<int,string> $errors
	 */
	private function addError(array &$errors, string $error): void {
		if (count($errors) >= self::MAX_ERROR_DETAILS) {
			return;
		}

		$errors[] = $error;
	}

	private function normalizeTextBlock(string $value): string {
		return str_replace(["\r\n", "\r"], "\n", $value);
	}

	private function normalizeTechnicalKey(string $value): string {
		$value = strtolower(trim($value));

		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value === 1;
		}

		$value = strtolower(trim((string)$value));

		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}

}

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

namespace MissionBay\Job;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use Base3\Worker\Api\IJob;
use Base3\Worker\Api\IJobExecutionPolicy;
use MissionBay\Api\IAgentExecutionService;
use Throwable;

/**
 * ScheduledAgentRunnerJob
 *
 * Always-active dispatcher job for scheduled MissionBay agents.
 *
 * The job itself is intentionally not policy controlled. It is expected to run
 * whenever the worker cycle reaches it, then evaluates the configured execution
 * policy of each active scheduled-agent record individually.
 */
final class ScheduledAgentRunnerJob implements IJob {

	private const SETTINGS_GROUP = 'scheduled-agent';
	private const DEFAULT_PRIORITY = 50;
	private const MAX_ERROR_DETAILS = 5;

	/**
	 * @var array<string,IJobExecutionPolicy>|null
	 */
	private ?array $policiesById = null;

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		private readonly IAgentExecutionService $agentExecutionService
	) {}

	public static function getName(): string {
		return 'scheduledagentrunnerjob';
	}

	public function isActive() {
		return true;
	}

	public function getPriority() {
		return self::DEFAULT_PRIORITY;
	}

	public function go() {
		$stats = [
			'total' => 0,
			'active' => 0,
			'disabled' => 0,
			'due' => 0,
			'ran' => 0,
			'skipped' => 0,
			'errors' => 0
		];
		$errors = [];

		try {
			$group = $this->settingsStore->getGroup(self::SETTINGS_GROUP);
		}
		catch (Throwable $e) {
			return 'Scheduled agent runner failed: ' . $e->getMessage();
		}

		if (!is_array($group) || $group === []) {
			return 'Scheduled agents done - total:0 active:0 due:0 ran:0 skipped:0 disabled:0 errors:0';
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

			try {
				$policy = $this->getPolicyForAgent($agentId, $settings, $errors);
			}
			catch (Throwable $e) {
				$stats['errors']++;
				$this->addError($errors, 'Timing policies could not be loaded: ' . $e->getMessage());
				continue;
			}

			if ($policy === null) {
				$stats['errors']++;
				continue;
			}

			$jobName = $this->buildAgentJobName($agentId);

			try {
				$policy->setData($this->getPolicyDataForAgent($agentId, $settings));

				if (!$policy->shouldRun($jobName)) {
					$stats['skipped']++;
					continue;
				}
			}
			catch (Throwable $e) {
				$stats['errors']++;
				$this->addError($errors, 'Policy check failed for scheduled agent "' . $agentId . '": ' . $e->getMessage());
				continue;
			}

			$stats['due']++;

			try {
				$this->agentExecutionService->run(
					$settings,
					$this->buildAgentInputs($settings),
					$this->buildAgentContextVars($agentId, $settings)
				);

				$policy->markRun($jobName);
				$stats['ran']++;
			}
			catch (Throwable $e) {
				$stats['errors']++;
				$this->addError($errors, 'Scheduled agent execution failed for "' . $agentId . '": ' . $e->getMessage());
			}
		}

		return $this->formatResult($stats, $errors);
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
			'user' => $userPrompt,
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
			'scheduled_agent_id' => $agentId,
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

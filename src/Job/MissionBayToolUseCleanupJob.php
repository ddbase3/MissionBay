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

use Base3\Configuration\Api\IConfiguration;
use Base3\Database\Api\IDatabase;
use Base3\Worker\Api\IPolicyControlledJob;
use Base3\Worker\Policy\PolicyControlledJobTrait;

/**
 * MissionBayToolUseCleanupJob
 *
 * Deletes MissionBay tool-use audit rows 24 hours after their last update.
 * The existing created_at index is used as an additional candidate bound.
 */
final class MissionBayToolUseCleanupJob implements IPolicyControlledJob {

	use PolicyControlledJobTrait;

	private const TABLE = 'base3_missionbay_tooluse';
	private const RETENTION_HOURS = 24;
	private const DELETE_BATCH = 100000;
	private const DEFAULT_ACTIVE = 1;
	private const DEFAULT_PRIORITY = 1;

	private ?array $jobConfiguration = null;

	public function __construct(
		private readonly IDatabase $database,
		private readonly IConfiguration $configuration
	) {}

	public static function getName(): string {
		return 'missionbaytoolusecleanupjob';
	}

	public function isActive() {
		$configuration = $this->getJobConfiguration();
		return ((int)($configuration['missionbaytoolusecleanupjob.active'] ?? self::DEFAULT_ACTIVE)) === 1;
	}

	public function getPriority() {
		$configuration = $this->getJobConfiguration();
		return (int)($configuration['missionbaytoolusecleanupjob.priority'] ?? self::DEFAULT_PRIORITY);
	}

	public function getPolicyDefinition(): array {
		return [
			'policy' => 'dailywindowjobpolicy',
			'data' => [
				'from' => '02:00',
				'to' => '04:00'
			]
		];
	}

	public function go() {
		$this->database->connect();
		if(!$this->database->connected()) {
			return 'DB not connected';
		}

		if(!$this->toolUseTableExists()) {
			return 'Skip (' . self::TABLE . ' does not exist)';
		}

		$cutoff = $this->cutoffUtcSqlString();
		$this->deleteOldToolUse($cutoff);
		$this->markRun();

		return 'Tool-use cleanup done (cutoff: ' . $cutoff . ', limit: ' . self::DELETE_BATCH . ')';
	}

	private function getJobConfiguration(): array {
		if($this->jobConfiguration === null) {
			$this->jobConfiguration = (array)$this->configuration->get('job');
		}

		return $this->jobConfiguration;
	}

	private function deleteOldToolUse(string $cutoff): void {
		$escapedCutoff = $this->database->escape($cutoff);

		$this->database->nonQuery(
			"DELETE FROM `" . self::TABLE . "`
			WHERE `created_at` < '" . $escapedCutoff . "'
				AND `updated_at` < '" . $escapedCutoff . "'
			ORDER BY `created_at` ASC, `id` ASC
			LIMIT " . self::DELETE_BATCH
		);
	}

	private function cutoffUtcSqlString(): string {
		return gmdate('Y-m-d H:i:s', time() - (self::RETENTION_HOURS * 3600));
	}

	private function toolUseTableExists(): bool {
		$row = $this->database->singleQuery("SHOW TABLES LIKE '" . self::TABLE . "'");
		return !empty($row);
	}
}

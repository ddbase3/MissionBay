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

namespace Base3\MissionBay\Diagnostics;

use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Diagnostics\Api\IDiagnosticCheck;
use Base3\CoreFoundation\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\Core\Diagnostics\Scope\PrivacyDiagnosticScope;
use Base3\MissionBay\ToolUse\ToolUseRetention;
use RuntimeException;

/** Read-only check of the existing 24-hour MissionBay tool-use cleanup policy. */
final class ToolUseLogRetentionDiagnosticCheck implements IDiagnosticCheck {

	public function __construct(private readonly IDatabase $database) {}

	public static function getName(): string {
		return 'tooluselogretentiondiagnosticcheck';
	}

	public function getScope(): string {
		return PrivacyDiagnosticScope::getName();
	}

	public function run(): DiagnosticCheckResult {
		$this->database->connect();
		if(!$this->database->connected()) {
			throw new RuntimeException('Tool-use diagnostic database unavailable.');
		}

		if(!ToolUseRetention::tableExists($this->database)) {
			return new DiagnosticCheckResult(DiagnosticCheckResult::NOT_APPLICABLE, 'not_applicable');
		}

		$cutoff = ToolUseRetention::cutoffUtc(ToolUseRetention::DIAGNOSTIC_HOURS);
		$count = $this->database->scalarQuery(
			'SELECT COUNT(*) FROM `' . ToolUseRetention::TABLE . '` WHERE '
			. ToolUseRetention::expiredWhere($this->database, $cutoff)
		);
		if(!is_numeric($count) || (float)$count < 0 || (float)$count > PHP_INT_MAX) {
			throw new RuntimeException('Tool-use diagnostic returned an invalid count.');
		}

		$overdue = (int)$count;
		return new DiagnosticCheckResult(
			$overdue === 0 ? DiagnosticCheckResult::PASSED : DiagnosticCheckResult::FAILED,
			$overdue === 0 ? 'passed' : 'failed',
			['hours' => ToolUseRetention::DIAGNOSTIC_HOURS, 'count' => $overdue],
			$overdue > 0 ? 'remediation' : '',
			['overdue' => $overdue, 'thresholdHours' => ToolUseRetention::DIAGNOSTIC_HOURS]
		);
	}

}

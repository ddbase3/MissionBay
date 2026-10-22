<?php declare(strict_types=1);

namespace MissionBay\Diagnostics;

use Base3\Database\Api\IDatabase;
use Base3\Diagnostics\Api\IDiagnosticCheck;
use Base3\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\Diagnostics\Scope\PrivacyDiagnosticScope;
use MissionBay\ToolUse\ToolUseRetention;
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

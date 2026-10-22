<?php declare(strict_types=1);

namespace MissionBay\ToolUse;

use Base3\Database\Api\IDatabase;

/** Shared retention semantics for the tool-use audit table and its diagnostic. */
final class ToolUseRetention {

	public const TABLE = 'base3_missionbay_tooluse';
	public const CLEANUP_HOURS = 24;
	public const DIAGNOSTIC_HOURS = 48;

	/** Returns the UTC storage cutoff in the timestamp format used by tool-use rows. */
	public static function cutoffUtc(int $hours): string {
		return gmdate('Y-m-d H:i:s', time() - ($hours * 3600));
	}

	/** Checks for the existing domain table without trying to create it. */
	public static function tableExists(IDatabase $database): bool {
		return !empty($database->singleQuery("SHOW TABLES LIKE '" . self::TABLE . "'"));
	}

	/** Both timestamps must be before the cutoff; a recently updated row stays live. */
	public static function expiredWhere(IDatabase $database, string $cutoffUtc): string {
		$cutoff = $database->escape($cutoffUtc);
		return "`created_at` < '" . $cutoff . "' AND `updated_at` < '" . $cutoff . "'";
	}

}

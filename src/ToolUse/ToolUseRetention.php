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

namespace Base3\MissionBay\ToolUse;

use Base3\CoreFoundation\Database\Api\IDatabase;

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

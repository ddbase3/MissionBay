<?php declare(strict_types=1);

/***********************************************************************
 * This legacy script path is intentionally disabled.
 *
 * Remote MCP connections are tested through the Agent Component Preset test
 * service. Loading this file from Composer or another PHP file has no side
 * effects.
 **********************************************************************/

if(realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
	if(defined('STDERR')) {
		fwrite(STDERR, "This MissionBay script entry point is disabled. Use the preset test action instead.\n");
	}
	exit(126);
}

return;

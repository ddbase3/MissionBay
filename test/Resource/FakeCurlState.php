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

namespace Base3\MissionBay\Resource;

final class FakeCurlState {

		public static int $errno = 0;
		public static string $error = '';
		public static int $httpCode = 200;
		public static mixed $execResult = null;

		private static array $options = [];

		public static function reset(): void {
			self::$errno = 0;
			self::$error = '';
			self::$httpCode = 200;
			self::$execResult = null;
			self::$options = [];
		}

		public static function setOpt(int $opt, mixed $value): void {
			self::$options[$opt] = $value;
		}

		public static function getOpt(int $opt): mixed {
			return self::$options[$opt] ?? null;
		}
	}

<?php declare(strict_types=1);

namespace MissionBay\Resource;

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

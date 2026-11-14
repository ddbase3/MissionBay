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

namespace Base3\MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\MissionBay\Resource\LoggerResource;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\CoreFoundation\Logger\Api\ILogger;

final class LoggerResourceTestDouble2 implements ILogger  {
			public array $calls = [];

			public function emergency(string|\Stringable $message, array $context = []): void {
				$this->logLevel(ILogger::EMERGENCY, $message, $context);
			}

			public function alert(string|\Stringable $message, array $context = []): void {
				$this->logLevel(ILogger::ALERT, $message, $context);
			}

			public function critical(string|\Stringable $message, array $context = []): void {
				$this->logLevel(ILogger::CRITICAL, $message, $context);
			}

			public function error(string|\Stringable $message, array $context = []): void {
				$this->logLevel(ILogger::ERROR, $message, $context);
			}

			public function warning(string|\Stringable $message, array $context = []): void {
				$this->logLevel(ILogger::WARNING, $message, $context);
			}

			public function notice(string|\Stringable $message, array $context = []): void {
				$this->logLevel(ILogger::NOTICE, $message, $context);
			}

			public function info(string|\Stringable $message, array $context = []): void {
				$this->logLevel(ILogger::INFO, $message, $context);
			}

			public function debug(string|\Stringable $message, array $context = []): void {
				$this->logLevel(ILogger::DEBUG, $message, $context);
			}

			public function logLevel(string $level, string|\Stringable $message, array $context = []): void {
				$this->calls[] = [
					'level' => $level,
					'message' => (string)$message,
					'context' => $context,
				];
			}

			public function log(string $scope, string $log, ?int $timestamp = null): bool {
				$this->calls[] = [
					'legacy' => true,
					'scope' => $scope,
					'log' => $log,
					'timestamp' => $timestamp,
				];
				return true;
			}

			public function getScopes(): array {
				return ['a', 'b'];
			}

			public function getNumOfScopes() {
				return 2;
			}

			public function getLogs(string $scope, int $num = 50, bool $reverse = true): array {
				return [['scope' => $scope, 'num' => $num, 'reverse' => $reverse]];
			}
		}

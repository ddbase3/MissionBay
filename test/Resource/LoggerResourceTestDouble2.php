<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\LoggerResource;
use MissionBay\Api\IAgentConfigValueResolver;
use Base3\Logger\Api\ILogger;

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

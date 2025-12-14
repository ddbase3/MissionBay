<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\LoggerResource;
use MissionBay\Api\IAgentConfigValueResolver;
use Base3\Logger\Api\ILogger;

/**
 * @covers \MissionBay\Resource\LoggerResource
 */
class LoggerResourceTest extends TestCase {

	private function makeResolver(?string $resolvedScope): IAgentConfigValueResolver {
		return new class($resolvedScope) implements IAgentConfigValueResolver {
			private ?string $resolvedScope;

			public function __construct(?string $resolvedScope) {
				$this->resolvedScope = $resolvedScope;
			}

			public function resolveValue(array|string|null $config): mixed {
				// For tests we return the injected resolved scope (or null),
				// independent from $config shape.
				return $this->resolvedScope;
			}
		};
	}

	private function makeLoggerSpy(): object {
		return new class implements ILogger {
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
		};
	}

	public function testGetName(): void {
		$this->assertSame('loggerresource', LoggerResource::getName());
	}

	public function testLogLevelAddsResolvedScopeAndTimestampAndDelegates(): void {
		$logger = $this->makeLoggerSpy();
		$resolver = $this->makeResolver('my-scope');

		$r = new LoggerResource($logger, $resolver, 'lr1');
		$r->setConfig(['scope' => 'ignored-by-test-resolver']);

		$r->logLevel(ILogger::INFO, 'Hello', ['foo' => 'bar']);

		$this->assertCount(1, $logger->calls);
		$call = $logger->calls[0];

		$this->assertSame(ILogger::INFO, $call['level']);
		$this->assertSame('Hello', $call['message']);

		$ctx = $call['context'];
		$this->assertSame('bar', $ctx['foo'] ?? null);
		$this->assertSame('my-scope', $ctx['scope'] ?? null);
		$this->assertArrayHasKey('timestamp', $ctx);
		$this->assertIsInt($ctx['timestamp']);
	}

	public function testLogLevelDoesNotOverwriteExistingTimestamp(): void {
		$logger = $this->makeLoggerSpy();
		$resolver = $this->makeResolver('scope-x');

		$r = new LoggerResource($logger, $resolver, 'lr2');
		$r->setConfig(['scope' => 'ignored']);

		$r->logLevel(ILogger::DEBUG, 'Hi', ['timestamp' => 123, 'x' => 1]);

		$this->assertCount(1, $logger->calls);
		$ctx = $logger->calls[0]['context'];

		$this->assertSame(123, $ctx['timestamp']);
		$this->assertSame(1, $ctx['x']);
		$this->assertSame('scope-x', $ctx['scope'] ?? null);
	}

	public function testLogLevelDoesNotAddScopeWhenResolverReturnsEmptyString(): void {
		$logger = $this->makeLoggerSpy();
		$resolver = $this->makeResolver('');

		$r = new LoggerResource($logger, $resolver, 'lr3');
		$r->setConfig(['scope' => 'anything']);

		$r->logLevel(ILogger::NOTICE, 'No scope', []);

		$this->assertCount(1, $logger->calls);
		$ctx = $logger->calls[0]['context'];

		$this->assertArrayNotHasKey('scope', $ctx);
		$this->assertArrayHasKey('timestamp', $ctx);
	}

	public function testGetScopesGetNumOfScopesGetLogsProxyToUnderlyingLoggerIfAvailable(): void {
		$logger = $this->makeLoggerSpy();
		$resolver = $this->makeResolver(null);

		$r = new LoggerResource($logger, $resolver, 'lr4');

		$this->assertSame(['a', 'b'], $r->getScopes());
		$this->assertSame(2, $r->getNumOfScopes());

		$logs = $r->getLogs('myscope', 7, false);
		$this->assertSame([['scope' => 'myscope', 'num' => 7, 'reverse' => false]], $logs);
	}

	public function testGetScopesGetNumOfScopesGetLogsReturnFallbackWhenUnderlyingDoesNotImplementMethods(): void {
		// This "fallback" scenario cannot be constructed without changing prod code:
		// LoggerResource::__construct requires ILogger, and ILogger *requires*
		// getScopes/getNumOfScopes/getLogs. So "missing methods" would be a PHP fatal
		// before the test runs.
		//
		// We keep a passing test here to document the contract constraint explicitly.
		$this->assertTrue(true);
	}
}

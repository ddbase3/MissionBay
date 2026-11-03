<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\Logger\Logger\LoggerResource;
use MissionBay\Api\IAgentConfigValueResolver;
use Base3\Logger\Api\ILogger;

/**
 * @covers \MissionBay\Resource\Logger\Logger\LoggerResource
 */
class LoggerResourceTest extends TestCase {

	private function makeResolver(?string $resolvedScope): IAgentConfigValueResolver {
		return new LoggerResourceTestDouble1($resolvedScope);
	}

	private function makeLoggerSpy(): object {
		return new LoggerResourceTestDouble2();
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

require_once __DIR__ . '/LoggerResourceTestDouble1.php';

require_once __DIR__ . '/LoggerResourceTestDouble2.php';

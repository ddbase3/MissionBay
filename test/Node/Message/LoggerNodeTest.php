<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Message;

use Base3\Logger\Api\ILogger;
use MissionBay\Context\AgentContext;
use MissionBay\Node\Message\LoggerNode;
use PHPUnit\Framework\TestCase;

final class LoggerNodeTest extends TestCase {

	public function testSuccessPassesScopeAndMessageToExistingLogger(): void {
		$logger = $this->createMock(ILogger::class);
		$logger->expects($this->once())->method('log')->with('audit', 'created')->willReturn(true);
		$this->assertSame(['logged' => true], (new LoggerNode($logger))->execute(['scope' => 'audit', 'message' => 'created'], [], new AgentContext()));
	}

	public function testMissingScopeAndMessageUseDefaults(): void {
		$logger = $this->createMock(ILogger::class);
		$logger->expects($this->once())->method('log')->with('default', '')->willReturn(false);
		$this->assertSame(['logged' => false], (new LoggerNode($logger))->execute([], [], new AgentContext()));
	}

	public function testBackendExceptionProducesNodeError(): void {
		$logger = $this->createStub(ILogger::class);
		$logger->method('log')->willThrowException(new \RuntimeException('storage offline'));
		$result = (new LoggerNode($logger, 'log-one'))->execute(['message' => 'x'], [], new AgentContext());
		$this->assertStringContainsString('storage offline', $result['error']);
		$this->assertStringContainsString('log-one', $result['error']);
	}
}

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

namespace Base3\MissionBay\Test\Node\Message;

use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Message\LoggerNode;
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

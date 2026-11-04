<?php declare(strict_types=1);

namespace MissionBay\Test\Exception;

use MissionBay\Exception\AgentRunCancelledException;
use PHPUnit\Framework\TestCase;

final class AgentRunCancelledExceptionBehaviorTest extends TestCase {

	public function testDefaultCancellationReasonIsExplicit(): void {
		$exception = new AgentRunCancelledException();
		$this->assertSame('Agent run cancelled by user.', $exception->getMessage());
		$this->assertTrue($exception instanceof \RuntimeException);
	}

	public function testCallerCanSupplyMoreSpecificCancellationReason(): void {
		$exception = new AgentRunCancelledException('Stopped during tool phase.');
		$this->assertSame('Stopped during tool phase.', $exception->getMessage());
	}
}

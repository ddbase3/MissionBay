<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Mcp;

use MissionBay\Dto\Mcp\McpProfileAuthorizationResult;
use PHPUnit\Framework\TestCase;

final class McpProfileAuthorizationResultBehaviorTest extends TestCase {

	public function testAuthorizedResultDoesNotContainFailureReason(): void {
		$result = McpProfileAuthorizationResult::success('bearer');
		$this->assertTrue($result->isAuthorized());
		$this->assertSame(200, $result->getStatusCode());
		$this->assertSame('bearer', $result->getMode());
		$this->assertSame('', $result->getFailureCode());
	}

	public function testDeniedResultDoesNotExposeAuthenticationMode(): void {
		$result = McpProfileAuthorizationResult::failure(403, 'forbidden');
		$this->assertFalse($result->isAuthorized());
		$this->assertSame(403, $result->getStatusCode());
		$this->assertSame('', $result->getMode());
		$this->assertSame('forbidden', $result->getFailureCode());
	}
}

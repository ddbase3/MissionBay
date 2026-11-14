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

namespace Base3\MissionBay\Test\Dto\Mcp;

use Base3\MissionBay\Dto\Mcp\McpProfileAuthorizationResult;
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

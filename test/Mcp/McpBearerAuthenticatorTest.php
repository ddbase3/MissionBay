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

namespace Base3\MissionBay\Test\Mcp;

use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Mcp\McpBearerAuthenticator;
use PHPUnit\Framework\TestCase;

final class McpBearerAuthenticatorTest extends TestCase {

	public function testRejectsUnconfiguredAndMissingBearerToken(): void {
		$original = $_SERVER ?? null;
		try {
			$_SERVER = [];
			$auth = $this->authenticator();
			$this->assertFalse($auth->isAuthorized(['id' => 'profile', 'token' => '']));
			$this->assertFalse($auth->isAuthorized(['id' => 'profile', 'token' => 'secret']));
			$_SERVER['HTTP_AUTHORIZATION'] = 'Basic abc';
			$this->assertFalse($auth->isAuthorized(['id' => 'profile', 'token' => 'secret']));
		} finally {
			$this->restoreServer($original);
		}
	}

	public function testAcceptsExactBearerTokenWithWhitespaceAndCaseInsensitiveScheme(): void {
		$original = $_SERVER ?? null;
		try {
			$_SERVER = ['HTTP_AUTHORIZATION' => '  BEARER precise-key  '];
			$this->assertTrue($this->authenticator()->isAuthorized(['id' => 'profile', 'token' => ' precise-key ']));
			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer precise-key-suffix';
			$this->assertFalse($this->authenticator()->isAuthorized(['id' => 'profile', 'token' => 'precise-key']));
		} finally {
			$this->restoreServer($original);
		}
	}

	public function testRedirectedAuthorizationHeaderIsRecognized(): void {
		$original = $_SERVER ?? null;
		try {
			$_SERVER = ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirected'];
			$this->assertTrue($this->authenticator()->isAuthorized(['token' => 'redirected']));
		} finally {
			$this->restoreServer($original);
		}
	}

	private function authenticator(): McpBearerAuthenticator {
		return new McpBearerAuthenticator($this->createStub(ILogger::class));
	}

	private function restoreServer(?array $original): void {
		if ($original === null) {
			unset($_SERVER);
		} else {
			$_SERVER = $original;
		}
	}
}

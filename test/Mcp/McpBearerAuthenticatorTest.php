<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use Base3\Logger\Api\ILogger;
use MissionBay\Mcp\McpBearerAuthenticator;
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

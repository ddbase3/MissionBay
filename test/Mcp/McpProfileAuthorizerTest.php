<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use Base3\Api\IRequest;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use CredentialFoundation\Api\ICredentialAccess;
use CredentialFoundation\Dto\CredentialAuthenticationResult;
use CredentialFoundation\Dto\CredentialIdentityResult;
use MissionBay\Mcp\McpProfileAuthorizer;
use MissionBay\Mcp\McpToolProfileRepository;
use PHPUnit\Framework\TestCase;

final class McpProfileAuthorizerTest extends TestCase {

	public function testAcceptsMatchingFixedBearerToken(): void {
		$request = $this->request(['HTTP_AUTHORIZATION' => 'Bearer fixed-secret']);
		$repository = new McpToolProfileRepository(new AuthorizerSettingsStore([]));
		$authorizer = new McpProfileAuthorizer(
			$request,
			$repository,
			$this->createStub(ILogger::class)
		);

		$result = $authorizer->authorize([
			'id' => 'admin',
			'mcp_fixed_bearer_enabled' => true,
			'token' => 'fixed-secret'
		]);

		self::assertTrue($result->isAuthorized());
		self::assertSame('fixed_bearer', $result->getMode());
	}

	public function testAuthorizesCurrentCredentialIdentityForProfileService(): void {
		$request = $this->request(['HTTP_AUTHORIZATION' => 'Bearer personal-key']);
		$repository = new McpToolProfileRepository(new AuthorizerSettingsStore([]));
		$access = $this->createMock(ICredentialAccess::class);
		$access->method('getIdentity')->willReturn(CredentialIdentityResult::success('credential-id', 42));
		$access->expects(self::once())
			->method('authorizeService')
			->with('missionbay:mcp:admin')
			->willReturn(CredentialAuthenticationResult::success(
				'credential-id',
				42,
				'missionbay:mcp:admin'
			));
		$authorizer = new McpProfileAuthorizer(
			$request,
			$repository,
			$this->createStub(ILogger::class),
			$access
		);

		$result = $authorizer->authorize([
			'id' => 'admin',
			'mcp_credential_enabled' => true
		]);

		self::assertTrue($result->isAuthorized());
		self::assertSame('credential', $result->getMode());
	}

	public function testHmacHeadersNeverFallBackToFixedBearer(): void {
		$request = $this->request([
			'HTTP_AUTHORIZATION' => 'Bearer fixed-secret',
			'HTTP_X_BASE3_TIMESTAMP' => '1785140000',
			'HTTP_X_BASE3_NONCE' => 'nonce',
			'HTTP_X_BASE3_SIGNATURE' => str_repeat('a', 64)
		]);
		$repository = new McpToolProfileRepository(new AuthorizerSettingsStore([]));
		$authorizer = new McpProfileAuthorizer(
			$request,
			$repository,
			$this->createStub(ILogger::class)
		);

		$result = $authorizer->authorize([
			'id' => 'admin',
			'mcp_fixed_bearer_enabled' => true,
			'token' => 'fixed-secret'
		]);

		self::assertFalse($result->isAuthorized());
		self::assertSame(401, $result->getStatusCode());
	}

	public function testReturnsForbiddenForMissingServiceGrant(): void {
		$request = $this->request(['HTTP_AUTHORIZATION' => 'Bearer personal-key']);
		$repository = new McpToolProfileRepository(new AuthorizerSettingsStore([]));
		$access = $this->createMock(ICredentialAccess::class);
		$access->method('getIdentity')->willReturn(CredentialIdentityResult::success('credential-id', 42));
		$access->method('authorizeService')->willReturn(CredentialAuthenticationResult::failure(
			CredentialAuthenticationResult::FAILURE_SERVICE_NOT_GRANTED,
			'missionbay:mcp:admin'
		));
		$authorizer = new McpProfileAuthorizer(
			$request,
			$repository,
			$this->createStub(ILogger::class),
			$access
		);

		$result = $authorizer->authorize([
			'id' => 'admin',
			'mcp_credential_enabled' => true
		]);

		self::assertFalse($result->isAuthorized());
		self::assertSame(403, $result->getStatusCode());
	}

	/** @param array<string,mixed> $server */
	private function request(array $server): IRequest {
		$request = $this->createMock(IRequest::class);
		$request->method('server')->willReturnCallback(
			static fn(string $key, mixed $default = null): mixed => $server[$key] ?? $default
		);
		return $request;
	}
}


final class AuthorizerSettingsStore implements ISettingsStore {

	/** @param array<string,array<string,array<string,mixed>>> $data */
	public function __construct(private array $data) {}

	public function get(string $group, string $name, array $default = []): array {
		return $this->data[$group][$name] ?? $default;
	}

	public function set(string $group, string $name, array $settings): void {
		$this->data[$group][$name] = $settings;
	}

	public function has(string $group, string $name): bool {
		return isset($this->data[$group][$name]);
	}

	public function remove(string $group, string $name): void {
		unset($this->data[$group][$name]);
	}

	public function getGroup(string $group): array {
		return $this->data[$group] ?? [];
	}

	public function save(): void {}

	public function reload(): void {}
}

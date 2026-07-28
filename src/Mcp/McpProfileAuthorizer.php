<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Api\IRequest;
use Base3\Logger\Api\ILogger;
use CredentialFoundation\Api\ICredentialAccess;
use CredentialFoundation\Dto\CredentialAuthenticationResult;
use MissionBay\Dto\Mcp\McpProfileAuthorizationResult;

/**
 * Authorizes one MCP profile through a fixed bearer token or the current
 * CredentialFoundation identity established by access control.
 */
final class McpProfileAuthorizer {

	private const LOG_SCOPE = 'missionbay_mcp';

	public function __construct(
		private readonly IRequest $request,
		private readonly McpToolProfileRepository $profileRepository,
		private readonly ILogger $logger,
		private readonly ?ICredentialAccess $credentialAccess = null
	) {}

	/**
	 * @param array<string,mixed> $profile
	 */
	public function authorize(array $profile): McpProfileAuthorizationResult {
		$profileId = trim((string)($profile['id'] ?? ''));
		$hmacRequested = $this->hasHmacHeaders();

		if($hmacRequested) {
			return $this->authorizeCredential($profile, $profileId);
		}

		if($this->profileRepository->isFixedBearerEnabled($profile)
			&& $this->matchesFixedBearerToken($profile)) {
			return McpProfileAuthorizationResult::success('fixed_bearer');
		}

		if($this->profileRepository->isCredentialAccessEnabled($profile)) {
			return $this->authorizeCredential($profile, $profileId);
		}

		$this->logFailure($profileId, 'no_enabled_authentication_method');
		return McpProfileAuthorizationResult::failure(401, 'unauthorized');
	}

	/**
	 * @param array<string,mixed> $profile
	 */
	private function authorizeCredential(array $profile, string $profileId): McpProfileAuthorizationResult {
		if(!$this->profileRepository->isCredentialAccessEnabled($profile)
			|| !$this->credentialAccess instanceof ICredentialAccess) {
			$this->logFailure($profileId, 'credential_access_unavailable');
			return McpProfileAuthorizationResult::failure(401, 'unauthorized');
		}

		$identity = $this->credentialAccess->getIdentity();
		if($identity === null || !$identity->isAuthenticated()) {
			$this->logFailure($profileId, $identity?->getFailureCode() ?: 'missing_credential_identity');
			return McpProfileAuthorizationResult::failure(401, 'unauthorized');
		}

		$serviceId = $this->profileRepository->getCredentialServiceId($profileId);
		$authorization = $this->credentialAccess->authorizeService($serviceId);

		if($authorization->isAuthenticated()) {
			return McpProfileAuthorizationResult::success('credential');
		}

		$failureCode = $authorization->getFailureCode();
		$this->logFailure($profileId, $failureCode);

		$statusCode = in_array($failureCode, [
			CredentialAuthenticationResult::FAILURE_SERVICE_NOT_FOUND,
			CredentialAuthenticationResult::FAILURE_SERVICE_NOT_GRANTED
		], true) ? 403 : 401;

		return McpProfileAuthorizationResult::failure(
			$statusCode,
			$statusCode === 403 ? 'forbidden' : 'unauthorized'
		);
	}

	/**
	 * @param array<string,mixed> $profile
	 */
	private function matchesFixedBearerToken(array $profile): bool {
		$expectedToken = trim((string)($profile['token'] ?? ''));
		$providedToken = $this->getBearerToken();

		if($expectedToken === '' || $providedToken === null) {
			return false;
		}

		return hash_equals($expectedToken, $providedToken);
	}

	private function getBearerToken(): ?string {
		$authorization = trim((string)$this->request->server('HTTP_AUTHORIZATION', ''));
		if($authorization === '') {
			$authorization = trim((string)$this->request->server('REDIRECT_HTTP_AUTHORIZATION', ''));
		}

		if(!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
			return null;
		}

		return $matches[1];
	}

	private function hasHmacHeaders(): bool {
		return trim((string)$this->request->server('HTTP_X_BASE3_TIMESTAMP', '')) !== ''
			|| trim((string)$this->request->server('HTTP_X_BASE3_NONCE', '')) !== ''
			|| trim((string)$this->request->server('HTTP_X_BASE3_SIGNATURE', '')) !== '';
	}

	private function logFailure(string $profileId, string $failureCode): void {
		$this->logger->logLevel(ILogger::WARNING, 'MCP profile authorization failed.', [
			'scope' => self::LOG_SCOPE,
			'profile' => $profileId,
			'failure_code' => $failureCode
		]);
	}
}

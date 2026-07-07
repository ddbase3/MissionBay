<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Logger\Api\ILogger;

/**
 * McpBearerAuthenticator
 *
 * Checks the Authorization bearer token against the token stored in the
 * selected MCP tool profile.
 */
class McpBearerAuthenticator {

	private const LOG_SCOPE = 'missionbay_mcp';

	public function __construct(private readonly ILogger $logger) {}

	public static function getName(): string {
		return 'mcpbearerauthenticator';
	}

	/**
	 * @param array<string,mixed> $profile
	 */
	public function isAuthorized(array $profile): bool {
		$expectedToken = trim((string)($profile['token'] ?? ''));
		$profileId = trim((string)($profile['id'] ?? ''));

		if($expectedToken === '') {
			$this->logger->logLevel(ILogger::WARNING, 'MCP profile has no bearer token configured.', [
				'scope' => self::LOG_SCOPE,
				'profile' => $profileId
			]);
			return false;
		}

		$header = $this->getAuthorizationHeader();

		if(!str_starts_with(strtolower($header), 'bearer ')) {
			$this->logger->logLevel(ILogger::WARNING, 'Missing MCP bearer token.', [
				'scope' => self::LOG_SCOPE,
				'profile' => $profileId
			]);
			return false;
		}

		$providedToken = trim(substr($header, 7));
		$authorized = hash_equals($expectedToken, $providedToken);

		if(!$authorized) {
			$this->logger->logLevel(ILogger::WARNING, 'Invalid MCP bearer token.', [
				'scope' => self::LOG_SCOPE,
				'profile' => $profileId
			]);
		}

		return $authorized;
	}

	private function getAuthorizationHeader(): string {
		$header = $_SERVER['HTTP_AUTHORIZATION']
			?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
			?? '';

		if($header !== '') {
			return trim((string)$header);
		}

		if(function_exists('apache_request_headers')) {
			$headers = apache_request_headers();

			foreach($headers as $name => $value) {
				if(strtolower((string)$name) === 'authorization') {
					return trim((string)$value);
				}
			}
		}

		return '';
	}
}

<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp\Client;

use MissionBay\Api\IMcpTransport;
use MissionBay\Dto\Mcp\McpHttpRequest;
use MissionBay\Dto\Mcp\McpHttpResponse;

/**
 * Streamable HTTP transport for MCP JSON-RPC messages.
 */
final class McpStreamableHttpTransport implements IMcpTransport {

	public static function getName(): string {
		return 'mcpstreamablehttptransport';
	}

	public function send(McpHttpRequest $request): McpHttpResponse {
		if(!function_exists('curl_init')) {
			throw new McpClientException('The PHP cURL extension is required for MCP Streamable HTTP connections.');
		}

		$headers = [];
		foreach($request->getHeaders() as $name => $value) {
			$name = trim((string)$name);
			$value = (string)$value;

			if($name === '') {
				continue;
			}

			if(str_contains($name, "\r") || str_contains($name, "\n")
				|| str_contains($value, "\r") || str_contains($value, "\n")) {
				throw new McpClientException('MCP HTTP headers must not contain line breaks.');
			}

			$headers[] = $name . ': ' . $value;
		}

		$responseHeaders = [];
		$responseBody = '';
		$responseTooLarge = false;
		$statusCode = 0;
		$curl = curl_init($request->getEndpoint());

		if($curl === false) {
			throw new McpClientException('Unable to initialize the MCP HTTP transport.');
		}

		$options = [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $request->getBody(),
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => $request->getConnectTimeout(),
			CURLOPT_TIMEOUT => $request->getRequestTimeout(),
			CURLOPT_SSL_VERIFYPEER => $request->shouldVerifyTls(),
			CURLOPT_SSL_VERIFYHOST => $request->shouldVerifyTls() ? 2 : 0,
			CURLOPT_USERAGENT => 'MissionBay-MCP-Client/1.0',
			CURLOPT_HEADERFUNCTION => static function($curl, string $line) use (&$responseHeaders, &$statusCode): int {
				$length = strlen($line);
				$trimmed = trim($line);

				if($trimmed === '') {
					return $length;
				}

				if(str_starts_with(strtoupper($trimmed), 'HTTP/')) {
					$responseHeaders = [];
					$parts = preg_split('/\s+/', $trimmed);
					$statusCode = isset($parts[1]) ? (int)$parts[1] : 0;
					return $length;
				}

				$separator = strpos($line, ':');
				if($separator === false) {
					return $length;
				}

				$name = strtolower(trim(substr($line, 0, $separator)));
				$value = trim(substr($line, $separator + 1));
				if($name !== '') {
					$responseHeaders[$name] = isset($responseHeaders[$name])
						? $responseHeaders[$name] . ', ' . $value
						: $value;
				}

				return $length;
			},
			CURLOPT_WRITEFUNCTION => static function($curl, string $chunk) use (&$responseBody, &$responseTooLarge, $request): int {
				if(strlen($responseBody) + strlen($chunk) > $request->getMaxResponseBytes()) {
					$responseTooLarge = true;
					return 0;
				}

				$responseBody .= $chunk;
				return strlen($chunk);
			}
		];

		if(defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
			$options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
		}

		if(defined('CURLOPT_NOSIGNAL')) {
			$options[CURLOPT_NOSIGNAL] = true;
		}

		curl_setopt_array($curl, $options);
		$ok = curl_exec($curl);
		$errorNumber = curl_errno($curl);
		$errorMessage = curl_error($curl);
		$infoStatus = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		curl_close($curl);

		if($responseTooLarge) {
			throw new McpClientException(
				'MCP response exceeded the configured size limit of ' . $request->getMaxResponseBytes() . ' bytes.',
				$statusCode > 0 ? $statusCode : $infoStatus
			);
		}

		if($ok === false || $errorNumber !== 0) {
			throw new McpClientException('MCP HTTP request failed: ' . ($errorMessage !== '' ? $errorMessage : 'unknown cURL error.'));
		}

		if($statusCode === 0) {
			$statusCode = $infoStatus;
		}

		return new McpHttpResponse($statusCode, $responseHeaders, $responseBody);
	}
}

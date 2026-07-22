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

namespace MissionBay\Transport;

use AssistantFoundation\Api\IAiProvider;

class OpenAiCompatibleTransport implements IAiProvider {

	/**
	 * @var array<string,mixed>
	 */
	protected array $options = [];

	public static function getName(): string {
		return 'openaicompatibletransport';
	}

	/**
	 * @param array<string,mixed> $options
	 */
	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getOptions(): array {
		return $this->options;
	}

	public function request(string $path, array $payload, array $options = []): array {
		$url = $this->buildUrl($path);
		$method = strtoupper(trim((string)($options['method'] ?? 'POST')));

		if($method === 'GET' && count($payload) > 0) {
			$query = http_build_query($payload);

			if($query !== '') {
				$url .= (str_contains($url, '?') ? '&' : '?') . $query;
			}
		}

		$jsonPayload = null;

		if($method !== 'GET') {
			$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			if($jsonPayload === false) {
				throw new \RuntimeException('Failed to encode OpenAI-compatible transport request payload.');
			}
		}

		$headers = $this->buildHeaders($options);
		$timeout = $this->resolveTimeout($options);
		$connectTimeout = $this->resolveConnectTimeout($options);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);

		$this->applyRequestMethod($ch, $method, $jsonPayload);

		$result = curl_exec($ch);

		if($result === false) {
			$error = curl_error($ch);
			curl_close($ch);
			throw new \RuntimeException('OpenAI-compatible transport request failed: ' . $error);
		}

		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if($httpCode < 200 || $httpCode >= 300) {
			throw new \RuntimeException(
				'OpenAI-compatible transport request failed with status ' . $httpCode . ': ' . (string)$result
			);
		}

		if(trim((string)$result) === '') {
			return [];
		}

		$data = json_decode((string)$result, true);

		if(!is_array($data)) {
			throw new \RuntimeException(
				'Invalid JSON response from OpenAI-compatible transport: ' . substr((string)$result, 0, 200)
			);
		}

		return $data;
	}

	public function stream(string $path, array $payload, callable $onChunk, array $options = []): void {
		$url = $this->buildUrl($path);
		$method = strtoupper(trim((string)($options['method'] ?? 'POST')));

		if($method === 'GET' && count($payload) > 0) {
			$query = http_build_query($payload);

			if($query !== '') {
				$url .= (str_contains($url, '?') ? '&' : '?') . $query;
			}
		}

		$jsonPayload = null;

		if($method !== 'GET') {
			$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			if($jsonPayload === false) {
				throw new \RuntimeException('Failed to encode OpenAI-compatible transport stream payload.');
			}
		}

		$headers = $this->buildHeaders($options);
		$connectTimeout = $this->resolveConnectTimeout($options);
		$responseBuffer = '';

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);

		$this->applyRequestMethod($ch, $method, $jsonPayload);

		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use ($onChunk, &$responseBuffer) {
			$chunk = (string)$chunk;
			$responseBuffer .= $chunk;
			$onChunk($chunk);

			return strlen($chunk);
		});

		$result = curl_exec($ch);

		if($result === false) {
			$error = curl_error($ch);
			curl_close($ch);
			throw new \RuntimeException('OpenAI-compatible transport streaming request failed: ' . $error);
		}

		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if($httpCode < 200 || $httpCode >= 300) {
			throw new \RuntimeException(
				'OpenAI-compatible transport streaming request failed with status ' . $httpCode . ': ' . substr($responseBuffer, 0, 500)
			);
		}
	}

	private function buildUrl(string $path): string {
		return ChatCompletionEndpointResolver::resolve(
			(string)($this->options['endpoint'] ?? ''),
			$path
		);
	}

	/**
	 * @param array<string,mixed> $options
	 * @return array<int,string>
	 */
	private function buildHeaders(array $options): array {
		$apiKey = trim((string)($options['apikey'] ?? $this->options['apikey'] ?? ''));

		if($apiKey === '') {
			throw new \RuntimeException('Missing API key for OpenAI-compatible transport.');
		}

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $apiKey
		];

		foreach(($options['headers'] ?? []) as $header) {
			if(is_string($header) && trim($header) !== '') {
				$headers[] = $header;
			}
		}

		return $headers;
	}

	/**
	 * @param resource $ch
	 */
	private function applyRequestMethod($ch, string $method, ?string $jsonPayload): void {
		if($method === 'GET') {
			curl_setopt($ch, CURLOPT_HTTPGET, true);
			return;
		}

		if($method === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
		}
		else {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		if($jsonPayload !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
		}
	}

	/**
	 * @param array<string,mixed> $options
	 */
	private function resolveTimeout(array $options): int {
		$timeout = (int)($options['timeout'] ?? $this->options['timeout'] ?? 60);

		return $timeout > 0 ? $timeout : 60;
	}

	/**
	 * @param array<string,mixed> $options
	 */
	private function resolveConnectTimeout(array $options): int {
		$timeout = (int)($options['connect_timeout'] ?? $this->options['connect_timeout'] ?? 15);

		return $timeout > 0 ? $timeout : 15;
	}
}

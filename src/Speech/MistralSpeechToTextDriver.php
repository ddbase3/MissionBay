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

namespace MissionBay\Speech;

use AssistantFoundation\Dto\RealtimeSpeechToTextSession;
use AssistantFoundation\Dto\RealtimeSpeechToTextSessionRequest;
use MissionBay\Api\ISpeechToTextDriver;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

final class MistralSpeechToTextDriver implements ISpeechToTextDriver {

	private const DEFAULT_MODEL = 'voxtral-mini-transcribe-realtime-2602';

	public static function getName(): string {
		return 'mistralspeechtotextdriver';
	}

	public function getDriver(): string {
		return 'mistral-stt';
	}

	public function createSession(
		ServiceConfig $serviceConfig,
		ConnectionConfig $connectionConfig,
		string $secret,
		RealtimeSpeechToTextSessionRequest $request
	): RealtimeSpeechToTextSession {
		$options = $serviceConfig->getOptions();
		$requestOptions = $request->getOptions();
		$model = trim((string)($requestOptions['model'] ?? $serviceConfig->getModel()));
		if($model === '') {
			$model = self::DEFAULT_MODEL;
		}

		$baseUrl = rtrim(trim($connectionConfig->getBaseUrl()), '/');
		if($baseUrl === '') {
			throw new RuntimeException('Mistral realtime speech-to-text connection has no base URL.');
		}

		$fastToken = $this->mintClientToken(
			$baseUrl,
			$model,
			$secret,
			$connectionConfig->getAuthHeaderName(),
			$connectionConfig->getTimeoutSeconds()
		);
		$slowToken = $this->mintClientToken(
			$baseUrl,
			$model,
			$secret,
			$connectionConfig->getAuthHeaderName(),
			$connectionConfig->getTimeoutSeconds()
		);

		if(hash_equals($fastToken['value'], $slowToken['value'])) {
			throw new RuntimeException('Mistral returned the same realtime token for both transcription streams.');
		}

		$fastDelay = $this->positiveInt(
			$requestOptions['fastStreamingDelayMs'] ?? $options['fastStreamingDelayMs'] ?? 240,
			'fast streaming delay'
		);
		$slowDelay = $this->positiveInt(
			$requestOptions['slowStreamingDelayMs'] ?? $options['slowStreamingDelayMs'] ?? 2400,
			'slow streaming delay'
		);
		$chunkDuration = $this->positiveInt(
			$requestOptions['chunkDurationMs'] ?? $options['chunkDurationMs'] ?? 20,
			'audio chunk duration'
		);
		$vocabulary = $this->normalizeStringList(
			$requestOptions['vocabulary'] ?? $options['vocabulary'] ?? []
		);

		return new RealtimeSpeechToTextSession(
			'mistral',
			'websocket',
			$this->buildWebSocketEndpoint($baseUrl, $model),
			$fastToken['value'],
			$this->earliestExpiration($fastToken['expiresAt'], $slowToken['expiresAt']),
			$model,
			'pcm_s16le',
			48000,
			[
				'clientTokens' => [
					'fast' => $fastToken,
					'slow' => $slowToken
				],
				'fastStreamingDelayMs' => $fastDelay,
				'slowStreamingDelayMs' => $slowDelay,
				'chunkDurationMs' => $chunkDuration,
				'supportedSampleRates' => [8000, 16000, 22050, 44100, 48000],
				'sessionTimeoutMs' => $this->positiveInt(
					$requestOptions['sessionTimeoutMs'] ?? $options['sessionTimeoutMs'] ?? 12000,
					'session timeout'
				),
				'finalizationTimeoutMs' => $this->positiveInt(
					$requestOptions['finalizationTimeoutMs'] ?? $options['finalizationTimeoutMs'] ?? 25000,
					'finalization timeout'
				),
				'maxQueueBytes' => 512 * 1024,
				'webSocketHighWaterBytes' => 192 * 1024,
				'vocabulary' => $vocabulary,
				'interimResults' => true
			]
		);
	}

	/** @return array{value:string,expiresAt:string} */
	private function mintClientToken(
		string $baseUrl,
		string $model,
		string $secret,
		string $authHeaderName,
		int $timeoutSeconds
	): array {
		$response = $this->postJson(
			$baseUrl . '/v1/client/sessions',
			[
				'purpose' => 'realtime',
				'model' => $model
			],
			$secret,
			$authHeaderName,
			$timeoutSeconds
		);

		$clientSecret = is_array($response['client_secret'] ?? null) ? $response['client_secret'] : [];
		$token = trim((string)($clientSecret['value'] ?? ''));
		$expiresAt = $this->normalizeExpiration(
			$clientSecret['expires_at'] ?? $response['expires_at'] ?? null
		);

		if(!str_starts_with($token, 'rt_')) {
			throw new RuntimeException('Mistral realtime session response has no valid client token.');
		}

		return [
			'value' => $token,
			'expiresAt' => $expiresAt
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function postJson(
		string $url,
		array $payload,
		string $secret,
		string $authHeaderName,
		int $timeoutSeconds
	): array {
		if(!function_exists('curl_init')) {
			throw new RuntimeException('PHP cURL extension is required for Mistral realtime speech-to-text.');
		}

		$body = json_encode(
			$payload,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
		);
		if(!is_string($body)) {
			throw new RuntimeException('Unable to encode Mistral realtime session request.');
		}

		$curl = curl_init($url);
		if($curl === false) {
			throw new RuntimeException('Unable to initialize Mistral realtime session request.');
		}

		curl_setopt_array($curl, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_MAXREDIRS => 0,
			CURLOPT_CONNECTTIMEOUT => min(10, max(1, $timeoutSeconds)),
			CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
			CURLOPT_NOSIGNAL => true,
			CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HTTPHEADER => [
				$this->normalizeAuthHeaderName($authHeaderName) . ': Bearer ' . $secret,
				'Content-Type: application/json',
				'Accept: application/json'
			]
		]);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if($responseBody === false) {
			throw new RuntimeException('Mistral realtime session request failed: ' . $error);
		}

		return $this->decodeJsonResponse(
			(string)$responseBody,
			$statusCode,
			'Mistral realtime session request failed'
		);
	}

	/** @return array<string,mixed> */
	private function decodeJsonResponse(string $responseBody, int $statusCode, string $errorPrefix): array {
		$decoded = json_decode($responseBody, true);
		if(!is_array($decoded)) {
			throw new RuntimeException($errorPrefix . ': invalid JSON response.');
		}
		if($statusCode < 200 || $statusCode >= 300) {
			$message = $decoded['error']['message'] ?? $decoded['message'] ?? $decoded['detail'] ?? 'HTTP ' . $statusCode;
			if(is_array($message)) {
				$message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'HTTP ' . $statusCode;
			}
			throw new RuntimeException($errorPrefix . ': ' . trim((string)$message));
		}
		return $decoded;
	}

	private function normalizeAuthHeaderName(string $authHeaderName): string {
		$authHeaderName = trim($authHeaderName);
		if($authHeaderName === '') {
			$authHeaderName = 'Authorization';
		}
		if(preg_match('/^[A-Za-z0-9-]+$/', $authHeaderName) !== 1) {
			throw new RuntimeException('Invalid authentication header name for Mistral speech-to-text.');
		}
		return $authHeaderName;
	}

	private function buildWebSocketEndpoint(string $baseUrl, string $model): string {
		$endpoint = preg_replace('/^https:/i', 'wss:', $baseUrl);
		$endpoint = preg_replace('/^http:/i', 'ws:', (string)$endpoint);
		return rtrim((string)$endpoint, '/')
			. '/v1/audio/transcriptions/realtime?model=' . rawurlencode($model);
	}

	private function normalizeExpiration(mixed $value): string {
		if(is_int($value) || (is_string($value) && ctype_digit($value))) {
			return gmdate('c', (int)$value);
		}
		if(is_string($value) && trim($value) !== '' && strtotime($value) !== false) {
			return gmdate('c', (int)strtotime($value));
		}
		return '';
	}

	private function earliestExpiration(string $left, string $right): string {
		$leftTime = strtotime($left);
		$rightTime = strtotime($right);
		if($leftTime === false) {
			return $right;
		}
		if($rightTime === false) {
			return $left;
		}
		return gmdate('c', min($leftTime, $rightTime));
	}

	/** @return array<int,string> */
	private function normalizeStringList(mixed $value): array {
		if(is_string($value)) {
			$value = preg_split('/[\r\n,]+/', $value) ?: [];
		}
		if(!is_array($value)) {
			return [];
		}

		$result = [];
		foreach($value as $item) {
			if(!is_scalar($item)) {
				continue;
			}
			$item = trim((string)$item);
			if($item === '' || preg_match('/[<>\r\n]/', $item) === 1) {
				continue;
			}
			$result[] = $item;
		}
		return array_values(array_unique($result));
	}

	private function positiveInt(mixed $value, string $label): int {
		if(!is_numeric($value) || (int)$value <= 0) {
			throw new RuntimeException('Invalid ' . $label . '.');
		}
		return (int)$value;
	}
}

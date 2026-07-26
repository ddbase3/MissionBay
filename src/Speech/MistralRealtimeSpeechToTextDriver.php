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
use MissionBay\Api\IRealtimeSpeechToTextDriver;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

final class MistralRealtimeSpeechToTextDriver implements IRealtimeSpeechToTextDriver {

	public static function getName(): string {
		return 'mistralrealtimespeechtotextdriver';
	}

	public function getDriver(): string {
		return 'mistral-realtime-stt';
	}

	public function createSession(
		ServiceConfig $serviceConfig,
		ConnectionConfig $connectionConfig,
		string $secret,
		RealtimeSpeechToTextSessionRequest $request
	): RealtimeSpeechToTextSession {
		$model = trim($serviceConfig->getModel());
		$baseUrl = rtrim(trim($connectionConfig->getBaseUrl()), '/');
		$sessionUrl = $baseUrl . '/v1/client/sessions';
		$response = $this->postJson(
			$sessionUrl,
			[
				'purpose' => 'realtime',
				'model' => $model
			],
			$secret,
			$connectionConfig->getTimeoutSeconds()
		);

		$clientSecret = is_array($response['client_secret'] ?? null) ? $response['client_secret'] : [];
		$token = trim((string)($clientSecret['value'] ?? ''));
		$expiresAt = $clientSecret['expires_at'] ?? null;

		if($token === '') {
			throw new RuntimeException('Mistral realtime session response has no client token.');
		}
		if(!is_string($expiresAt) || trim($expiresAt) === '' || strtotime($expiresAt) === false) {
			throw new RuntimeException('Mistral realtime session response has no valid expiration timestamp.');
		}

		$serviceOptions = $serviceConfig->getOptions();
		$requestOptions = $request->getOptions();
		$sampleRate = $this->positiveInt($requestOptions['sampleRate'] ?? $serviceOptions['sampleRate'] ?? 16000, 'sample rate');
		$targetDelay = $this->positiveInt($requestOptions['targetStreamingDelayMs'] ?? $serviceOptions['targetStreamingDelayMs'] ?? 480, 'target streaming delay');
		$silenceDuration = $this->positiveInt($requestOptions['silenceDurationMs'] ?? $serviceOptions['silenceDurationMs'] ?? 900, 'silence duration');
		$noSpeechTimeout = $this->positiveInt($requestOptions['noSpeechTimeoutMs'] ?? $serviceOptions['noSpeechTimeoutMs'] ?? 10000, 'no-speech timeout');
		$language = trim($request->getLanguage());
		if($language === '') {
			$language = trim((string)($serviceOptions['language'] ?? ''));
		}

		$options = [
			'targetStreamingDelayMs' => $targetDelay,
			'silenceDurationMs' => $silenceDuration,
			'noSpeechTimeoutMs' => $noSpeechTimeout,
			'interimResults' => true
		];
		if($language !== '' && strtolower($language) !== 'auto') {
			$options['language'] = $language;
		}

		return new RealtimeSpeechToTextSession(
			'mistral',
			'websocket',
			$this->buildWebSocketEndpoint($baseUrl, $model),
			$token,
			trim($expiresAt),
			$model,
			'pcm_s16le',
			$sampleRate,
			$options
		);
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	private function postJson(string $url, array $payload, string $secret, int $timeoutSeconds): array {
		$curl = curl_init($url);
		if($curl === false) {
			throw new RuntimeException('Unable to initialize Mistral realtime session request.');
		}

		$body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if($body === false) {
			throw new RuntimeException('Unable to encode Mistral realtime session request.');
		}

		curl_setopt_array($curl, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $timeoutSeconds,
			CURLOPT_HTTPHEADER => [
				'Authorization: Bearer ' . $secret,
				'Content-Type: application/json',
				'Accept: application/json'
			],
			CURLOPT_POSTFIELDS => $body
		]);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if($responseBody === false) {
			throw new RuntimeException('Mistral realtime session request failed: ' . $error);
		}

		$decoded = json_decode((string)$responseBody, true);
		if(!is_array($decoded)) {
			throw new RuntimeException('Mistral realtime session returned invalid JSON.');
		}
		if($statusCode < 200 || $statusCode >= 300) {
			$message = trim((string)($decoded['message'] ?? $decoded['detail'] ?? 'HTTP ' . $statusCode));
			throw new RuntimeException('Mistral realtime session request failed: ' . $message);
		}

		return $decoded;
	}

	private function buildWebSocketEndpoint(string $baseUrl, string $model): string {
		$endpoint = preg_replace('/^https:/i', 'wss:', $baseUrl);
		$endpoint = preg_replace('/^http:/i', 'ws:', (string)$endpoint);
		return rtrim((string)$endpoint, '/') . '/v1/audio/transcriptions/realtime?model=' . rawurlencode($model);
	}

	private function positiveInt(mixed $value, string $label): int {
		if(!is_numeric($value) || (int)$value <= 0) {
			throw new RuntimeException('Invalid ' . $label . '.');
		}
		return (int)$value;
	}
}

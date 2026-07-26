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

final class OpenAiRealtimeSpeechToTextDriver implements IRealtimeSpeechToTextDriver {

	public static function getName(): string {
		return 'openairealtimespeechtotextdriver';
	}

	public function getDriver(): string {
		return 'openai-realtime-stt';
	}

	public function createSession(
		ServiceConfig $serviceConfig,
		ConnectionConfig $connectionConfig,
		string $secret,
		RealtimeSpeechToTextSessionRequest $request
	): RealtimeSpeechToTextSession {
		$model = trim($serviceConfig->getModel());
		if($model === '') {
			throw new RuntimeException('OpenAI realtime speech-to-text model is missing.');
		}

		$baseUrl = rtrim(trim($connectionConfig->getBaseUrl()), '/');
		if($baseUrl === '') {
			throw new RuntimeException('OpenAI realtime speech-to-text connection has no base URL.');
		}

		$options = $serviceConfig->getOptions();
		$language = trim($request->getLanguage());
		if($language === '' || strtolower($language) === 'auto') {
			$language = trim((string)($options['language'] ?? ''));
		}
		$language = $this->normalizeLanguage($language);
		$prompt = trim((string)($options['prompt'] ?? ''));
		$threshold = $this->numberInRange($options['vadThreshold'] ?? 0.5, 0.0, 1.0, 0.5);
		$prefixPadding = $this->positiveInt($options['prefixPaddingMs'] ?? 300, 'prefix padding');
		$silenceDuration = $this->positiveInt($options['silenceDurationMs'] ?? 800, 'silence duration');
		$noiseReduction = $this->normalizeNoiseReduction((string)($options['noiseReduction'] ?? 'near_field'));

		$transcription = ['model' => $model];
		if($language !== '') {
			$transcription['language'] = $language;
		}
		if($prompt !== '') {
			$transcription['prompt'] = $prompt;
		}

		$input = [
			'format' => [
				'type' => 'audio/pcm',
				'rate' => 24000
			],
			'transcription' => $transcription,
			'turn_detection' => [
				'type' => 'server_vad',
				'threshold' => $threshold,
				'prefix_padding_ms' => $prefixPadding,
				'silence_duration_ms' => $silenceDuration
			]
		];
		if($noiseReduction !== '') {
			$input['noise_reduction'] = ['type' => $noiseReduction];
		}

		$response = $this->postJson(
			$baseUrl . '/v1/realtime/client_secrets',
			[
				'session' => [
					'type' => 'transcription',
					'audio' => [
						'input' => $input
					]
				]
			],
			$secret,
			$connectionConfig->getAuthHeaderName(),
			$connectionConfig->getTimeoutSeconds()
		);
		$token = trim((string)($response['value'] ?? ''));
		if($token === '') {
			throw new RuntimeException('OpenAI realtime transcription session response has no client token.');
		}

		return new RealtimeSpeechToTextSession(
			'openai',
			'websocket',
			$this->buildWebSocketEndpoint($baseUrl),
			$token,
			$this->normalizeExpiration($response['expires_at'] ?? null),
			$model,
			'pcm_s16le',
			24000,
			[
				'chunkDurationMs' => $this->positiveInt($options['chunkDurationMs'] ?? 100, 'chunk duration'),
				'finalizationTimeoutMs' => $this->positiveInt($options['finalizationTimeoutMs'] ?? 10000, 'finalization timeout'),
				'interimResults' => true
			]
		);
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	private function postJson(
		string $url,
		array $payload,
		string $secret,
		string $authHeaderName,
		int $timeoutSeconds
	): array {
		if(!function_exists('curl_init')) {
			throw new RuntimeException('PHP cURL extension is required for OpenAI realtime speech-to-text.');
		}
		$authHeaderName = trim($authHeaderName);
		if($authHeaderName === '') {
			$authHeaderName = 'Authorization';
		}
		if(preg_match('/^[A-Za-z0-9-]+$/', $authHeaderName) !== 1) {
			throw new RuntimeException('Invalid authentication header name for OpenAI realtime speech-to-text.');
		}

		$curl = curl_init($url);
		if($curl === false) {
			throw new RuntimeException('Unable to initialize OpenAI realtime speech-to-text session request.');
		}
		$body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if($body === false) {
			throw new RuntimeException('Unable to encode OpenAI realtime speech-to-text session request.');
		}

		curl_setopt_array($curl, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $timeoutSeconds,
			CURLOPT_HTTPHEADER => [
				$authHeaderName . ': Bearer ' . $secret,
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
			throw new RuntimeException('OpenAI realtime speech-to-text session request failed: ' . $error);
		}
		$decoded = json_decode((string)$responseBody, true);
		if(!is_array($decoded)) {
			throw new RuntimeException('OpenAI realtime speech-to-text session returned invalid JSON.');
		}
		if($statusCode < 200 || $statusCode >= 300) {
			$message = $decoded['error']['message'] ?? $decoded['message'] ?? 'HTTP ' . $statusCode;
			throw new RuntimeException('OpenAI realtime speech-to-text session request failed: ' . trim((string)$message));
		}

		return $decoded;
	}

	private function buildWebSocketEndpoint(string $baseUrl): string {
		$endpoint = preg_replace('/^https:/i', 'wss:', $baseUrl);
		$endpoint = preg_replace('/^http:/i', 'ws:', (string)$endpoint);
		return rtrim((string)$endpoint, '/') . '/v1/realtime';
	}

	private function normalizeExpiration(mixed $value): string {
		if(is_numeric($value) && (int)$value > 0) {
			return gmdate('c', (int)$value);
		}
		if(is_string($value) && trim($value) !== '' && strtotime($value) !== false) {
			return gmdate('c', (int)strtotime($value));
		}
		return gmdate('c', time() + 60);
	}

	private function normalizeLanguage(string $language): string {
		$language = strtolower(trim($language));
		if(str_contains($language, '-')) {
			$language = explode('-', $language, 2)[0];
		}
		return preg_match('/^[a-z]{2,3}$/', $language) === 1 ? $language : '';
	}

	private function normalizeNoiseReduction(string $value): string {
		$value = strtolower(trim($value));
		return in_array($value, ['near_field', 'far_field'], true) ? $value : '';
	}

	private function positiveInt(mixed $value, string $label): int {
		if(!is_numeric($value) || (int)$value <= 0) {
			throw new RuntimeException('Invalid ' . $label . '.');
		}
		return (int)$value;
	}

	private function numberInRange(mixed $value, float $minimum, float $maximum, float $default): float {
		if(!is_numeric($value)) {
			return $default;
		}
		return max($minimum, min($maximum, (float)$value));
	}
}

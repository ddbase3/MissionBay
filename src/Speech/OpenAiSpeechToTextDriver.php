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
use AssistantFoundation\Dto\SpeechToTextRequest;
use AssistantFoundation\Dto\SpeechToTextResult;
use CURLFile;
use MissionBay\Api\ISpeechToTextDriver;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

final class OpenAiSpeechToTextDriver implements ISpeechToTextDriver {

	public static function getName(): string {
		return 'openaispeechtotextdriver';
	}

	public function getDriver(): string {
		return 'openai-stt';
	}

	public function transcribe(
		ServiceConfig $serviceConfig,
		ConnectionConfig $connectionConfig,
		string $secret,
		SpeechToTextRequest $request
	): SpeechToTextResult {
		$model = trim($serviceConfig->getModel());
		if($model === '') {
			throw new RuntimeException('OpenAI speech-to-text model is missing.');
		}

		$audio = $request->getAudio();
		if($audio === '') {
			throw new RuntimeException('Speech-to-text audio input is empty.');
		}

		$baseUrl = rtrim(trim($connectionConfig->getBaseUrl()), '/');
		if($baseUrl === '') {
			throw new RuntimeException('OpenAI speech-to-text connection has no base URL.');
		}

		$options = $serviceConfig->getOptions();
		$requestOptions = $request->getOptions();
		$language = $this->normalizeLanguage(
			$request->getLanguage() !== '' ? $request->getLanguage() : (string)($options['language'] ?? '')
		);
		$prompt = trim((string)($requestOptions['prompt'] ?? $options['prompt'] ?? ''));

		$form = [
			'model' => $model,
			'response_format' => 'json'
		];
		if($language !== '') {
			$form['language'] = $language;
		}
		if($prompt !== '') {
			$form['prompt'] = $prompt;
		}

		$response = $this->postMultipartAudio(
			$baseUrl . '/v1/audio/transcriptions',
			$form,
			$audio,
			$request->getMimeType(),
			$secret,
			$connectionConfig->getAuthHeaderName(),
			$connectionConfig->getTimeoutSeconds()
		);

		$text = trim((string)($response['text'] ?? ''));
		$resolvedLanguage = trim((string)($response['language'] ?? $language));
		$metadata = [
			'provider' => 'openai',
			'model' => trim((string)($response['model'] ?? $model))
		];
		if(is_array($response['usage'] ?? null)) {
			$metadata['usage'] = $response['usage'];
		}

		return new SpeechToTextResult($text, $resolvedLanguage, $metadata, $response);
	}

	public function createSession(
		ServiceConfig $serviceConfig,
		ConnectionConfig $connectionConfig,
		string $secret,
		RealtimeSpeechToTextSessionRequest $request
	): RealtimeSpeechToTextSession {
		$options = $serviceConfig->getOptions();
		$requestOptions = $request->getOptions();
		$model = trim((string)($requestOptions['realtimeModel'] ?? $options['realtimeModel'] ?? $serviceConfig->getModel()));
		if($model === '') {
			throw new RuntimeException('OpenAI realtime speech-to-text model is missing.');
		}

		$baseUrl = rtrim(trim($connectionConfig->getBaseUrl()), '/');
		if($baseUrl === '') {
			throw new RuntimeException('OpenAI realtime speech-to-text connection has no base URL.');
		}

		$language = trim($request->getLanguage());
		if($language === '' || strtolower($language) === 'auto') {
			$language = trim((string)($options['language'] ?? ''));
		}
		$language = $this->normalizeLanguage($language);
		$prompt = trim((string)($requestOptions['prompt'] ?? $options['prompt'] ?? ''));
		$threshold = $this->numberInRange($requestOptions['vadThreshold'] ?? $options['vadThreshold'] ?? 0.5, 0.0, 1.0, 0.5);
		$prefixPadding = $this->positiveInt($requestOptions['prefixPaddingMs'] ?? $options['prefixPaddingMs'] ?? 300, 'prefix padding');
		$silenceDuration = $this->positiveInt($requestOptions['silenceDurationMs'] ?? $options['silenceDurationMs'] ?? 800, 'silence duration');
		$noiseReduction = $this->normalizeNoiseReduction((string)($requestOptions['noiseReduction'] ?? $options['noiseReduction'] ?? 'near_field'));

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
				'chunkDurationMs' => $this->positiveInt($requestOptions['chunkDurationMs'] ?? $options['chunkDurationMs'] ?? 100, 'chunk duration'),
				'finalizationTimeoutMs' => $this->positiveInt($requestOptions['finalizationTimeoutMs'] ?? $options['finalizationTimeoutMs'] ?? 10000, 'finalization timeout'),
				'interimResults' => true
			]
		);
	}

	/**
	 * @param array<string,mixed> $fields
	 * @return array<string,mixed>
	 */
	private function postMultipartAudio(
		string $url,
		array $fields,
		string $audio,
		string $mimeType,
		string $secret,
		string $authHeaderName,
		int $timeoutSeconds
	): array {
		if(!function_exists('curl_init')) {
			throw new RuntimeException('PHP cURL extension is required for OpenAI speech-to-text.');
		}

		$tempFile = tempnam(sys_get_temp_dir(), 'base3_stt_');
		if($tempFile === false || file_put_contents($tempFile, $audio) === false) {
			throw new RuntimeException('Unable to prepare OpenAI speech-to-text audio upload.');
		}

		try {
			$mimeType = $this->normalizeMimeType($mimeType);
			$fields['file'] = new CURLFile($tempFile, $mimeType, 'audio.' . $this->extensionForMimeType($mimeType));
			return $this->postForm($url, $fields, $secret, $authHeaderName, $timeoutSeconds);
		}
		finally {
			@unlink($tempFile);
		}
	}

	/** @param array<string,mixed> $fields @return array<string,mixed> */
	private function postForm(
		string $url,
		array $fields,
		string $secret,
		string $authHeaderName,
		int $timeoutSeconds
	): array {
		$authHeaderName = $this->normalizeAuthHeaderName($authHeaderName);
		$curl = curl_init($url);
		if($curl === false) {
			throw new RuntimeException('Unable to initialize OpenAI speech-to-text request.');
		}

		curl_setopt_array($curl, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $timeoutSeconds,
			CURLOPT_HTTPHEADER => [
				$authHeaderName . ': Bearer ' . $secret,
				'Accept: application/json'
			],
			CURLOPT_POSTFIELDS => $fields
		]);
		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if($responseBody === false) {
			throw new RuntimeException('OpenAI speech-to-text request failed: ' . $error);
		}
		return $this->decodeJsonResponse((string)$responseBody, $statusCode, 'OpenAI speech-to-text request failed');
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
			throw new RuntimeException('PHP cURL extension is required for OpenAI realtime speech-to-text.');
		}
		$authHeaderName = $this->normalizeAuthHeaderName($authHeaderName);
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
		return $this->decodeJsonResponse((string)$responseBody, $statusCode, 'OpenAI realtime speech-to-text session request failed');
	}

	/** @return array<string,mixed> */
	private function decodeJsonResponse(string $responseBody, int $statusCode, string $errorPrefix): array {
		$decoded = json_decode($responseBody, true);
		if(!is_array($decoded)) {
			throw new RuntimeException($errorPrefix . ': invalid JSON response.');
		}
		if($statusCode < 200 || $statusCode >= 300) {
			$message = $decoded['error']['message'] ?? $decoded['message'] ?? 'HTTP ' . $statusCode;
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
			throw new RuntimeException('Invalid authentication header name for OpenAI speech-to-text.');
		}
		return $authHeaderName;
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

	private function normalizeMimeType(string $mimeType): string {
		$mimeType = strtolower(trim(explode(';', $mimeType, 2)[0] ?? ''));
		return $mimeType !== '' ? $mimeType : 'audio/wav';
	}

	private function extensionForMimeType(string $mimeType): string {
		return match($mimeType) {
			'audio/mpeg', 'audio/mp3' => 'mp3',
			'audio/mp4', 'audio/x-m4a' => 'm4a',
			'audio/ogg' => 'ogg',
			'audio/flac' => 'flac',
			'audio/webm' => 'webm',
			default => 'wav'
		};
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

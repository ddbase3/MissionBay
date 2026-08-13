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

final class MistralSpeechToTextDriver implements ISpeechToTextDriver {

	public static function getName(): string {
		return 'mistralspeechtotextdriver';
	}

	public function getDriver(): string {
		return 'mistral-stt';
	}

	public function transcribe(
		ServiceConfig $serviceConfig,
		ConnectionConfig $connectionConfig,
		string $secret,
		SpeechToTextRequest $request
	): SpeechToTextResult {
		$model = trim($serviceConfig->getModel());
		if($model === '') {
			throw new RuntimeException('Mistral speech-to-text model is missing.');
		}

		$audio = $request->getAudio();
		if($audio === '') {
			throw new RuntimeException('Speech-to-text audio input is empty.');
		}

		$baseUrl = rtrim(trim($connectionConfig->getBaseUrl()), '/');
		if($baseUrl === '') {
			throw new RuntimeException('Mistral speech-to-text connection has no base URL.');
		}

		$options = $serviceConfig->getOptions();
		$requestOptions = $request->getOptions();
		$language = trim($request->getLanguage());
		if($language === '' || strtolower($language) === 'auto') {
			$language = trim((string)($requestOptions['language'] ?? $options['language'] ?? ''));
		}
		$language = $this->normalizeLanguage($language);

		$form = [
			'model' => $model
		];
		if($this->normalizeBool($requestOptions['diarize'] ?? $options['diarize'] ?? false)) {
			$form['diarize'] = 'true';
		}
		if($language !== '') {
			$form['language'] = $language;
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
			'provider' => 'mistral',
			'model' => trim((string)($response['model'] ?? $model))
		];
		if(is_array($response['segments'] ?? null)) {
			$metadata['segments'] = $response['segments'];
		}
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
		$serviceOptions = $serviceConfig->getOptions();
		$requestOptions = $request->getOptions();
		$model = trim((string)(
			$requestOptions['realtimeModel']
			?? $serviceOptions['realtimeModel']
			?? $serviceConfig->getModel()
		));
		if($model === '') {
			throw new RuntimeException('Mistral realtime speech-to-text model is missing.');
		}

		$baseUrl = rtrim(trim($connectionConfig->getBaseUrl()), '/');
		if($baseUrl === '') {
			throw new RuntimeException('Mistral realtime speech-to-text connection has no base URL.');
		}

		$response = $this->postJson(
			$baseUrl . '/v1/client/sessions',
			[
				'purpose' => 'realtime',
				'model' => $model
			],
			$secret,
			$connectionConfig->getAuthHeaderName(),
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

		$sampleRate = $this->positiveInt($requestOptions['sampleRate'] ?? $serviceOptions['sampleRate'] ?? 16000, 'sample rate');
		$targetDelay = $this->positiveInt($requestOptions['targetStreamingDelayMs'] ?? $serviceOptions['targetStreamingDelayMs'] ?? 480, 'target streaming delay');
		$silenceDuration = $this->positiveInt($requestOptions['silenceDurationMs'] ?? $serviceOptions['silenceDurationMs'] ?? 900, 'silence duration');
		$noSpeechTimeout = $this->positiveInt($requestOptions['noSpeechTimeoutMs'] ?? $serviceOptions['noSpeechTimeoutMs'] ?? 10000, 'no-speech timeout');
		$language = trim($request->getLanguage());
		if($language === '' || strtolower($language) === 'auto') {
			$language = trim((string)($serviceOptions['language'] ?? ''));
		}

		$options = [
			'targetStreamingDelayMs' => $targetDelay,
			'silenceDurationMs' => $silenceDuration,
			'noSpeechTimeoutMs' => $noSpeechTimeout,
			'chunkDurationMs' => $this->positiveInt($requestOptions['chunkDurationMs'] ?? $serviceOptions['chunkDurationMs'] ?? 480, 'chunk duration'),
			'finalizationTimeoutMs' => $this->positiveInt($requestOptions['finalizationTimeoutMs'] ?? $serviceOptions['finalizationTimeoutMs'] ?? 10000, 'finalization timeout'),
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

	/** @param array<string,mixed> $fields @return array<string,mixed> */
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
			throw new RuntimeException('PHP cURL extension is required for Mistral speech-to-text.');
		}

		$tempFile = tempnam(sys_get_temp_dir(), 'base3_stt_');
		if($tempFile === false || file_put_contents($tempFile, $audio) === false) {
			throw new RuntimeException('Unable to prepare Mistral speech-to-text audio upload.');
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
			throw new RuntimeException('Unable to initialize Mistral speech-to-text request.');
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
			throw new RuntimeException('Mistral speech-to-text request failed: ' . $error);
		}
		return $this->decodeJsonResponse((string)$responseBody, $statusCode, 'Mistral speech-to-text request failed');
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
		$authHeaderName = $this->normalizeAuthHeaderName($authHeaderName);
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
			throw new RuntimeException('Mistral realtime session request failed: ' . $error);
		}
		return $this->decodeJsonResponse((string)$responseBody, $statusCode, 'Mistral realtime session request failed');
	}

	/** @return array<string,mixed> */
	private function decodeJsonResponse(string $responseBody, int $statusCode, string $errorPrefix): array {
		$decoded = json_decode($responseBody, true);
		if(!is_array($decoded)) {
			throw new RuntimeException($errorPrefix . ': invalid JSON response.');
		}
		if($statusCode < 200 || $statusCode >= 300) {
			$message = $decoded['message'] ?? $decoded['detail'] ?? 'HTTP ' . $statusCode;
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

	private function normalizeLanguage(string $language): string {
		$language = strtolower(trim($language));
		if(str_contains($language, '-')) {
			$language = explode('-', $language, 2)[0];
		}
		return preg_match('/^[a-z]{2,3}$/', $language) === 1 ? $language : '';
	}

	private function normalizeBool(mixed $value): bool {
		if(is_bool($value)) {
			return $value;
		}
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
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
}

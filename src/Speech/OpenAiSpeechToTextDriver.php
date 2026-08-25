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

final class OpenAiSpeechToTextDriver implements ISpeechToTextDriver {

	private const DEFAULT_MODEL = 'gpt-live-transcribe';
	private const DEFAULT_PROMPT = 'Deutschsprachige Chatnachricht, frei diktiert in natürlicher Alltagssprache. Erwartet werden vollständige Sätze mit deutscher Groß- und Kleinschreibung sowie passender Zeichensetzung. Namen, Zahlen, Datumsangaben, E-Mail-Adressen, URLs, Produktnamen und technische Begriffe können vorkommen.';

	public static function getName(): string {
		return 'openaispeechtotextdriver';
	}

	public function getDriver(): string {
		return 'openai-stt';
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
			throw new RuntimeException('OpenAI realtime speech-to-text connection has no base URL.');
		}

		$languages = $this->normalizeLanguages(
			$requestOptions['languages'] ?? $options['languages'] ?? []
		);
		if($languages === []) {
			$language = $this->normalizeLanguage($request->getLanguage());
			$languages = [$language !== '' ? $language : 'de'];
		}

		$keywords = $this->normalizeStringList(
			$requestOptions['keywords'] ?? $options['keywords'] ?? []
		);
		$delay = $this->normalizeDelay((string)($requestOptions['delay'] ?? $options['delay'] ?? 'low'));
		$noiseReduction = $this->normalizeNoiseReduction(
			(string)($requestOptions['noiseReduction'] ?? $options['noiseReduction'] ?? 'far_field')
		);
		$ttl = $this->positiveInt(
			$requestOptions['clientSecretTtlSeconds'] ?? $options['clientSecretTtlSeconds'] ?? 120,
			'client secret TTL'
		);
		$prompt = trim((string)($requestOptions['prompt'] ?? $options['prompt'] ?? self::DEFAULT_PROMPT));
		if($prompt === '') {
			$prompt = self::DEFAULT_PROMPT;
		}
		$context = $this->normalizeContext((string)($requestOptions['context'] ?? ''));

		$transcription = [
			'model' => $model,
			'languages' => $languages,
			'delay' => $delay,
			'prompt' => $this->buildPrompt($prompt, $context)
		];
		if($keywords !== []) {
			$transcription['keywords'] = $keywords;
		}

		$response = $this->postJson(
			$baseUrl . '/v1/realtime/client_secrets',
			[
				'expires_after' => [
					'anchor' => 'created_at',
					'seconds' => $ttl
				],
				'session' => [
					'type' => 'transcription',
					'audio' => [
						'input' => [
							'format' => [
								'type' => 'audio/pcm',
								'rate' => 24000
							],
							'noise_reduction' => [
								'type' => $noiseReduction
							],
							'transcription' => $transcription,
							'turn_detection' => null
						]
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
			'webrtc',
			$baseUrl . '/v1/realtime/calls',
			$token,
			$this->normalizeExpiration($response['expires_at'] ?? null),
			$model,
			'audio/pcm',
			24000,
			[
				'finalizationTimeoutMs' => $this->positiveInt(
					$requestOptions['finalizationTimeoutMs'] ?? $options['finalizationTimeoutMs'] ?? 10000,
					'finalization timeout'
				),
				'commitDrainMs' => 180,
				'transcriptQuietMs' => 300,
				'interimResults' => true
			]
		);
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

		$body = json_encode(
			$payload,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
		);
		if(!is_string($body)) {
			throw new RuntimeException('Unable to encode OpenAI realtime speech-to-text session request.');
		}

		$curl = curl_init($url);
		if($curl === false) {
			throw new RuntimeException('Unable to initialize OpenAI realtime speech-to-text session request.');
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
			throw new RuntimeException('OpenAI realtime speech-to-text session request failed: ' . $error);
		}

		return $this->decodeJsonResponse(
			(string)$responseBody,
			$statusCode,
			'OpenAI realtime speech-to-text session request failed'
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

	private function buildPrompt(string $prompt, string $context): string {
		if($context === '') {
			return $prompt;
		}

		return $prompt
			. "\n\nBereits vorhandener Text unmittelbar vor der Einfügeposition:\n"
			. $context;
	}

	private function normalizeContext(string $context): string {
		$context = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $context) ?? '';
		$context = trim($context);
		if(strlen($context) > 4000) {
			$context = substr($context, -4000);
		}
		return $context;
	}

	/** @return array<int,string> */
	private function normalizeLanguages(mixed $value): array {
		$languages = [];
		foreach($this->normalizeStringList($value) as $language) {
			$language = $this->normalizeLanguage($language);
			if($language !== '') {
				$languages[] = $language;
			}
		}
		return array_values(array_unique($languages));
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

	private function normalizeLanguage(string $language): string {
		$language = strtolower(trim($language));
		if($language === '' || $language === 'auto') {
			return '';
		}
		if(str_contains($language, '-')) {
			$language = explode('-', $language, 2)[0];
		}
		return preg_match('/^[a-z]{2,3}$/', $language) === 1 ? $language : '';
	}

	private function normalizeDelay(string $delay): string {
		$delay = strtolower(trim($delay));
		if(!in_array($delay, ['low', 'medium', 'high'], true)) {
			throw new RuntimeException('Invalid OpenAI realtime transcription delay.');
		}
		return $delay;
	}

	private function normalizeNoiseReduction(string $noiseReduction): string {
		$noiseReduction = strtolower(trim($noiseReduction));
		if(!in_array($noiseReduction, ['near_field', 'far_field'], true)) {
			throw new RuntimeException('Invalid OpenAI realtime noise reduction.');
		}
		return $noiseReduction;
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

	private function normalizeExpiration(mixed $value): string {
		if(is_int($value) || (is_string($value) && ctype_digit($value))) {
			return gmdate('c', (int)$value);
		}
		if(is_string($value) && trim($value) !== '' && strtotime($value) !== false) {
			return gmdate('c', (int)strtotime($value));
		}
		return '';
	}

	private function positiveInt(mixed $value, string $label): int {
		if(!is_numeric($value) || (int)$value <= 0) {
			throw new RuntimeException('Invalid ' . $label . '.');
		}
		return (int)$value;
	}
}

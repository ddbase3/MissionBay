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

use AssistantFoundation\Dto\TextToSpeechRequest;
use AssistantFoundation\Dto\TextToSpeechResult;
use MissionBay\Api\ITextToSpeechDriver;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

final class MistralTextToSpeechDriver implements ITextToSpeechDriver {

	private const MIME_TYPES = [
		'mp3' => 'audio/mpeg',
		'opus' => 'audio/ogg',
		'flac' => 'audio/flac',
		'wav' => 'audio/wav',
		'pcm' => 'audio/pcm'
	];

	public static function getName(): string {
		return 'mistraltexttospeechdriver';
	}

	public function getDriver(): string {
		return 'mistral-tts';
	}

	public function synthesize(
		ServiceConfig $serviceConfig,
		ConnectionConfig $connectionConfig,
		string $secret,
		TextToSpeechRequest $request
	): TextToSpeechResult {
		$text = trim($request->getText());
		if($text === '') {
			throw new RuntimeException('Text-to-speech input is empty.');
		}
		$model = trim($serviceConfig->getModel());
		if($model === '') {
			throw new RuntimeException('Mistral text-to-speech model is missing.');
		}

		$serviceOptions = $serviceConfig->getOptions();
		$requestOptions = $request->getOptions();
		$voiceId = trim((string)(
			$requestOptions['voice']
			?? $requestOptions['voiceId']
			?? $serviceOptions['voice']
			?? $serviceOptions['voiceId']
			?? ''
		));
		if($voiceId === '') {
			throw new RuntimeException('Mistral text-to-speech voice ID is missing.');
		}
		$responseFormat = $this->normalizeResponseFormat(
			(string)($requestOptions['responseFormat'] ?? $serviceOptions['responseFormat'] ?? 'mp3')
		);
		$payload = [
			'model' => $model,
			'input' => $text,
			'response_format' => $responseFormat,
			'stream' => false
		];
		$payload['voice_id'] = $voiceId;

		$baseUrl = rtrim(trim($connectionConfig->getBaseUrl()), '/');
		if($baseUrl === '') {
			throw new RuntimeException('Mistral text-to-speech connection has no base URL.');
		}
		$response = $this->postJson(
			$baseUrl . '/v1/audio/speech',
			$payload,
			$secret,
			$connectionConfig->getAuthHeaderName(),
			$connectionConfig->getTimeoutSeconds()
		);
		$encodedAudio = trim((string)($response['audio_data'] ?? ''));
		$audio = base64_decode($encodedAudio, true);
		if($encodedAudio === '' || $audio === false || $audio === '') {
			throw new RuntimeException('Mistral text-to-speech response has no valid audio data.');
		}

		return new TextToSpeechResult(
			$audio,
			self::MIME_TYPES[$responseFormat],
			[
				'provider' => 'mistral',
				'model' => $model,
				'voiceId' => $voiceId,
				'responseFormat' => $responseFormat
			],
			$response
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
			throw new RuntimeException('PHP cURL extension is required for Mistral text-to-speech.');
		}
		$authHeaderName = trim($authHeaderName);
		if($authHeaderName === '') {
			$authHeaderName = 'Authorization';
		}
		if(preg_match('/^[A-Za-z0-9-]+$/', $authHeaderName) !== 1) {
			throw new RuntimeException('Invalid authentication header name for Mistral text-to-speech.');
		}
		$body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if($body === false) {
			throw new RuntimeException('Unable to encode Mistral text-to-speech request.');
		}
		$curl = curl_init($url);
		if($curl === false) {
			throw new RuntimeException('Unable to initialize Mistral text-to-speech request.');
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
			throw new RuntimeException('Mistral text-to-speech request failed: ' . $error);
		}
		$decoded = json_decode((string)$responseBody, true);
		if(!is_array($decoded)) {
			throw new RuntimeException('Mistral text-to-speech returned invalid JSON.');
		}
		if($statusCode < 200 || $statusCode >= 300) {
			throw new RuntimeException(
				'Mistral text-to-speech request failed: ' . $this->extractErrorMessage($decoded, $statusCode)
			);
		}
		return $decoded;
	}

	/**
	 * @param array<string,mixed> $response
	 */
	private function extractErrorMessage(array $response, int $statusCode): string {
		$message = $response['message'] ?? $response['error'] ?? null;
		if(is_string($message) && trim($message) !== '') {
			return trim($message);
		}

		$detail = $response['detail'] ?? null;
		if(is_string($detail) && trim($detail) !== '') {
			return trim($detail);
		}

		if(is_array($detail)) {
			$messages = [];
			foreach($detail as $entry) {
				if(is_string($entry) && trim($entry) !== '') {
					$messages[] = trim($entry);
					continue;
				}

				if(!is_array($entry)) {
					continue;
				}

				$entryMessage = trim((string)($entry['msg'] ?? $entry['message'] ?? ''));
				if($entryMessage === '') {
					continue;
				}

				$location = $entry['loc'] ?? [];
				if(is_array($location) && $location !== []) {
					$location = implode('.', array_map(static fn($value): string => (string)$value, $location));
					$entryMessage = $location . ': ' . $entryMessage;
				}

				$messages[] = $entryMessage;
			}

			if($messages !== []) {
				return implode('; ', $messages);
			}
		}

		return 'HTTP ' . $statusCode;
	}

	private function normalizeResponseFormat(string $format): string {
		$format = strtolower(trim($format));
		return array_key_exists($format, self::MIME_TYPES) ? $format : 'mp3';
	}
}

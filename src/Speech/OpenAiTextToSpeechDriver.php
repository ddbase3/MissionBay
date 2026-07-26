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

final class OpenAiTextToSpeechDriver implements ITextToSpeechDriver {

	private const MIME_TYPES = [
		'mp3' => 'audio/mpeg',
		'opus' => 'audio/ogg',
		'aac' => 'audio/aac',
		'flac' => 'audio/flac',
		'wav' => 'audio/wav',
		'pcm' => 'audio/pcm'
	];

	public static function getName(): string {
		return 'openaitexttospeechdriver';
	}

	public function getDriver(): string {
		return 'openai-tts';
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
			throw new RuntimeException('OpenAI text-to-speech model is missing.');
		}

		$serviceOptions = $serviceConfig->getOptions();
		$requestOptions = $request->getOptions();
		$voice = trim((string)($requestOptions['voice'] ?? $serviceOptions['voice'] ?? 'alloy'));
		$responseFormat = $this->normalizeResponseFormat(
			(string)($requestOptions['responseFormat'] ?? $serviceOptions['responseFormat'] ?? 'mp3')
		);
		$speed = $this->normalizeSpeed($requestOptions['speed'] ?? $serviceOptions['speed'] ?? 1.0);
		$instructions = trim((string)($requestOptions['instructions'] ?? $serviceOptions['instructions'] ?? ''));

		if($voice === '') {
			throw new RuntimeException('OpenAI text-to-speech voice is missing.');
		}

		$payload = [
			'model' => $model,
			'input' => $text,
			'voice' => $voice,
			'response_format' => $responseFormat,
			'speed' => $speed
		];
		if($instructions !== '') {
			$payload['instructions'] = $instructions;
		}

		$baseUrl = rtrim(trim($connectionConfig->getBaseUrl()), '/');
		if($baseUrl === '') {
			throw new RuntimeException('OpenAI text-to-speech connection has no base URL.');
		}

		$authHeaderName = trim($connectionConfig->getAuthHeaderName());
		if($authHeaderName === '') {
			$authHeaderName = 'Authorization';
		}

		$audio = $this->postJson(
			$baseUrl . '/v1/audio/speech',
			$payload,
			$secret,
			$authHeaderName,
			$connectionConfig->getTimeoutSeconds()
		);

		return new TextToSpeechResult(
			$audio,
			self::MIME_TYPES[$responseFormat],
			[
				'provider' => 'openai',
				'model' => $model,
				'voice' => $voice,
				'responseFormat' => $responseFormat,
				'speed' => $speed
			]
		);
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private function postJson(
		string $url,
		array $payload,
		string $secret,
		string $authHeaderName,
		int $timeoutSeconds
	): string {
		if(!function_exists('curl_init')) {
			throw new RuntimeException('PHP cURL extension is required for OpenAI text-to-speech.');
		}
		if(preg_match('/^[A-Za-z0-9-]+$/', $authHeaderName) !== 1) {
			throw new RuntimeException('Invalid authentication header name for OpenAI text-to-speech.');
		}

		$curl = curl_init($url);
		if($curl === false) {
			throw new RuntimeException('Unable to initialize OpenAI text-to-speech request.');
		}

		$body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if($body === false) {
			throw new RuntimeException('Unable to encode OpenAI text-to-speech request.');
		}

		curl_setopt_array($curl, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $timeoutSeconds,
			CURLOPT_HTTPHEADER => [
				$authHeaderName . ': Bearer ' . $secret,
				'Content-Type: application/json',
				'Accept: application/octet-stream'
			],
			CURLOPT_POSTFIELDS => $body
		]);

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if($responseBody === false) {
			throw new RuntimeException('OpenAI text-to-speech request failed: ' . $error);
		}

		if($statusCode < 200 || $statusCode >= 300) {
			$message = $this->extractErrorMessage((string)$responseBody);
			throw new RuntimeException('OpenAI text-to-speech request failed with HTTP ' . $statusCode . ': ' . $message);
		}

		if((string)$responseBody === '') {
			throw new RuntimeException('OpenAI text-to-speech response is empty.');
		}

		return (string)$responseBody;
	}

	private function extractErrorMessage(string $body): string {
		$decoded = json_decode($body, true);
		if(is_array($decoded)) {
			$message = $decoded['error']['message'] ?? $decoded['message'] ?? null;
			if(is_scalar($message) && trim((string)$message) !== '') {
				return trim((string)$message);
			}
		}

		$body = trim($body);
		return $body !== '' ? $body : 'Unknown provider error.';
	}

	private function normalizeResponseFormat(string $format): string {
		$format = strtolower(trim($format));
		return array_key_exists($format, self::MIME_TYPES) ? $format : 'mp3';
	}

	private function normalizeSpeed(mixed $speed): float {
		if(!is_numeric($speed)) {
			return 1.0;
		}

		$speed = (float)$speed;
		return max(0.25, min(4.0, $speed));
	}
}

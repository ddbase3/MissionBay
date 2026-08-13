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

use AssistantFoundation\Api\ITextToSpeechStream;
use AssistantFoundation\Dto\TextToSpeechRequest;
use AssistantFoundation\Dto\TextToSpeechResult;
use MediaFoundation\Model\AudioMedia;
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
		$prepared = $this->prepareRequest($serviceConfig, $connectionConfig, $request);
		$payload = $prepared['payload'];
		$payload['stream'] = false;

		$response = $this->postJson(
			$prepared['url'],
			$payload,
			$secret,
			$prepared['authHeaderName'],
			$prepared['timeoutSeconds']
		);

		$encodedAudio = trim((string)($response['audio_data'] ?? ''));
		$audio = base64_decode($encodedAudio, true);
		if($encodedAudio === '' || $audio === false || $audio === '') {
			throw new RuntimeException('Mistral text-to-speech response has no valid audio data.');
		}

		$metadata = $prepared['metadata'];
		$metadata['audioBytes'] = strlen($audio);
		$metadata['streaming'] = false;
		if(is_array($response['usage'] ?? null)) {
			$metadata['usage'] = $response['usage'];
		}

		return new TextToSpeechResult(
			$prepared['mimeType'],
			new AudioMedia($audio, $prepared['mimeType'], 0.0, 0),
			$metadata,
			$response
		);
	}

	public function stream(
		ServiceConfig $serviceConfig,
		ConnectionConfig $connectionConfig,
		string $secret,
		TextToSpeechRequest $request,
		ITextToSpeechStream $stream
	): TextToSpeechResult {
		$prepared = $this->prepareRequest($serviceConfig, $connectionConfig, $request);
		$payload = $prepared['payload'];
		$payload['stream'] = true;

		$streamResult = $this->postJsonStream(
			$prepared['url'],
			$payload,
			$secret,
			$prepared['authHeaderName'],
			$prepared['timeoutSeconds'],
			$prepared['mimeType'],
			$prepared['metadata'],
			$stream
		);

		$metadata = $prepared['metadata'];
		$metadata['audioBytes'] = $streamResult['audioBytes'];
		$metadata['streaming'] = true;
		$metadata['cancelled'] = $stream->isCancelled();
		if($streamResult['usage'] !== []) {
			$metadata['usage'] = $streamResult['usage'];
		}

		return new TextToSpeechResult(
			$prepared['mimeType'],
			null,
			$metadata,
			$streamResult['doneEvent']
		);
	}

	/**
	 * @return array{
	 *     url:string,
	 *     payload:array<string,mixed>,
	 *     authHeaderName:string,
	 *     timeoutSeconds:int,
	 *     mimeType:string,
	 *     metadata:array<string,mixed>
	 * }
	 */
	private function prepareRequest(
		ServiceConfig $serviceConfig,
		ConnectionConfig $connectionConfig,
		TextToSpeechRequest $request
	): array {
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
		$baseUrl = rtrim(trim($connectionConfig->getBaseUrl()), '/');
		if($baseUrl === '') {
			throw new RuntimeException('Mistral text-to-speech connection has no base URL.');
		}

		$authHeaderName = trim($connectionConfig->getAuthHeaderName());
		if($authHeaderName === '') {
			$authHeaderName = 'Authorization';
		}
		if(preg_match('/^[A-Za-z0-9-]+$/', $authHeaderName) !== 1) {
			throw new RuntimeException('Invalid authentication header name for Mistral text-to-speech.');
		}

		return [
			'url' => $baseUrl . '/v1/audio/speech',
			'payload' => [
				'model' => $model,
				'input' => $text,
				'response_format' => $responseFormat,
				'voice_id' => $voiceId
			],
			'authHeaderName' => $authHeaderName,
			'timeoutSeconds' => $connectionConfig->getTimeoutSeconds(),
			'mimeType' => self::MIME_TYPES[$responseFormat],
			'metadata' => [
				'provider' => 'mistral',
				'model' => $model,
				'voiceId' => $voiceId,
				'responseFormat' => $responseFormat
			]
		];
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

		$result = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if($result === false) {
			throw new RuntimeException('Mistral text-to-speech request failed: ' . $error);
		}
		if($statusCode < 200 || $statusCode >= 300) {
			throw new RuntimeException(
				'Mistral text-to-speech request failed: ' . $this->extractErrorMessage($result, $statusCode)
			);
		}

		$response = json_decode($result, true);
		if(!is_array($response)) {
			throw new RuntimeException('Mistral text-to-speech returned invalid JSON.');
		}

		return $response;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $metadata
	 * @return array{audioBytes:int,usage:array<string,mixed>,doneEvent:?array<string,mixed>}
	 */
	private function postJsonStream(
		string $url,
		array $payload,
		string $secret,
		string $authHeaderName,
		int $timeoutSeconds,
		string $mimeType,
		array $metadata,
		ITextToSpeechStream $stream
	): array {
		if(!function_exists('curl_init')) {
			throw new RuntimeException('PHP cURL extension is required for Mistral text-to-speech.');
		}

		$body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if($body === false) {
			throw new RuntimeException('Unable to encode Mistral text-to-speech request.');
		}

		$curl = curl_init($url);
		if($curl === false) {
			throw new RuntimeException('Unable to initialize Mistral text-to-speech request.');
		}

		$statusCode = 0;
		$errorBody = '';
		$sseBuffer = '';
		$streamStarted = false;
		$audioBytes = 0;
		$streamError = '';
		$usage = [];
		$doneEvent = null;

		$consumeEvent = function(string $event) use (
			&$streamStarted,
			&$audioBytes,
			&$streamError,
			&$usage,
			&$doneEvent,
			$mimeType,
			$metadata,
			$stream
		): void {
			if($streamError !== '') {
				return;
			}

			$eventName = '';
			$dataLines = [];
			foreach(preg_split('/\r\n|\r|\n/', $event) ?: [] as $line) {
				if(str_starts_with($line, 'event:')) {
					$eventName = trim(substr($line, 6));
					continue;
				}
				if(str_starts_with($line, 'data:')) {
					$dataLines[] = ltrim(substr($line, 5));
				}
			}

			$data = trim(implode("\n", $dataLines));
			if($data === '' || $data === '[DONE]') {
				return;
			}

			$decoded = json_decode($data, true);
			if(!is_array($decoded)) {
				$streamError = 'Mistral text-to-speech returned an invalid stream event.';
				return;
			}

			$type = trim((string)($decoded['type'] ?? $eventName));
			if($type === 'speech.audio.done') {
				$doneEvent = $decoded;
				$usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
				return;
			}

			if($type !== 'speech.audio.delta') {
				$error = $decoded['error'] ?? null;
				if(is_array($error)) {
					$error = $error['message'] ?? null;
				}
				if(is_scalar($error) && trim((string)$error) !== '') {
					$streamError = 'Mistral text-to-speech stream failed: ' . trim((string)$error);
				}
				return;
			}

			$encodedAudio = trim((string)($decoded['audio_data'] ?? ''));
			$audio = base64_decode($encodedAudio, true);
			if($encodedAudio === '' || $audio === false || $audio === '') {
				$streamError = 'Mistral text-to-speech audio event has no valid audio data.';
				return;
			}

			if(!$streamStarted) {
				$stream->start($mimeType, $metadata);
				$streamStarted = true;
			}
			$stream->write($audio);
			$audioBytes += strlen($audio);
		};

		$consumeBuffer = static function(bool $flush = false) use (&$sseBuffer, $consumeEvent): void {
			while(preg_match('/\r?\n\r?\n/', $sseBuffer, $matches, PREG_OFFSET_CAPTURE) === 1) {
				$separator = $matches[0][0];
				$offset = $matches[0][1];
				$event = substr($sseBuffer, 0, $offset);
				$sseBuffer = substr($sseBuffer, $offset + strlen($separator));
				$consumeEvent($event);
			}

			if($flush && trim($sseBuffer) !== '') {
				$event = $sseBuffer;
				$sseBuffer = '';
				$consumeEvent($event);
			}
		};

		curl_setopt_array($curl, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_TIMEOUT => $timeoutSeconds,
			CURLOPT_BUFFERSIZE => 16384,
			CURLOPT_HTTPHEADER => [
				$authHeaderName . ': Bearer ' . $secret,
				'Content-Type: application/json',
				'Accept: text/event-stream'
			],
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_HEADERFUNCTION => static function($curl, string $header) use (&$statusCode): int {
				$line = trim($header);
				if(preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $matches) === 1) {
					$statusCode = (int)$matches[1];
				}

				return strlen($header);
			},
			CURLOPT_WRITEFUNCTION => static function($curl, string $chunk) use (
				&$statusCode,
				&$errorBody,
				&$sseBuffer,
				&$streamError,
				$consumeBuffer,
				$stream
			): int {
				$length = strlen($chunk);
				if($stream->isCancelled() || $streamError !== '') {
					return 0;
				}

				if($statusCode >= 200 && $statusCode < 300) {
					$sseBuffer .= $chunk;
					$consumeBuffer();
					return $streamError === '' ? $length : 0;
				}

				if(strlen($errorBody) < 65536) {
					$errorBody .= substr($chunk, 0, 65536 - strlen($errorBody));
				}

				return $length;
			}
		]);

		$result = curl_exec($curl);
		$finalStatusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if($statusCode === 0) {
			$statusCode = $finalStatusCode;
		}
		if($statusCode >= 200 && $statusCode < 300 && $streamError === '') {
			$consumeBuffer(true);
		}
		if($streamError !== '') {
			throw new RuntimeException($streamError);
		}
		if($result === false) {
			if($stream->isCancelled()) {
				return [
					'audioBytes' => $audioBytes,
					'usage' => $usage,
					'doneEvent' => $doneEvent
				];
			}
			throw new RuntimeException('Mistral text-to-speech request failed: ' . $error);
		}
		if($statusCode < 200 || $statusCode >= 300) {
			throw new RuntimeException(
				'Mistral text-to-speech request failed: ' . $this->extractErrorMessage($errorBody, $statusCode)
			);
		}
		if($audioBytes === 0 && !$stream->isCancelled()) {
			throw new RuntimeException('Mistral text-to-speech response is empty.');
		}

		return [
			'audioBytes' => $audioBytes,
			'usage' => $usage,
			'doneEvent' => $doneEvent
		];
	}

	private function extractErrorMessage(string $body, int $statusCode): string {
		$response = json_decode($body, true);
		if(!is_array($response)) {
			$body = trim($body);
			return $body !== '' ? $body : 'HTTP ' . $statusCode;
		}

		$message = $response['message'] ?? $response['error'] ?? null;
		if(is_string($message) && trim($message) !== '') {
			return trim($message);
		}

		if(is_array($message)) {
			$message = $message['message'] ?? null;
			if(is_scalar($message) && trim((string)$message) !== '') {
				return trim((string)$message);
			}
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

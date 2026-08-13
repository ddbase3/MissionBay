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

namespace MissionBay\Service;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IAiServiceTester;
use AssistantFoundation\Api\IImageGenerationModel;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\RealtimeSpeechToTextSessionRequest;
use AssistantFoundation\Dto\TextToSpeechRequest;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IRealtimeSpeechToTextDriver;
use MissionBay\Api\ISearchService;
use MissionBay\Api\ITextToSpeechDriver;
use MissionBay\Api\IVectorStoreService;
use RuntimeException;

/**
 * Executes bounded end-to-end tests for unsaved configured service settings.
 */
final class ConfiguredServiceTestService implements IAiServiceTester {

	private const TESTER_TYPE = 'missionbay-configured-service';
	private const LOG_SCOPE = 'service-test';
	private const PREVIEW_LENGTH = 400;

	public function __construct(
		private readonly ConfiguredServiceRuntimeResolver $runtimeResolver,
		private readonly ILogger $logger
	) {}

	public static function getType(): string {
		return self::TESTER_TYPE;
	}

	public function test(array $config): array {
		$startedAt = microtime(true);
		$serviceId = $this->normalizeKey((string)($config['id'] ?? ''));
		$settings = is_array($config['settings'] ?? null) ? $config['settings'] : [];

		if($serviceId === '') {
			return $this->failure('Missing configured service id.', $startedAt);
		}

		if($settings === []) {
			return $this->failure('Missing configured service settings.', $startedAt, $serviceId);
		}

		$serviceType = $this->normalizeKey((string)($settings['serviceType'] ?? ''));
		$driver = $this->normalizeKey((string)($settings['driver'] ?? ''));
		$connectionId = $this->normalizeKey((string)($settings['connection'] ?? ''));
		$model = trim((string)($settings['model'] ?? ''));

		try {
			$result = match($serviceType) {
				'llm' => $this->testChat($serviceId, $settings),
				'embedding' => $this->testEmbedding($serviceId, $settings),
				'image' => $this->testImage($serviceId, $settings),
				'search' => $this->testSearch($serviceId, $settings),
				'vectorstore' => $this->testVectorStore($serviceId, $settings),
				'stt' => $this->testSpeechToText($serviceId, $settings),
				'tts' => $this->testTextToSpeech($serviceId, $settings),
				default => throw new RuntimeException('No configured service test is available for service type: ' . $serviceType),
			};

			$durationMs = (int)round((microtime(true) - $startedAt) * 1000);
			$details = is_array($result['details'] ?? null) ? $result['details'] : [];
			$details = array_merge([
				'serviceId' => $serviceId,
				'serviceType' => $serviceType,
				'driver' => $driver,
				'connectionId' => $connectionId,
				'model' => $model,
				'durationMs' => $durationMs
			], $details);

			$this->logger->info('Configured service test succeeded.', [
				'scope' => self::LOG_SCOPE,
				'service_id' => $serviceId,
				'service_type' => $serviceType,
				'service_driver' => $driver,
				'connection_id' => $connectionId,
				'duration_ms' => $durationMs
			]);

			return [
				'ok' => true,
				'message' => (string)($result['message'] ?? 'Service test succeeded.'),
				'details' => $details
			];
		}
		catch(\Throwable $e) {
			$durationMs = (int)round((microtime(true) - $startedAt) * 1000);

			$this->logger->error('Configured service test failed.', [
				'scope' => self::LOG_SCOPE,
				'service_id' => $serviceId,
				'service_type' => $serviceType,
				'service_driver' => $driver,
				'connection_id' => $connectionId,
				'duration_ms' => $durationMs,
				'error' => $e->getMessage()
			]);

			return [
				'ok' => false,
				'message' => $e->getMessage(),
				'details' => [
					'serviceId' => $serviceId,
					'serviceType' => $serviceType,
					'driver' => $driver,
					'connectionId' => $connectionId,
					'model' => $model,
					'durationMs' => $durationMs
				]
			];
		}
	}

	/** @param array<string,mixed> $settings */
	private function testChat(string $serviceId, array $settings): array {
		$model = $this->runtimeResolver->resolveSettings(
			$serviceId,
			$settings,
			'llm',
			'llm',
			IAiChatModel::class,
			[
				'max_tokens' => 16,
				'enableThinking' => false,
				'enable_thinking' => false,
				'timeout_seconds' => 15
			]
		);

		if(!$model instanceof IAiChatModel) {
			throw new RuntimeException('Configured chat model could not be initialized.');
		}

		$result = $model->complete([[
			'role' => 'user',
			'content' => 'Reply only: pong'
		]]);
		$content = trim($result->getContent());

		if($content === '') {
			throw new RuntimeException('Chat model returned an empty response.');
		}

		return [
			'message' => 'Chat model responded to ping.',
			'details' => $this->withMetadata([
				'preview' => $this->limitText($content)
			], $result->getMetadata())
		];
	}

	/** @param array<string,mixed> $settings */
	private function testEmbedding(string $serviceId, array $settings): array {
		$model = $this->runtimeResolver->resolveSettings(
			$serviceId,
			$settings,
			'embedding',
			'embedding',
			IAiEmbeddingModel::class
		);

		if(!$model instanceof IAiEmbeddingModel) {
			throw new RuntimeException('Configured embedding model could not be initialized.');
		}

		$result = $model->embedResult(['ping']);
		$embeddings = $result->getEmbeddings();
		$vector = is_array($embeddings[0] ?? null) ? $embeddings[0] : [];

		if($vector === []) {
			throw new RuntimeException('Embedding model returned no vector.');
		}

		return [
			'message' => 'Embedding model responded successfully.',
			'details' => $this->withMetadata([
				'vectorCount' => count($embeddings),
				'dimensions' => count($vector),
				'preview' => count($vector) . ' dimensions returned.'
			], $result->getMetadata())
		];
	}

	/** @param array<string,mixed> $settings */
	private function testImage(string $serviceId, array $settings): array {
		$model = $this->runtimeResolver->resolveSettings(
			$serviceId,
			$settings,
			'image',
			'image',
			IImageGenerationModel::class,
			[
				'n' => 1,
				'size' => '1024x1024',
				'quality' => 'low'
			]
		);

		if(!$model instanceof IImageGenerationModel) {
			throw new RuntimeException('Configured image model could not be initialized.');
		}

		$result = $model->generateResult('Minimal test icon: one black dot centered on a plain white background. No text, no detail.');
		$images = $result->getImages();

		if($images === []) {
			throw new RuntimeException('Image model returned no image.');
		}

		return [
			'message' => 'Image model responded successfully.',
			'details' => $this->withMetadata([
				'imageCount' => count($images),
				'preview' => count($images) . ' image result returned.'
			], $result->getMetadata())
		];
	}

	/** @param array<string,mixed> $settings */
	private function testSearch(string $serviceId, array $settings): array {
		$service = $this->runtimeResolver->resolveSettings(
			$serviceId,
			$settings,
			'search',
			'search',
			ISearchService::class,
			[
				'max_results' => 1,
				'search_context_size' => 'low',
				'timeout_seconds' => 20
			]
		);

		if(!$service instanceof ISearchService) {
			throw new RuntimeException('Configured search service could not be initialized.');
		}

		$result = $service->searchResult('OpenAI', ['max_results' => 1]);
		$answer = trim($result->getAnswer());

		if($answer === '' && $result->getResults() === [] && $result->getCitations() === []) {
			throw new RuntimeException('Search service returned no answer, result or citation.');
		}

		return [
			'message' => 'Web search service responded successfully.',
			'details' => $this->withMetadata([
				'resultCount' => count($result->getResults()),
				'citationCount' => count($result->getCitations()),
				'preview' => $this->limitText($answer !== '' ? $answer : 'Search result returned without answer text.')
			], $result->getMetadata())
		];
	}

	/** @param array<string,mixed> $settings */
	private function testVectorStore(string $serviceId, array $settings): array {
		$service = $this->runtimeResolver->resolveSettings(
			$serviceId,
			$settings,
			'vectorstore',
			'vectorstore',
			IVectorStoreService::class
		);

		if(!$service instanceof IVectorStoreService) {
			throw new RuntimeException('Configured vector store could not be initialized.');
		}

		if(!$service instanceof IAiServiceTester) {
			throw new RuntimeException('The selected vector store driver does not provide a service health test.');
		}

		$result = $service->test($service->getOptions());
		if(($result['ok'] ?? false) !== true) {
			throw new RuntimeException(trim((string)($result['message'] ?? 'Vector store health test failed.')));
		}

		$details = is_array($result['details'] ?? null) ? $result['details'] : [];
		unset($details['apikey'], $details['api_key'], $details['auth_secret'], $details['token'], $details['secret']);

		return [
			'message' => (string)($result['message'] ?? 'Vector store responded successfully.'),
			'details' => $details
		];
	}

	/** @param array<string,mixed> $settings */
	private function testSpeechToText(string $serviceId, array $settings): array {
		$serviceConfig = $this->runtimeResolver->createServiceConfig($serviceId, $settings, 'stt');
		if(trim($serviceConfig->getModel()) === '') {
			throw new RuntimeException('Speech-to-text service has no model: ' . $serviceId);
		}

		$connectionConfig = $this->runtimeResolver->loadConnectionConfig($serviceConfig->getConnectionId());

		if($connectionConfig->getAuthType() !== 'bearer') {
			throw new RuntimeException('Realtime speech-to-text requires bearer authentication.');
		}

		$secret = $this->runtimeResolver->resolveConnectionSecret($connectionConfig);
		if($secret === null || $secret === '') {
			throw new RuntimeException('Connection secret could not be resolved: ' . $connectionConfig->getId());
		}

		$driver = $this->runtimeResolver->resolveDriver(
			$serviceConfig,
			'stt',
			IRealtimeSpeechToTextDriver::class
		);

		if(!$driver instanceof IRealtimeSpeechToTextDriver) {
			throw new RuntimeException('Realtime speech-to-text driver could not be initialized.');
		}

		$options = $serviceConfig->getOptions();
		$language = trim((string)($options['language'] ?? ''));
		$session = $driver->createSession(
			$serviceConfig,
			$connectionConfig,
			$secret,
			new RealtimeSpeechToTextSessionRequest($serviceId, $language)
		);

		if(trim($session->getClientToken()) === '') {
			throw new RuntimeException('Speech-to-text session returned no client token.');
		}

		return [
			'message' => 'Speech-to-text session was created successfully.',
			'details' => [
				'provider' => $session->getProvider(),
				'transport' => $session->getTransport(),
				'audioEncoding' => $session->getAudioEncoding(),
				'sampleRate' => $session->getSampleRate(),
				'expiresAt' => $session->getExpiresAt(),
				'preview' => 'Realtime session created; client token was received and is not displayed.'
			]
		];
	}

	/** @param array<string,mixed> $settings */
	private function testTextToSpeech(string $serviceId, array $settings): array {
		$serviceConfig = $this->runtimeResolver->createServiceConfig($serviceId, $settings, 'tts');
		if(trim($serviceConfig->getModel()) === '') {
			throw new RuntimeException('Text-to-speech service has no model: ' . $serviceId);
		}

		$connectionConfig = $this->runtimeResolver->loadConnectionConfig($serviceConfig->getConnectionId());

		if($connectionConfig->getAuthType() !== 'bearer') {
			throw new RuntimeException('Text-to-speech requires bearer authentication.');
		}

		$secret = $this->runtimeResolver->resolveConnectionSecret($connectionConfig);
		if($secret === null || $secret === '') {
			throw new RuntimeException('Connection secret could not be resolved: ' . $connectionConfig->getId());
		}

		$driver = $this->runtimeResolver->resolveDriver(
			$serviceConfig,
			'tts',
			ITextToSpeechDriver::class
		);

		if(!$driver instanceof ITextToSpeechDriver) {
			throw new RuntimeException('Text-to-speech driver could not be initialized.');
		}

		$result = $driver->synthesize(
			$serviceConfig,
			$connectionConfig,
			$secret,
			new TextToSpeechRequest($serviceId, 'ping')
		);

		$audio = $result->getAudio();
		if($audio === null || $audio->getSize() <= 0) {
			throw new RuntimeException('Text-to-speech service returned no audio data.');
		}

		$mimeType = trim($result->getMimeType());
		if($mimeType === '') {
			$mimeType = $audio->getMimeType();
		}

		return [
			'message' => 'Text-to-speech service returned audio successfully.',
			'details' => [
				'mimeType' => $mimeType,
				'audioBytes' => $audio->getSize(),
				'preview' => $audio->getSize() . ' audio bytes returned.'
			]
		];
	}

	/**
	 * @param array<string,mixed> $details
	 * @return array<string,mixed>
	 */
	private function withMetadata(array $details, AiResultMetadata $metadata): array {
		$durationMs = $metadata->getDurationMs();
		$usage = $metadata->getUsage();

		if($metadata->getProvider() !== '') {
			$details['provider'] = $metadata->getProvider();
		}
		if($metadata->getModel() !== '') {
			$details['resolvedModel'] = $metadata->getModel();
		}
		if($durationMs !== null) {
			$details['providerDurationMs'] = (int)round($durationMs);
		}
		if($usage->getTotalTokens() !== null) {
			$details['totalTokens'] = $usage->getTotalTokens();
		}

		return $details;
	}

	private function failure(string $message, float $startedAt, string $serviceId = ''): array {
		return [
			'ok' => false,
			'message' => $message,
			'details' => [
				'serviceId' => $serviceId,
				'durationMs' => (int)round((microtime(true) - $startedAt) * 1000)
			]
		];
	}

	private function limitText(string $text): string {
		$text = trim($text);
		if(strlen($text) <= self::PREVIEW_LENGTH) {
			return $text;
		}

		return substr($text, 0, self::PREVIEW_LENGTH) . "\n...";
	}

	private function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}

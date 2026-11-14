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

namespace Base3\MissionBay\Test\Service;

use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Api\IAiEmbeddingModel;
use Base3\AssistantFoundation\Api\IRetrievalCollectionDefinition;
use Base3\AssistantFoundation\Api\IImageGenerationModel;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiEmbeddingResult;
use Base3\AssistantFoundation\Dto\AiImageResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\AssistantFoundation\Dto\AiSearchResult;
use Base3\AssistantFoundation\Dto\AiUsage;
use Base3\AssistantFoundation\Dto\RealtimeSpeechToTextSession;
use Base3\AssistantFoundation\Dto\TextToSpeechResult;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\ISearchService;
use Base3\MissionBay\Api\ISpeechToTextDriver;
use Base3\MissionBay\Api\ITextToSpeechDriver;
use Base3\MissionBay\Api\IVectorStoreService;
use Base3\MediaFoundation\Api\IAudioMedia;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use Base3\MissionBay\Service\ConfiguredServiceTestService;
use Base3\MissionBay\ServiceDriver\OpenAiChatServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiEmbeddingServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiImageServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiWebSearchServiceDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiSpeechToTextDriverDefinition;
use Base3\MissionBay\ServiceDriver\OpenAiTextToSpeechDriverDefinition;
use Base3\MissionBay\ServiceDriver\QdrantVectorStoreServiceDriverDefinition;
use PHPUnit\Framework\TestCase;

final class ConfiguredServiceTestExecutionBehaviorTest extends TestCase {

	public function testSuccessfulChatReturnsTrimmedPreview(): void {
		$model = $this->chatModel('  pong  ');
		$result = $this->tester($model)->test($this->config('llm'));
		$this->assertTrue($result['ok']);
		$this->assertSame('Chat model responded to ping.', $result['message']);
		$this->assertSame('pong', $result['details']['preview']);
	}

	public function testChatPingUsesBoundedSingleUserMessage(): void {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willReturnCallback(function(array $messages, array $tools = []): AiChatResult {
			$this->assertSame([['role' => 'user', 'content' => 'Reply only: pong']], $messages);
			$this->assertSame([], $tools);
			return new AiChatResult('pong', [], $this->metadata());
		});
		$this->assertTrue($this->tester($model)->test($this->config('llm'))['ok']);
	}

	public function testChatTesterOverridesMaximumTokensAndDisablesThinking(): void {
		$model = $this->chatModel('pong');
		$options = [];
		$model->method('setOptions')->willReturnCallback(static function(array $value) use (&$options): void { $options = $value; });
		$result = $this->tester($model)->test($this->config('llm'));
		$this->assertTrue($result['ok']);
		$this->assertSame(16, $options['max_tokens']);
		$this->assertFalse($options['enable_thinking']);
		$this->assertSame(15, $options['timeout_seconds']);
	}

	public function testChatMetadataIsNormalizedIntoDetails(): void {
		$model = $this->chatModel('pong', $this->metadata('provider', 'model', 12.7, 11));
		$details = $this->tester($model)->test($this->config('llm'))['details'];
		$this->assertSame('provider', $details['provider']);
		$this->assertSame('model', $details['resolvedModel']);
		$this->assertSame(13, $details['providerDurationMs']);
		$this->assertSame(11, $details['totalTokens']);
	}

	public function testMissingChatMetadataDoesNotInventValues(): void {
		$details = $this->tester($this->chatModel('pong'))->test($this->config('llm'))['details'];
		$this->assertArrayNotHasKey('provider', $details);
		$this->assertArrayNotHasKey('resolvedModel', $details);
		$this->assertArrayNotHasKey('totalTokens', $details);
	}

	public function testChatPreviewIsBoundedAtFourHundredCharacters(): void {
		$details = $this->tester($this->chatModel(str_repeat('x', 500)))->test($this->config('llm'))['details'];
		$this->assertSame(str_repeat('x', 400) . "\n...", $details['preview']);
	}

	public function testChatPreviewDoesNotTruncateExactLimit(): void {
		$details = $this->tester($this->chatModel(str_repeat('x', 400)))->test($this->config('llm'))['details'];
		$this->assertSame(str_repeat('x', 400), $details['preview']);
	}

	public function testWhitespaceChatResponseIsFailure(): void {
		$result = $this->tester($this->chatModel("  \n  "))->test($this->config('llm'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Chat model returned an empty response.', $result['message']);
	}

	public function testChatProviderExceptionBecomesStructuredFailure(): void {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willThrowException(new \RuntimeException('Simulated provider timeout'));
		$result = $this->tester($model)->test($this->config('llm'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Simulated provider timeout', $result['message']);
		$this->assertSame('llm', $result['details']['serviceType']);
	}

	public function testFailureDoesNotReturnProviderDetailsAsSuccess(): void {
		$result = $this->tester($this->chatModel(' '))->test($this->config('llm'));
		$this->assertArrayNotHasKey('preview', $result['details']);
		$this->assertArrayHasKey('durationMs', $result['details']);
	}

	public function testSuccessfulChatResultIncludesNormalizedIdentifiers(): void {
		$result = $this->tester($this->chatModel('pong'))->test($this->config('llm', '  My Service! '));
		$this->assertSame('myservice', $result['details']['serviceId']);
		$this->assertSame('openai-chat', $result['details']['driver']);
		$this->assertSame('provider', $result['details']['connectionId']);
	}

	public function testChatSuccessIsLoggedInServiceTestScope(): void {
		$logged = [];
		$logger = $this->createStub(ILogger::class);
		$logger->method('info')->willReturnCallback(static function(string $message, array $context) use (&$logged): void {
			$logged = ['message' => $message, 'context' => $context];
		});
		$this->tester($this->chatModel('pong'), $logger)->test($this->config('llm'));
		$this->assertSame('Configured service test succeeded.', $logged['message']);
		$this->assertSame('service-test', $logged['context']['scope']);
		$this->assertSame('llm', $logged['context']['service_type']);
	}

	public function testFailureIsLoggedInServiceTestScopeWithoutSecret(): void {
		$logged = [];
		$logger = $this->createStub(ILogger::class);
		$logger->method('error')->willReturnCallback(static function(string $message, array $context) use (&$logged): void {
			$logged = ['message' => $message, 'context' => $context];
		});
		$this->tester($this->chatModel(' '), $logger)->test($this->config('llm'));
		$this->assertSame('Configured service test failed.', $logged['message']);
		$this->assertSame('service-test', $logged['context']['scope']);
		$this->assertSame('Chat model returned an empty response.', $logged['context']['error']);
	}

	public function testEmbeddingReturnsVectorCountAndDimensions(): void {
		$model = $this->embeddingModel([[0.1, 0.2, 0.3], [0.4, 0.5, 0.6]]);
		$result = $this->tester($model)->test($this->config('embedding'));
		$this->assertTrue($result['ok']);
		$this->assertSame(2, $result['details']['vectorCount']);
		$this->assertSame(3, $result['details']['dimensions']);
		$this->assertSame('3 dimensions returned.', $result['details']['preview']);
	}

	public function testEmbeddingPingsExactlyOneInput(): void {
		$model = $this->createStub(IAiEmbeddingModel::class);
		$model->method('embedResult')->willReturnCallback(function(array $texts): AiEmbeddingResult {
			$this->assertSame(['ping'], $texts);
			return new AiEmbeddingResult([[0.1]], $this->metadata());
		});
		$this->assertTrue($this->tester($model)->test($this->config('embedding'))['ok']);
	}

	public function testEmbeddingWithNoVectorsIsRejected(): void {
		$result = $this->tester($this->embeddingModel([]))->test($this->config('embedding'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Embedding model returned no vector.', $result['message']);
	}

	public function testEmbeddingWithEmptyFirstVectorIsRejected(): void {
		$result = $this->tester($this->embeddingModel([[], [0.1]]))->test($this->config('embedding'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Embedding model returned no vector.', $result['message']);
	}

	public function testEmbeddingMetadataIsReportedWithoutInventingTokenUsage(): void {
		$model = $this->createStub(IAiEmbeddingModel::class);
		$model->method('embedResult')->willReturn(new AiEmbeddingResult([[0.1]], $this->metadata('embed-vendor', 'embed-v1')));
		$details = $this->tester($model)->test($this->config('embedding'))['details'];
		$this->assertSame('embed-vendor', $details['provider']);
		$this->assertSame('embed-v1', $details['resolvedModel']);
		$this->assertArrayNotHasKey('totalTokens', $details);
	}

	public function testImageResultReturnsImageCount(): void {
		$result = $this->tester($this->imageModel([['url' => 'https://example.invalid/1.png'], ['url' => 'https://example.invalid/2.png']]))->test($this->config('image'));
		$this->assertTrue($result['ok']);
		$this->assertSame(2, $result['details']['imageCount']);
		$this->assertSame('2 image result returned.', $result['details']['preview']);
	}

	public function testImageServiceRejectsEmptyResults(): void {
		$result = $this->tester($this->imageModel([]))->test($this->config('image'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Image model returned no image.', $result['message']);
	}

	public function testImageUsesBoundedDiagnosticPrompt(): void {
		$model = $this->createStub(IImageGenerationModel::class);
		$model->method('generateResult')->willReturnCallback(function(string $prompt): AiImageResult {
			$this->assertStringContainsString('one black dot', $prompt);
			$this->assertStringContainsString('No text', $prompt);
			return new AiImageResult([['url' => 'https://example.invalid/preview.png']], $this->metadata());
		});
		$this->assertTrue($this->tester($model)->test($this->config('image'))['ok']);
	}

	public function testImageHealthCheckOverridesQualityAndCount(): void {
		$model = $this->imageModel([['url' => 'https://example.invalid/image.png']]);
		$options = [];
		$model->method('setOptions')->willReturnCallback(static function(array $value) use (&$options): void { $options = $value; });
		$this->tester($model)->test($this->config('image'));
		$this->assertSame(1, $options['n']);
		$this->assertSame('low', $options['quality']);
		$this->assertSame('1024x1024', $options['size']);
	}

	public function testSearchAcceptsAnswerWithoutResultRows(): void {
		$result = $this->tester($this->searchModel('  Search answer  '))->test($this->config('search'));
		$this->assertTrue($result['ok']);
		$this->assertSame('Search answer', $result['details']['preview']);
		$this->assertSame(0, $result['details']['resultCount']);
	}

	public function testSearchAcceptsResultsWithoutAnswer(): void {
		$result = $this->tester($this->searchModel('', [['title' => 'Document']]))->test($this->config('search'));
		$this->assertTrue($result['ok']);
		$this->assertSame(1, $result['details']['resultCount']);
		$this->assertSame('Search result returned without answer text.', $result['details']['preview']);
	}

	public function testSearchAcceptsCitationsWithoutAnswerOrResults(): void {
		$result = $this->tester($this->searchModel('', [], [['url' => 'https://example.invalid']]))->test($this->config('search'));
		$this->assertTrue($result['ok']);
		$this->assertSame(1, $result['details']['citationCount']);
	}

	public function testSearchRejectsCompletelyEmptyResponse(): void {
		$result = $this->tester($this->searchModel('   '))->test($this->config('search'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Search service returned no answer, result or citation.', $result['message']);
	}

	public function testSearchPingsKnownTermWithOneResultLimit(): void {
		$model = $this->createStub(ISearchService::class);
		$model->method('searchResult')->willReturnCallback(function(string $query, array $options): AiSearchResult {
			$this->assertSame('OpenAI', $query);
			$this->assertSame(['max_results' => 1], $options);
			return new AiSearchResult($query, 'ok', [], [], $this->metadata());
		});
		$this->assertTrue($this->tester($model)->test($this->config('search'))['ok']);
	}

	public function testSearchHealthCheckAppliesLowContextBudget(): void {
		$model = $this->searchModel('ok');
		$options = [];
		$model->method('setOptions')->willReturnCallback(static function(array $value) use (&$options): void { $options = $value; });
		$this->tester($model)->test($this->config('search'));
		$this->assertSame('low', $options['search_context_size']);
		$this->assertSame(1, $options['max_results']);
		$this->assertSame(20, $options['timeout_seconds']);
	}

	public function testSearchPreviewClipsLongAnswer(): void {
		$result = $this->tester($this->searchModel(str_repeat('a', 500)))->test($this->config('search'));
		$this->assertTrue($result['ok']);
		$this->assertSame(str_repeat('a', 400) . "\n...", $result['details']['preview']);
	}

	public function testNonTestableVectorStoreIsRejectedWithoutContactingBackend(): void {
		$vectorStore = $this->createStub(IVectorStoreService::class);
		$result = $this->tester($vectorStore)->test($this->config('vectorstore'));
		$this->assertFalse($result['ok']);
		$this->assertSame('The selected vector store driver does not provide a service health test.', $result['message']);
	}

	public function testSpeechToTextWithoutModelDoesNotCreateSession(): void {
		$result = $this->tester($this->createStub(ISpeechToTextDriver::class))->test($this->config('stt', 'speech', '  '));
		$this->assertFalse($result['ok']);
		$this->assertSame('Speech-to-text service has no model: speech', $result['message']);
	}

	public function testTextToSpeechWithoutModelDoesNotSynthesize(): void {
		$result = $this->tester($this->createStub(ITextToSpeechDriver::class))->test($this->config('tts', 'speech', '  '));
		$this->assertFalse($result['ok']);
		$this->assertSame('Text-to-speech service has no model: speech', $result['message']);
	}

	public function testSpeechToTextRejectsUnauthenticatedConnection(): void {
		$result = $this->tester($this->createStub(ISpeechToTextDriver::class))->test($this->config('stt'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Realtime speech-to-text requires bearer authentication.', $result['message']);
	}

	public function testTextToSpeechRejectsUnauthenticatedConnection(): void {
		$result = $this->tester($this->createStub(ITextToSpeechDriver::class))->test($this->config('tts'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Text-to-speech requires bearer authentication.', $result['message']);
	}

	public function testSpeechToTextFailsIfBearerSecretIsMissing(): void {
		$result = $this->tester($this->createStub(ISpeechToTextDriver::class), null, $this->bearerConnection(false))->test($this->config('stt'));
		$this->assertFalse($result['ok']);
		$this->assertStringContainsString('no secret config:', $result['message']);
	}

	public function testTextToSpeechFailsIfBearerSecretIsMissing(): void {
		$result = $this->tester($this->createStub(ITextToSpeechDriver::class), null, $this->bearerConnection(false))->test($this->config('tts'));
		$this->assertFalse($result['ok']);
		$this->assertStringContainsString('no secret config:', $result['message']);
	}

	public function testSpeechToTextSessionRejectsBlankClientToken(): void {
		$driver = $this->createStub(ISpeechToTextDriver::class);
		$driver->method('createSession')->willReturn($this->session(' '));
		$result = $this->tester($driver, null, $this->bearerConnection())->test($this->config('stt'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Speech-to-text session returned no client token.', $result['message']);
	}

	public function testSpeechToTextSessionReportsMetadataButNeverClientSecret(): void {
		$driver = $this->createStub(ISpeechToTextDriver::class);
		$driver->method('createSession')->willReturn($this->session('PRIVATE_CLIENT_TOKEN'));
		$result = $this->tester($driver, null, $this->bearerConnection())->test($this->config('stt'));
		$this->assertTrue($result['ok']);
		$this->assertSame('vendor', $result['details']['provider']);
		$this->assertSame('websocket', $result['details']['transport']);
		$this->assertSame(24000, $result['details']['sampleRate']);
		$this->assertStringNotContainsString('PRIVATE_CLIENT_TOKEN', json_encode($result));
	}

	public function testSpeechToTextRequestKeepsServiceIdentityAndLanguage(): void {
		$driver = $this->createStub(ISpeechToTextDriver::class);
		$driver->method('createSession')->willReturnCallback(function($service, $connection, string $secret, $request): RealtimeSpeechToTextSession {
			$this->assertSame('speech', $request->getServiceId());
			$this->assertSame('de', $request->getLanguage());
			$this->assertSame('local-secret', $secret);
			return $this->session('TOKEN');
		});
		$config = $this->config('stt', 'speech');
		$config['settings']['options']['language'] = ' de ';
		$this->assertTrue($this->tester($driver, null, $this->bearerConnection())->test($config)['ok']);
	}

	public function testTextToSpeechRejectsMissingAudioPayload(): void {
		$driver = $this->createStub(ITextToSpeechDriver::class);
		$driver->method('synthesize')->willReturn(new TextToSpeechResult('audio/mpeg'));
		$result = $this->tester($driver, null, $this->bearerConnection())->test($this->config('tts'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Text-to-speech service returned no audio data.', $result['message']);
	}

	public function testSpeechToTextBackendErrorsAreReturnedWithoutSessionData(): void {
		$driver = $this->createStub(ISpeechToTextDriver::class);
		$driver->method('createSession')->willThrowException(new \RuntimeException('Provider unavailable'));
		$result = $this->tester($driver, null, $this->bearerConnection())->test($this->config('stt'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Provider unavailable', $result['message']);
		$this->assertArrayNotHasKey('clientToken', $result['details']);
	}


	public function testVectorStoreHealthCheckReturnsSafeDetails(): void {
		$store = $this->vectorStore(['ok' => true, 'details' => [
			'collectionCount' => 3,
			'apikey' => 'private-1',
			'api_key' => 'private-2',
			'auth_secret' => 'private-3',
			'token' => 'private-4',
			'secret' => 'private-5'
		]]);
		$result = $this->tester($store)->test($this->config('vectorstore'));
		$this->assertTrue($result['ok']);
		$this->assertSame(3, $result['details']['collectionCount']);
		foreach(['apikey', 'api_key', 'auth_secret', 'token', 'secret'] as $key) {
			$this->assertArrayNotHasKey($key, $result['details']);
		}
	}

	public function testVectorStoreReturnsCustomSuccessfulHealthMessage(): void {
		$store = $this->vectorStore(['ok' => true, 'message' => 'Connected', 'details' => ['count' => 4]]);
		$result = $this->tester($store)->test($this->config('vectorstore'));
		$this->assertTrue($result['ok']);
		$this->assertSame('Connected', $result['message']);
		$this->assertSame(4, $result['details']['count']);
	}

	public function testVectorStoreHealthCheckRejectsExplicitFailure(): void {
		$store = $this->vectorStore(['ok' => false, 'message' => 'Collection index inaccessible']);
		$result = $this->tester($store)->test($this->config('vectorstore'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Collection index inaccessible', $result['message']);
	}

	public function testVectorStoreHealthCheckRejectsMissingSuccessFlag(): void {
		$store = $this->vectorStore(['details' => []]);
		$result = $this->tester($store)->test($this->config('vectorstore'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Vector store health test failed.', $result['message']);
	}

	public function testTextToSpeechReturnsAudioSizeAndMimeType(): void {
		$driver = $this->createStub(ITextToSpeechDriver::class);
		$driver->method('synthesize')->willReturn(new TextToSpeechResult('audio/mpeg', $this->audio(17)));
		$result = $this->tester($driver, null, $this->bearerConnection())->test($this->config('tts'));
		$this->assertTrue($result['ok']);
		$this->assertSame('audio/mpeg', $result['details']['mimeType']);
		$this->assertSame(17, $result['details']['audioBytes']);
		$this->assertSame('17 audio bytes returned.', $result['details']['preview']);
	}

	public function testTextToSpeechUsesAudioMimeTypeWhenResultMimeTypeBlank(): void {
		$driver = $this->createStub(ITextToSpeechDriver::class);
		$driver->method('synthesize')->willReturn(new TextToSpeechResult('  ', $this->audio(8, 'audio/wav')));
		$result = $this->tester($driver, null, $this->bearerConnection())->test($this->config('tts'));
		$this->assertTrue($result['ok']);
		$this->assertSame('audio/wav', $result['details']['mimeType']);
	}

	public function testTextToSpeechRejectsZeroByteAudio(): void {
		$driver = $this->createStub(ITextToSpeechDriver::class);
		$driver->method('synthesize')->willReturn(new TextToSpeechResult('audio/wav', $this->audio(0)));
		$result = $this->tester($driver, null, $this->bearerConnection())->test($this->config('tts'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Text-to-speech service returned no audio data.', $result['message']);
	}

	public function testTextToSpeechRequestKeepsServiceIdentityAndSecret(): void {
		$driver = $this->createStub(ITextToSpeechDriver::class);
		$driver->method('synthesize')->willReturnCallback(function($service, $connection, string $secret, $request): TextToSpeechResult {
			$this->assertSame('speech', $request->getServiceId());
			$this->assertSame('ping', $request->getText());
			$this->assertSame('local-secret', $secret);
			return new TextToSpeechResult('audio/mpeg', $this->audio(10));
		});
		$this->assertTrue($this->tester($driver, null, $this->bearerConnection())->test($this->config('tts', 'speech'))['ok']);
	}

	public function testTextToSpeechBackendErrorIsReturnedWithoutAudio(): void {
		$driver = $this->createStub(ITextToSpeechDriver::class);
		$driver->method('synthesize')->willThrowException(new \RuntimeException('Speech provider failed'));
		$result = $this->tester($driver, null, $this->bearerConnection())->test($this->config('tts'));
		$this->assertFalse($result['ok']);
		$this->assertSame('Speech provider failed', $result['message']);
		$this->assertArrayNotHasKey('audioBytes', $result['details']);
	}

	/** @param array<int,array<int,float>> $vectors */
	private function embeddingModel(array $vectors): IAiEmbeddingModel {
		$model = $this->createStub(IAiEmbeddingModel::class);
		$model->method('embedResult')->willReturn(new AiEmbeddingResult($vectors, $this->metadata()));
		return $model;
	}

	private function chatModel(string $content, ?AiResultMetadata $metadata = null): IAiChatModel {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willReturn(new AiChatResult($content, [], $metadata ?? $this->metadata()));
		return $model;
	}

	/** @param array<int,array<string,mixed>> $images */
	private function imageModel(array $images): IImageGenerationModel {
		$model = $this->createStub(IImageGenerationModel::class);
		$model->method('generateResult')->willReturn(new AiImageResult($images, $this->metadata()));
		return $model;
	}

	/** @param array<int,array<string,mixed>> $results @param array<int,array<string,mixed>> $citations */
	private function searchModel(string $answer, array $results = [], array $citations = []): ISearchService {
		$model = $this->createStub(ISearchService::class);
		$model->method('searchResult')->willReturn(new AiSearchResult('OpenAI', $answer, $results, $citations, $this->metadata()));
		return $model;
	}


	/** @param array<string,mixed> $health */
	private function vectorStore(array $health): ConfiguredServiceTestVectorStoreDouble {
		return new ConfiguredServiceTestVectorStoreDouble(
			$this->createStub(IRetrievalCollectionDefinition::class),
			$health
		);
	}

	private function audio(int $size, string $mimeType = 'audio/mpeg'): IAudioMedia {
		$audio = $this->createStub(IAudioMedia::class);
		$audio->method('getSize')->willReturn($size);
		$audio->method('getMimeType')->willReturn($mimeType);
		return $audio;
	}

	private function metadata(string $provider = '', string $model = '', ?float $durationMs = null, ?int $tokens = null): AiResultMetadata {
		return new AiResultMetadata('service-health', $provider, $model, '', null, $durationMs, null,
			$tokens !== null ? new AiUsage(2, 3, $tokens) : null
		);
	}

	/** @return array<string,mixed> */
	private function config(string $type, string $id = 'service', string $model = 'model-v1'): array {
		$driver = match($type) {
			'llm' => 'openai-chat',
			'embedding' => 'openai-embedding',
			'image' => 'openai-image',
			'search' => 'openai-websearch',
			'vectorstore' => 'qdrant-vectorstore',
			'stt' => 'openai-stt',
			'tts' => 'openai-tts',
		};
		return ['id' => $id, 'settings' => [
			'serviceType' => $type,
			'connection' => 'provider',
			'driver' => $driver,
			'model' => $model,
			'enabled' => true
		]];
	}

	/** @return array<string,mixed> */
	private function bearerConnection(bool $withSecret = true): array {
		$auth = ['type' => 'bearer'];
		if($withSecret) {
			$auth['secret'] = ['mode' => 'fixed', 'value' => 'local-secret'];
		}
		return ['baseUrl' => 'https://example.invalid', 'type' => 'http', 'enabled' => true, 'auth' => $auth];
	}

	private function session(string $token): RealtimeSpeechToTextSession {
		return new RealtimeSpeechToTextSession('vendor', 'websocket', 'wss://example.invalid/realtime', $token,
			'2099-01-01', 'voice-model', 'pcm16', 24000
		);
	}

	/** @param array<string,mixed>|null $connection */
	private function tester(object $runtimeService, ?ILogger $logger = null, ?array $connection = null): ConfiguredServiceTestService {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([
			new OpenAiChatServiceDriverDefinition(),
			new OpenAiEmbeddingServiceDriverDefinition(),
			new OpenAiImageServiceDriverDefinition(),
			new OpenAiWebSearchServiceDriverDefinition(),
			new QdrantVectorStoreServiceDriverDefinition(),
			new OpenAiSpeechToTextDriverDefinition(),
			new OpenAiTextToSpeechDriverDefinition()
		]);
		$map->method('getClassByInterfaceName')->willReturn('local-test-service');
		$map->method('instantiate')->willReturn($runtimeService);
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn($connection ?? [
			'baseUrl' => 'https://example.invalid', 'type' => 'http', 'enabled' => true,
			'auth' => ['type' => 'none']
		]);
		$secret = $this->createStub(IAgentConfigValueResolver::class);
		$secret->method('resolveValue')->willReturn('local-secret');
		return new ConfiguredServiceTestService(
			new ConfiguredServiceRuntimeResolver($settings, $map, $secret),
			$logger ?? $this->createStub(ILogger::class)
		);
	}
}

require_once __DIR__ . '/ConfiguredServiceTestVectorStoreDouble.php';

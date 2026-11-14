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

namespace Base3\MissionBay\Test\ChatModel;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\ChatModel\OpenAiCompatibleChatModel;
use Base3\MissionBay\Transport\OpenAiCompatibleTransport;
use PHPUnit\Framework\TestCase;

final class OpenAiCompatibleChatModelBehaviorCoverageTest extends TestCase {

	private function model(array $options = []): OpenAiCompatibleChatModel {
		$model = new OpenAiCompatibleChatModel($this->createStub(IClassMap::class),
			new AiProviderRequestEventDispatcher($this->createStub(IEventManager::class)));
		$model->setOptions($options);
		return $model;
	}

	private function payload(OpenAiCompatibleChatModel $model, array $messages, array $tools = [], bool $stream = false): array {
		return (new \ReflectionMethod($model, 'buildPayload'))->invoke($model, $messages, $tools, $stream);
	}

	public function testAdapterIdentityResolvesToCompatibleTransport(): void {
		$model = $this->model();
		$this->assertSame('openaicompatiblechatmodel', $model::getName());
		$this->assertSame(OpenAiCompatibleTransport::getName(), (new \ReflectionMethod($model, 'getProviderName'))->invoke($model));
	}

	public function testMissingModelIsRejectedBeforeAnyProviderRequest(): void {
		$this->expectException(\RuntimeException::class);
		$this->payload($this->model(), [['role' => 'user', 'content' => 'Hello']]);
	}

	public function testMinimalChatPayloadUsesTheConfiguredModel(): void {
		$this->assertSame(['model' => 'test-model', 'messages' => [['role' => 'user', 'content' => 'Hello']]],
			$this->payload($this->model(['model' => 'test-model']), [['role' => 'user', 'content' => 'Hello']]));
	}

	public function testStreamingRequestsSetTheStreamFlag(): void {
		$payload = $this->payload($this->model(['model' => 'test-model']), [['role' => 'user', 'content' => 'Hello']], [], true);
		$this->assertTrue($payload['stream']);
	}

	public function testModelSamplingAndTokenParametersAreNormalized(): void {
		$payload = $this->payload($this->model(['model' => 'test-model', 'temperature' => '0.3', 'max_tokens' => '500', 'top_p' => '0.9']), []);
		$this->assertSame(0.3, $payload['temperature']);
		$this->assertSame(500, $payload['max_tokens']);
		$this->assertSame(0.9, $payload['top_p']);
	}

	public function testInvalidSamplingSettingsAreOmitted(): void {
		$payload = $this->payload($this->model(['model' => 'test-model', 'temperature' => 'invalid', 'max_tokens' => 'wrong']), []);
		$this->assertArrayNotHasKey('temperature', $payload);
		$this->assertArrayNotHasKey('max_tokens', $payload);
	}

	public function testChatTemplateKeywordArgumentsMustBeAnObject(): void {
		$this->expectException(\RuntimeException::class);
		$this->payload($this->model(['model' => 'test-model', 'chat_template_kwargs' => 'not-an-array']), []);
	}

	public function testChatTemplateKeywordArgumentsArePassedWithoutMutation(): void {
		$payload = $this->payload($this->model(['model' => 'test-model', 'chat_template_kwargs' => ['enable_thinking' => false]]), []);
		$this->assertSame(['enable_thinking' => false], $payload['chat_template_kwargs']);
	}

	public function testOrphanedToolResponsesAreRemovedBeforeModelSubmission(): void {
		$payload = $this->payload($this->model(['model' => 'test-model']), [
			['role' => 'tool', 'tool_call_id' => 'unknown', 'content' => 'stale'],
			['role' => 'user', 'content' => 'Hello']
		]);
		$this->assertSame([['role' => 'user', 'content' => 'Hello']], $payload['messages']);
	}

	public function testRequestTimeoutOptionsUseExplicitConfiguredValues(): void {
		$model = $this->model(['model' => 'test-model', 'timeout_seconds' => 42, 'connect_timeout_seconds' => 6]);
		$this->assertSame(['timeout' => 42, 'connect_timeout' => 6],
			(new \ReflectionMethod($model, 'buildRequestOptions'))->invoke($model, false));
	}

	public function testChangingOptionsDoesNotDiscardExistingModel(): void {
		$model = $this->model(['model' => 'test-model', 'temperature' => 0.2]);
		$model->setOptions(['top_p' => 0.8]);
		$this->assertSame(['model' => 'test-model', 'temperature' => 0.2, 'top_p' => 0.8], $model->getOptions());
	}
}

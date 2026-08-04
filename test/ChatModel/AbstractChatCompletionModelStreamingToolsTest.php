<?php declare(strict_types=1);

namespace MissionBay\Test\ChatModel;

use AssistantFoundation\Api\IAiProvider;
use Base3\Api\IClassMap;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\ChatModel\MistralChatModel;
use PHPUnit\Framework\TestCase;

final class AbstractChatCompletionModelStreamingToolsTest extends TestCase {

	public function testStreamingPayloadKeepsToolDefinitionsForConfiguredModels(): void {
		$provider = new CapturingStreamingProvider();
		$model = new MistralChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'mistral-small-2603',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key',
			'temperature' => 0.3,
			'max_tokens' => 20000
		]);

		$tools = [[
			'type' => 'function',
			'function' => [
				'name' => 'get_global_webdav_status',
				'description' => 'Returns the current global WebDAV state.',
				'parameters' => [
					'type' => 'object',
					'properties' => []
				]
			]
		]];

		$result = $model->streamResult(
			[['role' => 'user', 'content' => 'What is the WebDAV status?']],
			$tools,
			static function(string $delta): void {},
			static function(array $metadata): void {}
		);

		$this->assertSame($tools, $provider->payload['tools'] ?? null);
		$this->assertSame('auto', $provider->payload['tool_choice'] ?? null);
		$this->assertTrue($provider->payload['stream'] ?? false);
		$this->assertCount(1, $result->getToolCalls());
		$this->assertSame('get_global_webdav_status', $result->getToolCalls()[0]->getName());
		$this->assertSame([], $result->getToolCalls()[0]->getArguments());
	}

	public function testStreamingPayloadIncludesConfiguredChatTemplateKwargs(): void {
		$provider = new CapturingStreamingProvider(false);
		$model = new MistralChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'qwen35-9b',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key',
			'chat_template_kwargs' => [
				'enable_thinking' => true
			]
		]);

		$model->streamResult(
			[['role' => 'user', 'content' => 'Think before answering.']],
			[],
			static function(string $delta): void {},
			static function(array $metadata): void {}
		);

		$this->assertSame(
			['enable_thinking' => true],
			$provider->payload['chat_template_kwargs'] ?? null
		);
	}

	public function testStreamingReasoningDeltasRemainHiddenAndEmitLifecycleMetadata(): void {
		$provider = new ReasoningStreamingProvider();
		$model = new MistralChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'qwen35-9b',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key'
		]);
		$deltas = [];
		$metadataEvents = [];

		$result = $model->streamResult(
			[['role' => 'user', 'content' => 'Answer after reasoning.']],
			[],
			function(string $delta) use (&$deltas): void {
				$deltas[] = $delta;
			},
			function(array $metadata) use (&$metadataEvents): void {
				$metadataEvents[] = $metadata;
			}
		);

		$this->assertSame(['Final answer.'], $deltas);
		$this->assertSame('Final answer.', $result->getContent());
		$this->assertSame('stop', $result->getMetadata()->getFinishReason());
		$this->assertSame('reasoning_start', $metadataEvents[0]['event'] ?? null);
		$this->assertFalse($metadataEvents[0]['visible'] ?? true);
		$this->assertSame('reasoning_end', $metadataEvents[1]['event'] ?? null);
		$this->assertFalse($metadataEvents[1]['visible'] ?? true);
		$this->assertSame(16, $metadataEvents[1]['bytes'] ?? null);
		$this->assertStringNotContainsString('Thinking quietly', json_encode($result->getRaw()) ?: '');
	}

	public function testStreamingPayloadWithoutToolsRemainsToolFree(): void {
		$provider = new CapturingStreamingProvider(false);
		$model = new MistralChatModel(
			new SingleProviderClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
		$model->setOptions([
			'model' => 'mistral-small-2603',
			'endpoint' => 'https://example.test',
			'apikey' => 'test-key'
		]);

		$result = $model->streamResult(
			[['role' => 'user', 'content' => 'Hi']],
			[],
			static function(string $delta): void {},
			static function(array $metadata): void {}
		);

		$this->assertArrayNotHasKey('tools', $provider->payload);
		$this->assertArrayNotHasKey('tool_choice', $provider->payload);
		$this->assertSame('Hello!', $result->getContent());
		$this->assertFalse($result->hasToolCalls());
	}
}

final class ReasoningStreamingProvider implements IAiProvider {

	/** @var array<string,mixed> */
	private array $options = [];

	public static function getName(): string {
		return 'reasoningstreamingprovider';
	}

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function request(string $path, array $payload, array $options = []): array {
		return [];
	}

	public function stream(string $path, array $payload, callable $onChunk, array $options = []): void {
		$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{\"role\":\"assistant\",\"content\":\"\"},\"finish_reason\":null}]}\n\n");
		$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{\"reasoning\":\"Thinking\"},\"finish_reason\":null}]}\n\n");
		$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{\"reasoning_content\":\" quietly\"},\"finish_reason\":null}]}\n\n");
		$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{\"content\":\"Final answer.\"},\"finish_reason\":null}]}\n\n");
		$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}]}\n\n");
		$onChunk("data: [DONE]\n\n");
	}
}

final class CapturingStreamingProvider implements IAiProvider {

	/** @var array<string,mixed> */
	public array $payload = [];

	/** @var array<string,mixed> */
	private array $options = [];

	public function __construct(
		private readonly bool $returnToolCall = true
	) {}

	public static function getName(): string {
		return 'capturingstreamingprovider';
	}

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function request(string $path, array $payload, array $options = []): array {
		$this->payload = $payload;
		return [];
	}

	public function stream(string $path, array $payload, callable $onChunk, array $options = []): void {
		$this->payload = $payload;

		if(!$this->returnToolCall) {
			$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{\"content\":\"Hello!\"},\"finish_reason\":null}]}\n\n");
			$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}]}\n\n");
			$onChunk("data: [DONE]\n\n");
			return;
		}

		$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{\"tool_calls\":[{\"index\":0,\"id\":\"call-webdav\",\"type\":\"function\",\"function\":{\"name\":\"get_global_webdav_status\",\"arguments\":\"{}\"}}]},\"finish_reason\":null}]}\n\n");
		$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"tool_calls\"}]}\n\n");
		$onChunk("data: [DONE]\n\n");
	}
}

final class SingleProviderClassMap implements IClassMap {

	/** @var array<int,object> */
	private array $emptyInstances = [];

	private mixed $emptyInstance = null;

	public function __construct(
		private readonly IAiProvider $provider
	) {}

	public function instantiate(string $class) {
		return null;
	}

	public function instantiateWith(string $class, array $arguments = []) {
		return null;
	}

	public function generate($regenerate = false): void {}

	public function getApps() {
		return [];
	}

	public function &getInstances(array $criteria = []) {
		return $this->emptyInstances;
	}

	public function &getInstancesByInterface($interface) {
		return $this->emptyInstances;
	}

	public function &getInstancesByAppInterface($app, $interface, $retry = false) {
		return $this->emptyInstances;
	}

	public function &getInstanceByAppName($app, $name, $retry = false) {
		return $this->emptyInstance;
	}

	public function getClassByInterfaceName(string $interface, string $name): ?string {
		return null;
	}

	public function &getInstanceByInterfaceName($interface, $name, $retry = false) {
		if($interface === IAiProvider::class) {
			$provider = $this->provider;
			return $provider;
		}

		return $this->emptyInstance;
	}

	public function &getInstanceByAppInterfaceName($app, $interface, $name, $retry = false) {
		return $this->getInstanceByInterfaceName($interface, $name, $retry);
	}

	public function getPlugins() {
		return [];
	}
}

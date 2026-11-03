<?php declare(strict_types=1);

namespace MissionBay\Test\ChatModel;

use AssistantFoundation\Api\IAiProvider;
use Base3\Api\IClassMap;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\ChatModel\MistralChatModel;
use MissionBay\ChatModel\OpenAiChatModel;
use MissionBay\ChatModel\OpenAiCompatibleChatModel;
use PHPUnit\Framework\TestCase;

final class MixedContentToolStreamingProvider implements IAiProvider {

	/** @var array<string,mixed> */
	private array $options = [];

	public static function getName(): string {
		return 'mixedcontenttoolstreamingprovider';
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
		$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{\"content\":\"This must not be published before tool metadata.\",\"tool_calls\":[{\"index\":0,\"id\":\"call-lookup\",\"type\":\"function\",\"function\":{\"name\":\"lookup\",\"arguments\":\"{}\"}}]},\"finish_reason\":null}]}\n\n");
		$onChunk("data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"tool_calls\"}]}\n\n");
		$onChunk("data: [DONE]\n\n");
	}
}

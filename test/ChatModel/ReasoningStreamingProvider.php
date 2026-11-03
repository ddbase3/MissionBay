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

<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Orchestrator\AgentModelDecisionAssessment;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class ModelDecisionQueueChatModel implements IAiChatModel {

	/** @var array<int,AiChatResult> */
	private array $results;
	/** @var array<int,array<int,string>> */
	private array $streamChunks;
	private int $completeCalls = 0;
	private int $streamCalls = 0;
	private array $options = [];
	private array $lastMessages = [];
	private array $lastTools = [];
	private ?\Throwable $streamError;
	/** @var array<int,array<int,array<string,mixed>>> */
	private array $streamMetadataEvents;

	/**
	 * @param array<int,AiChatResult> $results
	 * @param array<int,array<int,string>> $streamChunks
	 * @param array<int,array<int,array<string,mixed>>> $streamMetadataEvents
	 */
	public function __construct(
		array $results,
		array $streamChunks = [],
		?\Throwable $streamError = null,
		array $streamMetadataEvents = []
	) {
		$this->results = array_values($results);
		$this->streamChunks = array_values($streamChunks);
		$this->streamError = $streamError;
		$this->streamMetadataEvents = array_values($streamMetadataEvents);
	}

	public function complete(array $messages, array $tools = []): AiChatResult {
		$this->completeCalls++;
		$this->lastMessages = $messages;
		$this->lastTools = $tools;
		return $this->nextResult();
	}

	public function getCompleteCalls(): int {
		return $this->completeCalls;
	}

	public function getStreamCalls(): int {
		return $this->streamCalls;
	}

	public function getLastMessages(): array {
		return $this->lastMessages;
	}

	public function getLastTools(): array {
		return $this->lastTools;
	}

	public function chat(array $messages): string { return $this->complete($messages)->getContent(); }
	public function raw(array $messages, array $tools = []): mixed { return $this->complete($messages, $tools); }
	public function streamResult(array $messages, array $tools, callable $onData, ?callable $onMeta = null): AiChatResult {
		$this->streamCalls++;
		$this->lastMessages = $messages;
		$this->lastTools = $tools;
		$result = $this->nextResult();
		$metadataEvents = array_shift($this->streamMetadataEvents);
		foreach (is_array($metadataEvents) ? $metadataEvents : [] as $metadata) {
			if ($onMeta !== null) {
				$onMeta($metadata);
			}
		}
		$chunks = array_shift($this->streamChunks);
		if (!is_array($chunks)) {
			$chunks = $result->hasToolCalls() ? [] : [$result->getContent()];
		}
		foreach ($chunks as $chunk) {
			$onData($chunk);
		}
		if ($this->streamError !== null) {
			throw $this->streamError;
		}
		return $result;
	}
	public function stream(array $messages, array $tools, callable $onData, ?callable $onMeta = null): void {
		$this->streamResult($messages, $tools, $onData, $onMeta);
	}
	public function setOptions(array $options): void { $this->options = $options; }
	public function getOptions(): array { return $this->options; }

	private function nextResult(): AiChatResult {
		$result = array_shift($this->results);
		if (!$result instanceof AiChatResult) {
			throw new \RuntimeException('No queued model decision result available.');
		}
		return $result;
	}
}

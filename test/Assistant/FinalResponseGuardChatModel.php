<?php declare(strict_types=1);

namespace MissionBay\Test\Assistant;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Dto\Orchestrator\AgentModelDecisionAssessment;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;
use MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use PHPUnit\Framework\TestCase;

final class FinalResponseGuardChatModel implements IAiChatModel {

	/** @var array<int,AiChatResult> */
	private array $completeResults;
	private int $completeCalls = 0;
	private int $streamCalls = 0;
	private array $options = [];

	/** @param array<int,AiChatResult> $completeResults */
	public function __construct(array $completeResults, private readonly ?AiChatResult $streamResult = null) {
		$this->completeResults = array_values($completeResults);
	}

	public function complete(array $messages, array $tools = []): AiChatResult {
		$this->completeCalls++;
		$result = array_shift($this->completeResults);
		if (!$result instanceof AiChatResult) {
			throw new \RuntimeException('No queued final response result available.');
		}
		return $result;
	}

	public function getCompleteCalls(): int { return $this->completeCalls; }
	public function getStreamCalls(): int { return $this->streamCalls; }
	public function chat(array $messages): string { return $this->complete($messages)->getContent(); }
	public function raw(array $messages, array $tools = []): mixed { return $this->complete($messages, $tools); }
	public function streamResult(array $messages, array $tools, callable $onData, ?callable $onMeta = null): AiChatResult {
		$this->streamCalls++;
		if (!$this->streamResult instanceof AiChatResult) {
			throw new \RuntimeException('No queued streaming result available.');
		}
		$content = $this->streamResult->getContent();
		$mid = max(1, intdiv(strlen($content), 2));
		$onData(substr($content, 0, $mid));
		$onData(substr($content, $mid));
		return $this->streamResult;
	}
	public function stream(array $messages, array $tools, callable $onData, ?callable $onMeta = null): void {
		$this->streamResult($messages, $tools, $onData, $onMeta);
	}
	public function setOptions(array $options): void { $this->options = $options; }
	public function getOptions(): array { return $this->options; }
}

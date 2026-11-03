<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Api\IAgentCapabilitySourceMetadata;
use MissionBay\Api\IAgentTool;
use MissionBay\Capability\AgentCapabilityCatalogBuilder;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Decision\AgentSelectedNativeModelDecisionStrategy;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentSelectedNativeQueueModel implements IAiChatModel {

	/** @var array<int,AiChatResult> */
	private array $results;
	private array $toolSets = [];
	private array $messageSets = [];
	private array $options = [];

	/** @param array<int,AiChatResult> $results */
	public function __construct(array $results) {
		$this->results = array_values($results);
	}

	public function complete(array $messages, array $tools = []): AiChatResult {
		return $this->nextResult($messages, $tools);
	}

	public function chat(array $messages): string {
		return $this->complete($messages)->getContent();
	}

	public function raw(array $messages, array $tools = []): mixed {
		return $this->complete($messages, $tools);
	}

	public function streamResult(array $messages, array $tools, callable $onData, ?callable $onMeta = null): AiChatResult {
		$result = $this->nextResult($messages, $tools);
		if (!$result->hasToolCalls() && $result->getContent() !== '') {
			$onData($result->getContent());
		}
		return $result;
	}

	public function stream(array $messages, array $tools, callable $onData, ?callable $onMeta = null): void {
		$this->streamResult($messages, $tools, $onData, $onMeta);
	}

	public function setOptions(array $options): void {
		$this->options = $options;
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function getToolSets(): array {
		return $this->toolSets;
	}

	public function getMessageSets(): array {
		return $this->messageSets;
	}

	private function nextResult(array $messages, array $tools): AiChatResult {
		$this->messageSets[] = $messages;
		$this->toolSets[] = $tools;
		$result = array_shift($this->results);
		if (!$result instanceof AiChatResult) {
			throw new \RuntimeException('No queued agent-selected native model result available.');
		}
		return $result;
	}
}

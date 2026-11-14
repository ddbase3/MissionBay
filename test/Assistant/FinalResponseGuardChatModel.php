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

namespace Base3\MissionBay\Test\Assistant;

use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionAssessment;
use Base3\MissionBay\Orchestrator\AgentToolOrchestratorResult;
use Base3\MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMessageFactory;
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

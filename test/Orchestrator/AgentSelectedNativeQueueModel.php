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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Api\IAgentCapabilitySourceMetadata;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Capability\AgentCapabilityCatalogBuilder;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Orchestrator\Decision\AgentSelectedNativeModelDecisionStrategy;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
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

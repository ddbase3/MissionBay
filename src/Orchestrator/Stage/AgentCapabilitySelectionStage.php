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

namespace MissionBay\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentCapabilitySelector;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use AssistantFoundation\Dto\AgentStageResult;

/**
 * Chooses the context-relevant subset of the agent's run-local capability
 * catalog immediately before a model decision.
 */
final class AgentCapabilitySelectionStage implements IAgentStage {

	public function __construct(
		private readonly IAgentCapabilitySelector $selector,
		private readonly string $id = 'capability-selection',
		private readonly string $stageName = 'capability-selection',
		private readonly int $maxContextCharacters = 24000
	) {
		if ($this->maxContextCharacters < 1000) {
			throw new \InvalidArgumentException('Capability selection context limit must be at least 1000 characters.');
		}
	}

	public static function getName(): string {
		return 'agentcapabilityselectionstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		return 'Selects a bounded context-relevant tool subset from the run-specific capability catalog before each model decision.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_MODEL
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$catalog = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_CATALOG);
		$config = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_SELECTION_CONFIG);
		$selections = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_SELECTIONS);
		$previous = $context->getVar(AgentToolLoopContextKeys::SELECTED_TOOL_NAMES);
		$required = $context->getVar(AgentToolLoopContextKeys::REQUIRED_TOOL_NAMES);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);

		if (!$catalog instanceof AgentCapabilityCatalog) {
			return $this->failure('capability_catalog_missing', 'Capability selection stage did not receive a run-specific catalog.');
		}
		if (!$config instanceof AgentCapabilitySelectionConfig) {
			$config = new AgentCapabilitySelectionConfig();
		}
		if (!is_array($selections)) {
			$selections = [];
		}
		if (!is_array($previous)) {
			$previous = [];
		}
		if (!is_array($required)) {
			$required = [];
		}

		try {
			$selection = $this->selector->select(
				$catalog,
				new AgentCapabilitySelectionRequest(
					iteration: $iteration,
					contextText: $this->buildContextText($context),
					config: $config,
					previousSelectedToolNames: $previous,
					recentToolNames: $this->recentToolNames($context),
					requiredToolNames: $required
				)
			);
		} catch (\Throwable $e) {
			return $this->failure(
				'capability_selection_failed',
				'Capability selection failed: ' . $e->getMessage()
			);
		}

		$selections[] = $selection;
		$this->emitSelection($context, $selection->toArray());

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::TOOL_DEFINITIONS => $selection->getToolDefinitions(),
			AgentToolLoopContextKeys::SELECTED_TOOL_NAMES => $selection->getToolNames(),
			AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED => true,
			AgentToolLoopContextKeys::CAPABILITY_SELECTIONS => $selections
		], $selection->toArray());
	}

	private function buildContextText(IAgentContext $context): string {
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$continuationHint = $context->getVar(AgentToolLoopContextKeys::CONTINUATION_HINT);
		$parts = [];

		if (is_array($messages)) {
			foreach (array_slice($messages, -12) as $message) {
				if (!is_array($message)) {
					continue;
				}
				$content = $message['content'] ?? '';
				if (is_scalar($content)) {
					$content = trim((string)$content);
					if ($content !== '') {
						$parts[] = $content;
					}
				}
			}
		}

		if (is_scalar($continuationHint) && trim((string)$continuationHint) !== '') {
			$parts[] = trim((string)$continuationHint);
		}

		$text = implode("\n", $parts);
		if (strlen($text) > $this->maxContextCharacters) {
			$text = substr($text, -$this->maxContextCharacters);
		}
		return $text;
	}

	/** @return array<int,string> */
	private function recentToolNames(IAgentContext $context): array {
		$calls = $context->getVar(AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS);
		$result = [];
		foreach (array_slice(is_array($calls) ? $calls : [], -8) as $call) {
			if (!is_array($call)) {
				continue;
			}
			$name = trim((string)($call['name'] ?? $call['tool'] ?? ''));
			if ($name !== '') {
				$result[$name] = true;
			}
		}
		return array_keys($result);
	}

	/** @param array<string,mixed> $payload */
	private function emitSelection(IAgentContext $context, array $payload): void {
		$callback = $context->getVar(AgentToolLoopContextKeys::EVENT_CALLBACK);
		if (!is_callable($callback)) {
			return;
		}
		try {
			$callback('capability.selection', $payload);
		} catch (\Throwable) {
		}
	}

	private function failure(string $code, string $message): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => [],
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
		]);
	}
}

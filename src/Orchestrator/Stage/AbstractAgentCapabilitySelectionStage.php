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
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use AssistantFoundation\Dto\AgentStageResult;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;

/**
 * Shared execution mechanics for deterministic and AI-based capability
 * selection stages. The selected algorithm remains an explicit stage choice.
 */
abstract class AbstractAgentCapabilitySelectionStage implements IAgentStage {

	private AgentToolDefinitionSemantics $toolDefinitionSemantics;

	public function __construct(
		private readonly IAgentCapabilitySelector $selector,
		private readonly string $id,
		private readonly string $stageName,
		private readonly int $maxContextCharacters = 24000,
		?AgentToolDefinitionSemantics $toolDefinitionSemantics = null
	) {
		$this->toolDefinitionSemantics = $toolDefinitionSemantics ?? new AgentToolDefinitionSemantics();
		if (trim($this->id) === '') {
			throw new \InvalidArgumentException('Capability selection stage id must not be empty.');
		}
		if (trim($this->stageName) === '') {
			throw new \InvalidArgumentException('Capability selection stage name must not be empty.');
		}
		if ($this->maxContextCharacters < 1000) {
			throw new \InvalidArgumentException('Capability selection context limit must be at least 1000 characters.');
		}
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function supports(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_MODEL
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	final public function process(IAgentContext $context): AgentStageResult {
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
					requiredToolNames: $required,
					model: $this->resolveModel($context),
					messages: is_array($context->getVar(AgentToolLoopContextKeys::MESSAGES))
						? $context->getVar(AgentToolLoopContextKeys::MESSAGES)
						: []
				)
			);
		} catch (\Throwable $e) {
			return $this->failure(
				'capability_selection_failed',
				'Capability selection failed: ' . $e->getMessage()
			);
		}

		$selections[] = $selection;
		$modelResults = $context->getVar(AgentToolLoopContextKeys::MODEL_RESULTS);
		if (!is_array($modelResults)) {
			$modelResults = [];
		}
		if ($selection->getModelMetadata() !== null) {
			$modelResults[] = $selection->getModelMetadata()->toArray();
		}
		$this->emitSelection($context, $selection->toArray());

		$selectedDefinitions = $selection->getToolDefinitions();
		$patch = [
			AgentToolLoopContextKeys::TOOL_DEFINITIONS => $selectedDefinitions,
			AgentToolLoopContextKeys::SELECTED_TOOL_NAMES => $selection->getToolNames(),
			AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED => true,
			AgentToolLoopContextKeys::CAPABILITY_SELECTIONS => $selections,
			AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults
		];
		if ($config->selectsSources()) {
			$patch[AgentToolLoopContextKeys::MUTATION_TOOL_NAMES] = $this->toolDefinitionSemantics->getMutationToolNames($selectedDefinitions);
		}

		return AgentStageResult::patch($patch, $selection->toArray());
	}

	protected function resolveModel(IAgentContext $context): ?IAiChatModel {
		return null;
	}

	private function buildContextText(IAgentContext $context): string {
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$continuationHint = $context->getVar(AgentToolLoopContextKeys::CONTINUATION_HINT);
		$parts = [];

		foreach ($this->currentTurnMessages(is_array($messages) ? $messages : []) as $message) {
			if (!is_array($message)) {
				continue;
			}
			$role = strtolower(trim((string)($message['role'] ?? 'message')));
			if ($role === 'system') {
				continue;
			}
			$content = $message['content'] ?? '';
			if (!is_scalar($content)) {
				continue;
			}
			$content = trim((string)$content);
			if ($content !== '') {
				$parts[] = ($role === '' ? 'message' : $role) . ': ' . $content;
			}
		}

		if (is_scalar($continuationHint) && trim((string)$continuationHint) !== '') {
			$parts[] = 'continuation: ' . trim((string)$continuationHint);
		}

		return $this->limitContextText(implode("\n", $parts));
	}

	/** @param array<int,mixed> $messages @return array<int,mixed> */
	private function currentTurnMessages(array $messages): array {
		for ($index = count($messages) - 1; $index >= 0; $index--) {
			$message = $messages[$index] ?? null;
			if (!is_array($message)) {
				continue;
			}
			if (strtolower(trim((string)($message['role'] ?? ''))) === 'user') {
				return array_slice($messages, $index);
			}
		}

		return array_slice($messages, -12);
	}

	private function limitContextText(string $text): string {
		if (strlen($text) <= $this->maxContextCharacters) {
			return $text;
		}

		$separator = "\n...\n";
		$available = $this->maxContextCharacters - strlen($separator);
		$headLength = intdiv($available, 2);
		$tailLength = $available - $headLength;

		return substr($text, 0, $headLength) . $separator . substr($text, -$tailLength);
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

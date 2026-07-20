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

namespace MissionBay\Service\Assistant;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMessageFactory;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Dto\Assistant\AgentExecutionLedger;

final class AgentAssistantFinalResponseService implements IAgentAssistantFinalResponseService {

	private AgentFinalResponseGuardService $guardService;

	public function __construct(
		private IAgentAssistantMessageFactory $messageFactory,
		?AgentFinalResponseGuardService $guardService = null
	) {
		$this->guardService = $guardService ?? new AgentFinalResponseGuardService();
	}

	public function createDirectResponse(IAiChatModel $model, AgentAssistantTurnResult $turnResult): string {
		$ledger = AgentExecutionLedger::fromTurnResult($turnResult);
		$providerAnswer = $this->findTerminalProviderAnswer($turnResult);
		if ($providerAnswer !== '') {
			return $this->guardResponse($model, $turnResult, $ledger, $providerAnswer);
		}

		if (!$turnResult->canGenerateFinalResponse()) {
			return $turnResult->getFallbackContent() ?? '';
		}

		$result = $model->complete($this->buildFinalResponseMessages($turnResult, $ledger));
		$turnResult->addModelResult($result->getMetadata());
		$draft = $this->messageFactory->normalizeContent($result->getContent());

		return $this->guardResponse($model, $turnResult, $ledger, $draft);
	}

	public function createStreamingResponse(IAiChatModel $model, AgentAssistantTurnResult $turnResult, callable $onData, ?callable $onMeta = null): string {
		$ledger = AgentExecutionLedger::fromTurnResult($turnResult);
		$providerAnswer = $this->findTerminalProviderAnswer($turnResult);
		if ($providerAnswer !== '') {
			$content = $this->guardResponse($model, $turnResult, $ledger, $providerAnswer);
			$onData($content);

			return $content;
		}

		if (!$turnResult->canGenerateFinalResponse()) {
			$content = $turnResult->getFallbackContent() ?? '';
			if ($content !== '') {
				$onData($content);
			}

			return $content;
		}

		$metaCallback = $onMeta ?? function(array $meta): void {};
		if ($ledger->requiresBufferedStreaming()) {
			$buffer = '';
			$result = $model->streamResult(
				$this->buildFinalResponseMessages($turnResult, $ledger),
				[],
				function(string $delta) use (&$buffer): void {
					$buffer .= $delta;
				},
				$metaCallback
			);
			$turnResult->addModelResult($result->getMetadata());
			$draft = $buffer !== '' ? $buffer : $result->getContent();
			$content = $this->guardResponse(
				$model,
				$turnResult,
				$ledger,
				$this->messageFactory->normalizeContent($draft)
			);
			$onData($content);

			return $content;
		}

		$result = $model->streamResult(
			$this->buildFinalResponseMessages($turnResult, $ledger),
			[],
			$onData,
			$metaCallback
		);
		$turnResult->addModelResult($result->getMetadata());

		return $result->getContent();
	}


	/**
	 * Returns a provider-produced terminal answer without another model call.
	 * The terminal marker is set only by trusted tool adapters after successful
	 * execution and contract validation.
	 */
	private function findTerminalProviderAnswer(AgentAssistantTurnResult $turnResult): string {
		$messages = $turnResult->getMessages();

		for ($index = count($messages) - 1; $index >= 0; $index--) {
			$message = $messages[$index] ?? null;
			if (!is_array($message) || ($message['role'] ?? null) !== 'tool') {
				continue;
			}

			$content = $message['content'] ?? null;
			if (!is_string($content) || trim($content) === '') {
				continue;
			}

			$payload = json_decode($content, true);
			if (
				!is_array($payload) ||
				($payload['final_answer_ready'] ?? false) !== true ||
				!is_scalar($payload['answer'] ?? null)
			) {
				continue;
			}

			$answer = trim((string)$payload['answer']);
			if ($answer !== '') {
				return $this->messageFactory->normalizeContent($answer);
			}
		}

		return '';
	}


	/**
	 * Adds a control-only recovery instruction when the tool loop ended with a
	 * recoverable partial result. The instruction is not written to memory and
	 * does not alter the preserved orchestration message stack.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function buildFinalResponseMessages(AgentAssistantTurnResult $turnResult, AgentExecutionLedger $ledger): array {
		$messages = $turnResult->getMessages();
		$instructions = [];

		if ($turnResult->isPartialFinalResponse()) {
			$instructions[] = implode("\n", [
				'The tool phase reached its configured loop limit.',
				'Produce the most useful answer possible from the tool observations and conversation already available.',
				'Clearly state which conclusions are incomplete, uncertain, or still require verification.',
				'Do not claim that additional tools were executed and do not expose internal control codes or runtime details.'
			]);
		}

		$continuationInstruction = trim($turnResult->getFinalResponseInstruction());
		if ($continuationInstruction !== '') {
			$instructions[] = $continuationInstruction;
		}

		$ledgerInstruction = trim($ledger->buildFinalResponseInstruction());
		if ($ledgerInstruction !== '') {
			$instructions[] = $ledgerInstruction;
		}

		if ($instructions === []) {
			return $messages;
		}

		$instruction = implode("\n\n", $instructions);

		foreach ($messages as $index => $message) {
			if (
				!is_array($message) ||
				($message['role'] ?? null) !== 'system' ||
				!is_scalar($message['content'] ?? null)
			) {
				continue;
			}

			$content = trim((string)$message['content']);
			$messages[$index]['content'] = $content === ''
				? $instruction
				: $content . "\n\n" . $instruction;

			return $messages;
		}

		array_unshift($messages, [
			'role' => 'system',
			'content' => $instruction
		]);

		return $messages;
	}

	private function guardResponse(
		IAiChatModel $model,
		AgentAssistantTurnResult $turnResult,
		AgentExecutionLedger $ledger,
		string $content
	): string {
		$guarded = $this->guardService->guard($model, $turnResult, $ledger, $content);
		return $this->messageFactory->normalizeContent($guarded);
	}

	public function createAssistantMessage(AgentAssistantTurnResult $turnResult, string $content): array {
		return $this->messageFactory->createAssistantMessage($turnResult->getAssistantMessageId(), $content);
	}
}

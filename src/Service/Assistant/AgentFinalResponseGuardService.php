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
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Dto\Assistant\AgentExecutionLedger;

/**
 * Semantically checks a buffered final answer against authoritative mutation evidence.
 */
final class AgentFinalResponseGuardService {

	private const VERDICT_TOOL_NAME = 'missionbay_final_response_verdict';
	private const VERDICT_ACCEPT = 'accept';
	private const VERDICT_REPLACE = 'replace';

	public function guard(
		IAiChatModel $model,
		AgentAssistantTurnResult $turnResult,
		AgentExecutionLedger $ledger,
		string $draft
	): string {
		$draft = trim($draft);
		if (!$ledger->requiresFinalResponseGuard()) {
			return $draft;
		}
		if ($draft === '') {
			return $ledger->getSafeFallbackResponse();
		}

		try {
			$result = $model->complete(
				$this->buildMessages($ledger, $draft),
				[$this->getVerdictToolDefinition()]
			);
			$turnResult->addModelResult($result->getMetadata());
			$verdict = $this->readVerdict($result->getToolCalls());
			if ($verdict === null) {
				return $ledger->getSafeFallbackResponse();
			}
			if ($verdict['verdict'] === self::VERDICT_ACCEPT && !$verdict['claims_successful_mutation']) {
				return $draft;
			}
			$replacement = trim($verdict['replacement']);
			return $replacement !== '' ? $replacement : $ledger->getSafeFallbackResponse();
		}
		catch (\Throwable) {
			return $ledger->getSafeFallbackResponse();
		}
	}

	/** @return array<int,array<string,mixed>> */
	private function buildMessages(AgentExecutionLedger $ledger, string $draft): array {
		return [
			[
				'role' => 'system',
				'content' => implode("\n", [
					'You are a final-response evidence guard inside an agent runtime.',
					'Compare the draft semantically with the authoritative execution ledger.',
					'Do not answer the original task and do not call any external tool.',
					'Call the supplied verdict function exactly once.',
					'Use verdict=replace when the draft states or implies that a state-changing action succeeded although the ledger contains no corresponding successful mutation call.',
					'Use verdict=accept only when the draft makes no unsupported mutation-success claim.',
					'When replacing, write a concise safe response in the same language as the draft and preserve useful non-conflicting information.'
				])
			],
			[
				'role' => 'user',
				'content' => $ledger->buildFinalResponseInstruction() . "\n\nDraft final response:\n" . $draft
			]
		];
	}

	/** @param array<int,mixed> $toolCalls @return ?array{verdict:string,claims_successful_mutation:bool,replacement:string} */
	private function readVerdict(array $toolCalls): ?array {
		foreach ($toolCalls as $toolCall) {
			if (!$toolCall instanceof AiToolCall || $toolCall->getName() !== self::VERDICT_TOOL_NAME) {
				continue;
			}
			$arguments = $toolCall->getArguments();
			$verdict = strtolower(trim((string)($arguments['verdict'] ?? '')));
			if (!in_array($verdict, [self::VERDICT_ACCEPT, self::VERDICT_REPLACE], true)) {
				return null;
			}
			if (!array_key_exists('claims_successful_mutation', $arguments) || !is_bool($arguments['claims_successful_mutation'])) {
				return null;
			}
			return [
				'verdict' => $verdict,
				'claims_successful_mutation' => $arguments['claims_successful_mutation'],
				'replacement' => is_scalar($arguments['replacement'] ?? null)
					? trim((string)$arguments['replacement'])
					: ''
			];
		}
		return null;
	}

	/** @return array<string,mixed> */
	private function getVerdictToolDefinition(): array {
		return [
			'type' => 'function',
			'label' => 'MissionBay Final Response Verdict',
			'annotations' => ['readOnlyHint' => true],
			'function' => [
				'name' => self::VERDICT_TOOL_NAME,
				'description' => 'Internal semantic verdict for a buffered final response. This function is not exposed as an agent capability.',
				'parameters' => [
					'type' => 'object',
					'additionalProperties' => false,
					'properties' => [
						'verdict' => ['type' => 'string', 'enum' => [self::VERDICT_ACCEPT, self::VERDICT_REPLACE]],
						'claims_successful_mutation' => ['type' => 'boolean'],
						'reason' => ['type' => 'string'],
						'replacement' => ['type' => 'string']
					],
					'required' => ['verdict', 'claims_successful_mutation', 'reason', 'replacement']
				]
			]
		];
	}
}

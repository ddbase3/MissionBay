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
			if ($verdict['verdict'] === self::VERDICT_ACCEPT && !$verdict['has_unsupported_mutation_claim']) {
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
					'Validate every mutation-related claim in the draft against the ledger, including turns that contain successful mutation calls.',
					'Use verdict=replace when the draft states or implies an action succeeded, a post-condition is verified, or a current state is established beyond what the corresponding successful_mutation_calls result actually supports.',
					'For multiple requested mutations, validate each claimed outcome individually. One successful call does not prove that other requested changes succeeded.',
					'Approval, intent, prior assistant statements, attempted calls, failed calls, and cached mutation results are not execution proof.',
					'Use verdict=accept only when every mutation-related claim is supported by the authoritative ledger and the draft does not overstate verification.',
					'When replacing, write a concise safe response in the same language as the draft and preserve useful non-conflicting information.'
				])
			],
			[
				'role' => 'user',
				'content' => $ledger->buildFinalResponseInstruction() . "\n\nDraft final response:\n" . $draft
			]
		];
	}

	/** @param array<int,mixed> $toolCalls @return ?array{verdict:string,has_unsupported_mutation_claim:bool,replacement:string} */
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
			if (!array_key_exists('has_unsupported_mutation_claim', $arguments) || !is_bool($arguments['has_unsupported_mutation_claim'])) {
				return null;
			}
			return [
				'verdict' => $verdict,
				'has_unsupported_mutation_claim' => $arguments['has_unsupported_mutation_claim'],
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
						'has_unsupported_mutation_claim' => ['type' => 'boolean'],
						'reason' => ['type' => 'string'],
						'replacement' => ['type' => 'string']
					],
					'required' => ['verdict', 'has_unsupported_mutation_claim', 'reason', 'replacement']
				]
			]
		];
	}
}

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

namespace MissionBay\Orchestrator\Service;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use MissionBay\Dto\Assistant\AgentInteractionResolution;

/** Interprets a natural-language reply against the exact server-owned pending interaction requests. */
final class AgentInteractionResponseResolver {

	/**
	 * @param array<int,AgentInteractionRequest> $requests
	 */
	public function resolve(?IAiChatModel $model, array $requests, string $responseText): AgentInteractionResolution {
		$responseText = trim($responseText);

		if ($responseText === '') {
			return AgentInteractionResolution::unclear('The user response is empty.');
		}
		if (!$model instanceof IAiChatModel) {
			return AgentInteractionResolution::unclear('No AI chat model is available to interpret the user response.');
		}
		if ($requests === []) {
			return AgentInteractionResolution::unclear('No pending interaction requests are available.');
		}
		foreach ($requests as $request) {
			if (!$request instanceof AgentInteractionRequest) {
				return AgentInteractionResolution::unclear('A pending interaction request has an invalid runtime type.');
			}
		}

		try {
			$result = $model->complete($this->buildMessages($requests, $responseText), []);
			$metadata = [
				'model_metadata' => $result->getMetadata()->toArray(),
				'raw_response' => $this->truncate($result->getContent(), 4000)
			];
			$parsed = $this->parseJsonObject($result->getContent());

			if ($parsed === null) {
				return AgentInteractionResolution::unclear(
					'The AI response could not be parsed as the required JSON object.',
					$metadata + ['parse_status' => 'invalid']
				);
			}

			$status = strtolower(trim((string)($parsed['status'] ?? '')));
			$reason = trim((string)($parsed['reason'] ?? ''));

			if ($status === AgentInteractionResolution::STATUS_UNCLEAR) {
				return AgentInteractionResolution::unclear(
					$reason !== '' ? $reason : 'The user response was not unambiguous.',
					$metadata + ['parse_status' => 'valid']
				);
			}
			if ($status !== AgentInteractionResolution::STATUS_RESOLVED) {
				return AgentInteractionResolution::unclear(
					'The AI response contains an unsupported resolution status.',
					$metadata + ['parse_status' => 'invalid']
				);
			}

			$responses = $this->validateResponses($requests, $parsed['responses'] ?? null);
			if ($responses === null) {
				return AgentInteractionResolution::unclear(
					'The AI response does not contain one valid decision for every pending request.',
					$metadata + ['parse_status' => 'invalid']
				);
			}

			return AgentInteractionResolution::resolved(
				$responses,
				$reason,
				$metadata + ['parse_status' => 'valid']
			);
		} catch (\Throwable $e) {
			return AgentInteractionResolution::unclear(
				'The user response could not be interpreted safely.',
				[
					'parse_status' => 'error',
					'type' => get_class($e),
					'message' => $e->getMessage()
				]
			);
		}
	}

	/**
	 * @param array<int,AgentInteractionRequest> $requests
	 * @return array<int,array<string,string>>
	 */
	private function buildMessages(array $requests, string $responseText): array {
		$payload = array_map(
			static fn(AgentInteractionRequest $request): array => [
				'id' => $request->getId(),
				'kind' => $request->getKind(),
				'title' => $request->getTitle(),
				'message' => $request->getMessage(),
				'risk' => $request->getRisk(),
				'summary' => $request->getSummary(),
				'action' => [
					'type' => $request->getAction()->getType(),
					'name' => $request->getAction()->getName(),
					'input' => $request->getAction()->getInput()
				]
			],
			$requests
		);
		$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return [
			[
				'role' => 'system',
				'content' => implode("\n", [
					'Interpret the user reply only as a response to the listed pending agent interactions.',
					'Do not execute actions, invent request ids, alter action payloads, or infer consent from silence.',
					'Natural language may express approval, denial, clarification input, or uncertainty in any wording or language.',
					'For an approval request, allowed decisions are approve or deny.',
					'For a clarification or dry_run request, allowed decisions are submit or deny. Put supplied structured values in input.',
					'If the reply is ambiguous, unrelated, conditional without a clear decision, or insufficient for any request, return status unclear and no responses.',
					'If several requests are pending, return exactly one response for every request only when the reply clearly covers all of them.',
					'Return exactly one JSON object and no surrounding text:',
					'{"status":"resolved|unclear","reason":"short explanation","responses":[{"request_id":"exact id","decision":"approve|deny|submit","input":{},"note":"optional"}]}'
				])
			],
			[
				'role' => 'user',
				'content' => "Pending interactions:\n" . ($payloadJson !== false ? $payloadJson : '[]') . "\n\nUser reply:\n" . $responseText
			]
		];
	}

	/**
	 * @param array<int,AgentInteractionRequest> $requests
	 * @return array<int,AgentInteractionResponse>|null
	 */
	private function validateResponses(array $requests, mixed $value): ?array {
		if (!is_array($value)) {
			return null;
		}

		$requestMap = [];
		foreach ($requests as $request) {
			$requestMap[$request->getId()] = $request;
		}

		$responses = [];
		foreach ($value as $item) {
			if (!is_array($item)) {
				return null;
			}
			$requestId = trim((string)($item['request_id'] ?? $item['id'] ?? ''));
			if ($requestId === '' || !isset($requestMap[$requestId]) || isset($responses[$requestId])) {
				return null;
			}
			$decision = strtolower(trim((string)($item['decision'] ?? '')));
			$request = $requestMap[$requestId];
			if (!$this->isAllowedDecision($request, $decision)) {
				return null;
			}
			$input = is_array($item['input'] ?? null) ? $item['input'] : [];
			$responses[$requestId] = new AgentInteractionResponse(
				$requestId,
				$decision,
				$input,
				trim((string)($item['note'] ?? '')),
				['source' => 'natural_language_ai']
			);
		}

		if (count($responses) !== count($requestMap)) {
			return null;
		}

		return array_values($responses);
	}

	private function isAllowedDecision(AgentInteractionRequest $request, string $decision): bool {
		if ($request->getKind() === AgentInteractionRequest::KIND_APPROVAL) {
			return in_array($decision, [
				AgentInteractionResponse::DECISION_APPROVE,
				AgentInteractionResponse::DECISION_DENY
			], true);
		}

		return in_array($decision, [
			AgentInteractionResponse::DECISION_SUBMIT,
			AgentInteractionResponse::DECISION_DENY
		], true);
	}

	/** @return array<string,mixed>|null */
	private function parseJsonObject(string $content): ?array {
		$content = trim($content);
		if ($content === '') {
			return null;
		}
		if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $content, $match)) {
			$content = trim($match[1]);
		}
		$decoded = json_decode($content, true);
		if (is_array($decoded)) {
			return $decoded;
		}
		$start = strpos($content, '{');
		$end = strrpos($content, '}');
		if ($start === false || $end === false || $end <= $start) {
			return null;
		}
		$decoded = json_decode(substr($content, $start, $end - $start + 1), true);
		return is_array($decoded) ? $decoded : null;
	}

	private function truncate(string $value, int $maxLength): string {
		if (strlen($value) <= $maxLength) {
			return $value;
		}
		return substr($value, 0, max(0, $maxLength - 1)) . '…';
	}
}

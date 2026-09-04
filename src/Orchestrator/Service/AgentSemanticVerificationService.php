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

use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;
use Base3\Logger\Api\ILogger;

/**
 * AgentSemanticVerificationService
 *
 * Validates a terminal model-decision after the tool loop has already decided
 * that no further tool call is required. The stage evaluates the accumulated
 * normalized observations exactly once per terminal candidate.
 *
 * It is not executed after every tool call. This avoids doubling the AI latency
 * of each loop iteration. A valid failed verdict marks a material unresolved gap
 * for continuation handling. Malformed or inconclusive verifier output remains advisory.
 */
final class AgentSemanticVerificationService {

	public const VERIFIER_NAME = 'semantic-tool-result-sufficiency';
	private const RECOMMENDATION_ANSWER = 'answer';
	private const RECOMMENDATION_CONTINUE = 'continue';
	private const RECOMMENDATION_CLARIFY = 'clarify';
	private const RECOMMENDATION_UNKNOWN = 'unknown';

	public function __construct(
		private readonly int $maxInputBytes = 60000,
		private readonly int $maxTaskBytes = 12000
	) {}

	public function verify(IAgentContext $context): AgentStageResult {
		$model = $context->getVar(AgentToolLoopContextKeys::MODEL);
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$observations = $context->getVar(AgentToolLoopContextKeys::OBSERVATIONS);
		$toolDefinitions = $context->getVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS);
		$capabilityCatalog = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_CATALOG);
		$selectionConfig = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_SELECTION_CONFIG);
		$modelResults = $context->getVar(AgentToolLoopContextKeys::MODEL_RESULTS);
		$verifications = $context->getVar(AgentToolLoopContextKeys::RESULT_VERIFICATIONS);
		$logger = $context->getVar(AgentToolLoopContextKeys::LOGGER);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);

		if (!is_array($messages)) {
			$messages = [];
		}


		if (!is_array($observations)) {
			$observations = [];
		}

		if (!is_array($toolDefinitions)) {
			$toolDefinitions = [];
		}
		$selectedToolDefinitions = $toolDefinitions;
		if ($capabilityCatalog instanceof AgentCapabilityCatalog) {
			$toolDefinitions = [];
			foreach ($capabilityCatalog->all() as $capability) {
				$definition = $capability->getDefinition();
				if (is_array($definition)) {
					$toolDefinitions[] = $definition;
				}
			}
		}

		if (!is_array($modelResults)) {
			$modelResults = [];
		}

		if (!is_array($verifications)) {
			$verifications = [];
		}

		if (!$model instanceof IAiChatModel) {
			return $this->appendInconclusive(
				$iteration,
				$verifications,
				$modelResults,
				'semantic_verifier_model_missing',
				'Semantic verification could not run because no normalized chat model was available.',
				[]
			);
		}

		foreach ($observations as $toolResult) {
			if (!$toolResult instanceof AgentToolResult) {
				return $this->appendInconclusive(
					$iteration,
					$verifications,
					$modelResults,
					'invalid_tool_result_type',
					'Semantic verification received a non-normalized tool result or observation.',
					['type' => get_debug_type($toolResult)]
				);
			}
		}

		[$payload, $inputTruncated] = $this->buildVerificationPayload(
			$messages,
			$observations,
			[],
			$toolDefinitions,
			$selectedToolDefinitions,
			$selectionConfig instanceof AgentCapabilitySelectionConfig ? $selectionConfig : null,
			$iteration
		);

		try {
			$result = $model->complete($this->buildVerifierMessages($payload), []);
			$modelMetadata = $result->getMetadata()->toArray();
			$modelResults[] = $modelMetadata;
			$parsed = $this->parseVerificationResponse($result->getContent());

			if ($parsed === null) {
				$responseExcerpt = $this->truncateText(trim($result->getContent()), 1000)[0];
				$this->logError($logger, 'Semantic verifier returned invalid structured output: ' . $responseExcerpt);
				$verification = new AgentResultVerification(
					iteration: $iteration,
					verifier: self::VERIFIER_NAME,
					verdict: AgentResultVerification::VERDICT_INCONCLUSIVE,
					summary: 'Semantic verifier returned no valid structured assessment.',
					issues: [[
						'code' => 'invalid_semantic_verifier_response',
						'message' => 'The semantic verifier response could not be parsed as the required JSON object.',
						'detail' => [
							'response_excerpt' => $responseExcerpt
						]
					]],
					metadata: [
						'recommendation' => self::RECOMMENDATION_UNKNOWN,
						'confidence' => null,
						'input_truncated' => $inputTruncated,
						'previous_observation_count' => count($observations),
						'current_tool_result_count' => 0,
						'total_evidence_count' => count($observations),
						'model_metadata' => $modelMetadata
					]
				);
				$verifications[] = $verification;

				return AgentStageResult::patch([
					AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
					AgentToolLoopContextKeys::RESULT_VERIFICATIONS => $verifications
				], [
					'semantic_verification' => $verification->toArray(),
					'parse_status' => 'invalid'
				]);
			}

			$verification = new AgentResultVerification(
				iteration: $iteration,
				verifier: self::VERIFIER_NAME,
				verdict: $parsed['verdict'],
				summary: $parsed['summary'],
				issues: $parsed['issues'],
				metadata: [
					'recommendation' => $parsed['recommendation'],
					'confidence' => $parsed['confidence'],
					'input_truncated' => $inputTruncated,
					'previous_observation_count' => count($observations),
					'current_tool_result_count' => 0,
					'total_evidence_count' => count($observations),
					'model_metadata' => $modelMetadata
				]
			);
			$verifications[] = $verification;

			return AgentStageResult::patch([
				AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
				AgentToolLoopContextKeys::RESULT_VERIFICATIONS => $verifications
			], [
				'semantic_verification' => $verification->toArray(),
				'parse_status' => 'valid'
			]);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Semantic verification failed: ' . $e->getMessage());

			return $this->appendInconclusive(
				$iteration,
				$verifications,
				$modelResults,
				'semantic_verifier_error',
				'Semantic verification could not be completed.',
				[
					'type' => get_class($e),
					'message' => $e->getMessage(),
					'input_truncated' => $inputTruncated
				]
			);
		}
	}


	/**
	 * @param array<int,array<string,mixed>> $messages
	 * @param array<int,AgentToolResult> $observations
	 * @param array<int,AgentToolResult> $toolResults
	 * @param array<int,array<string,mixed>> $toolDefinitions
	 * @param array<int,array<string,mixed>> $selectedToolDefinitions
	 * @return array{0:array<string,mixed>,1:bool}
	 */
	private function buildVerificationPayload(
		array $messages,
		array $observations,
		array $toolResults,
		array $toolDefinitions,
		array $selectedToolDefinitions,
		?AgentCapabilitySelectionConfig $selectionConfig,
		int $iteration
	): array {
		$task = $this->extractCurrentTask($messages);
		[$task, $taskTruncated] = $this->truncateText($task, max(1000, $this->maxTaskBytes));
		[$availableTools, $toolsCompacted] = $this->normalizeAvailableTools(
			$toolDefinitions,
			min(12000, max(3000, intdiv($this->maxInputBytes, 5)))
		);
		$availableToolsJson = json_encode($availableTools, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$availableToolsBytes = is_string($availableToolsJson) ? strlen($availableToolsJson) : 0;
		$evidenceCount = max(1, count($observations) + count($toolResults));
		$availableBytes = max(4000, $this->maxInputBytes - strlen($task) - $availableToolsBytes - 7000);
		$perResultBytes = max(750, intdiv($availableBytes, $evidenceCount));
		$inputTruncated = $taskTruncated || $toolsCompacted;
		$previousEvidence = $this->normalizeEvidence(
			$observations,
			$perResultBytes,
			false,
			$inputTruncated
		);
		$currentEvidence = $this->normalizeEvidence(
			$toolResults,
			$perResultBytes,
			true,
			$inputTruncated
		);

		return [[
			'iteration' => $iteration,
			'task' => $task,
			'available_tools' => $availableTools,
			'available_tool_count' => count($toolDefinitions),
			'currently_selected_tools' => $this->toolNames($selectedToolDefinitions),
			'capability_selection_constraints' => $this->selectionConstraints($selectionConfig),
			'tool_catalog_compacted' => $toolsCompacted,
			'previous_observations' => $previousEvidence,
			'current_tool_results' => $currentEvidence,
			'evidence_count' => count($observations) + count($toolResults),
			'input_truncated' => $inputTruncated
		], $inputTruncated];
	}


	/** @param array<int,array<string,mixed>> $definitions @return array<int,string> */
	private function toolNames(array $definitions): array {
		$result = [];
		foreach ($definitions as $definition) {
			if (!is_array($definition)) {
				continue;
			}
			$name = trim((string)($definition['function']['name'] ?? ''));
			if ($name !== '') {
				$result[$name] = true;
			}
		}
		return array_keys($result);
	}

	/** @return array<string,mixed> */
	private function selectionConstraints(?AgentCapabilitySelectionConfig $config): array {
		if (!$config instanceof AgentCapabilitySelectionConfig) {
			return [];
		}

		return [
			'include_tools' => $config->getIncludeTools(),
			'exclude_tools' => $config->getExcludeTools(),
			'include_tags' => $config->getIncludeTags(),
			'exclude_tags' => $config->getExcludeTags(),
			'include_categories' => $config->getIncludeCategories(),
			'exclude_categories' => $config->getExcludeCategories(),
			'max_tools' => $config->getMaxTools(),
			'max_sources' => $config->getMaxSources(),
			'selection_unit' => $config->selectsSources() ? 'source' : 'function'
		];
	}

	/**
	 * @param array<int,array<string,mixed>> $definitions
	 * @return array{0:array<int,mixed>,1:bool}
	 */
	private function normalizeAvailableTools(array $definitions, int $maxBytes): array {
		$full = [];
		foreach ($definitions as $definition) {
			if (!is_array($definition)) {
				continue;
			}

			$function = is_array($definition['function'] ?? null)
				? $definition['function']
				: $definition;
			$name = trim((string)($function['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$description = trim((string)($function['description'] ?? ''));
			[$description] = $this->truncateText($description, 180);
			$parameters = is_array($function['parameters'] ?? null) ? $function['parameters'] : [];
			$required = array_values(array_filter(
				(array)($parameters['required'] ?? []),
				static fn(mixed $value): bool => is_string($value) && trim($value) !== ''
			));

			$full[] = [
				'name' => $name,
				'description' => $description,
				'required' => $required
			];
		}

		$json = json_encode($full, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (is_string($json) && strlen($json) <= $maxBytes) {
			return [$full, false];
		}

		$compact = [];
		foreach ($full as $tool) {
			$entry = ['name' => $tool['name']];
			if ($tool['required'] !== []) {
				$entry['required'] = $tool['required'];
			}
			$compact[] = $entry;
		}

		$json = json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (is_string($json) && strlen($json) <= $maxBytes) {
			return [$compact, true];
		}

		return [[
			'tool_names' => array_values(array_map(
				static fn(array $tool): string => (string)$tool['name'],
				$full
			))
		], true];
	}

	/**
	 * @param array<int,AgentToolResult> $results
	 * @return array<int,array<string,mixed>>
	 */
	private function normalizeEvidence(
		array $results,
		int $perResultBytes,
		bool $currentIteration,
		bool &$inputTruncated
	): array {
		$normalized = [];

		foreach ($results as $toolResult) {
			[$arguments, $argumentsTruncated] = $this->truncateText(
				$this->serializeValue($toolResult->getArguments()),
				max(250, intdiv($perResultBytes, 4))
			);
			[$output, $outputTruncated] = $this->truncateText(
				$this->serializeValue($toolResult->getOutput()),
				max(500, $perResultBytes - strlen($arguments))
			);
			$resultTruncated = $argumentsTruncated || $outputTruncated;
			$inputTruncated = $inputTruncated || $resultTruncated;
			$metadata = $toolResult->getMetadata();

			$normalized[] = [
				'call_id' => $toolResult->getCallId(),
				'tool' => $toolResult->getToolName(),
				'arguments' => $arguments,
				'status' => $toolResult->getStatus(),
				'output' => $output,
				'error_code' => $toolResult->getErrorCode(),
				'error_message' => $toolResult->getErrorMessage(),
				'iteration' => isset($metadata['iteration']) && is_numeric($metadata['iteration'])
					? (int)$metadata['iteration']
					: null,
				'current_iteration' => $currentIteration,
				'truncated' => $resultTruncated
			];
		}

		return $normalized;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<int,array<string,mixed>>
	 */
	private function buildVerifierMessages(array $payload): array {
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($json)) {
			$json = '{}';
		}

		return [
			[
				'role' => 'system',
				'content' => implode("\n", [
					'You are a terminal-decision verifier inside an agent runtime.',
					'The primary model has already decided that the tool phase should stop.',
					'Evaluate only the supplied task, accumulated normalized observations, and the supplied run-local capability catalog.',
					'The capability catalog is the assigned tool landscape before per-iteration source or function selection. Respect the supplied selection constraints when deciding whether a gap is tool-resolvable. currently_selected_tools shows only the functions exposed in the terminal iteration.',
					'Do not call tools, do not answer the user, and do not add facts.',
					'Return exactly one JSON object without Markdown or surrounding prose.',
					'Use this schema:',
					'{"verdict":"verified|failed|inconclusive","summary":"concise factual assessment","issues":[{"code":"stable_snake_case","message":"factual issue","detail":{}}],"recommendation":"answer|continue|clarify","confidence":0.85}',
					'confidence must be a JSON number between 0 and 1.',
					'Use verified only when the accumulated evidence supports the material factual claims required by the user request and covers its relevant scope.',
					'Do not mark evidence verified merely because it is relevant, plausible, or partially matching. Distinguish examples from definitions, individual results from complete sets, partial observations from general conclusions, and intended actions from verified successful outcomes.',
					'Do not use model knowledge to fill a gap in the supplied evidence.',
					'Previous assistant statements are not evidence. They may explain conversational intent, but they never verify current state, a factual claim, or successful execution.',
					'When the task asks to check, verify, re-check, or confirm current runtime state and an eligible authoritative read capability exists, require relevant tool evidence for that state instead of accepting a prior assistant claim.',
					'For state-changing work, distinguish requested, awaiting approval, approved, attempted, succeeded, and verified outcomes. A successful mutation call supports only what its returned evidence establishes; do not infer an unreported post-condition. Multiple requested actions must be accounted for individually.',
					'Use failed when a specific material information or action gap remains and one of the supplied catalog tools that is eligible under the selection constraints is reasonably likely to resolve it.',
					'Use answer only when no additional tool call is needed for a supportable response whose material claims are grounded in the supplied evidence.',
					'Use continue when a concrete material gap remains and one or more supplied catalog tools that are eligible under the selection constraints are reasonably likely to add the missing evidence or complete the requested action.',
					'Use clarify when the missing value cannot be established from the supplied evidence or any eligible catalog tool and must come from the user.',
					'Use verdict=failed only for a concrete material gap that further eligible tool work can reasonably close, and pair it with recommendation=continue. If a remaining gap cannot be closed by any eligible catalog tool, use verdict=inconclusive with recommendation=answer and describe the limitation instead of continuing speculatively.',
					'Use inconclusive when the supplied evidence does not permit a reliable assessment.'
				])
			],
			[
				'role' => 'user',
				'content' => $json
			]
		];
	}

	/**
	 * @return ?array{
	 *     verdict:string,
	 *     summary:string,
	 *     issues:array<int,array<string,mixed>>,
	 *     recommendation:string,
	 *     confidence:?float
	 * }
	 */
	private function parseVerificationResponse(string $content): ?array {
		$content = trim($content);
		if ($content === '') {
			return null;
		}

		$content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
		$content = preg_replace('/\s*```$/', '', $content) ?? $content;
		$start = strpos($content, '{');
		$end = strrpos($content, '}');

		if ($start === false || $end === false || $end < $start) {
			return null;
		}

		$data = json_decode(substr($content, $start, $end - $start + 1), true);
		if (!is_array($data)) {
			return null;
		}

		foreach (['assessment', 'verification', 'result'] as $containerKey) {
			if (isset($data[$containerKey]) && is_array($data[$containerKey])) {
				$data = $data[$containerKey];
				break;
			}
		}

		$verdict = $this->normalizeVerdict($data['verdict'] ?? $data['status'] ?? null);
		if ($verdict === null) {
			return null;
		}

		$summary = trim((string)($data['summary'] ?? $data['reason'] ?? $data['assessment'] ?? ''));
		if ($summary === '') {
			return null;
		}

		$recommendation = $this->normalizeRecommendation(
			$data['recommendation'] ?? $data['decision'] ?? $data['next_action'] ?? null
		);
		$confidence = $this->normalizeConfidence($data['confidence'] ?? $data['score'] ?? null);

		return [
			'verdict' => $verdict,
			'summary' => $summary,
			'issues' => $this->normalizeIssues($data['issues'] ?? $data['gaps'] ?? []),
			'recommendation' => $recommendation,
			'confidence' => $confidence
		];
	}

	private function normalizeVerdict(mixed $value): ?string {
		$value = strtolower(trim((string)$value));

		return match ($value) {
			'verified', 'sufficient', 'pass', 'passed', 'complete' => AgentResultVerification::VERDICT_VERIFIED,
			'failed', 'insufficient', 'fail', 'incomplete' => AgentResultVerification::VERDICT_FAILED,
			'inconclusive', 'unknown', 'uncertain' => AgentResultVerification::VERDICT_INCONCLUSIVE,
			default => null
		};
	}

	private function normalizeRecommendation(mixed $value): string {
		$value = strtolower(trim((string)$value));

		return match ($value) {
			'answer', 'respond', 'finish', 'complete', 'stop' => self::RECOMMENDATION_ANSWER,
			'continue', 'search', 'more_tools', 'tool', 'tools' => self::RECOMMENDATION_CONTINUE,
			'clarify', 'ask_user', 'question' => self::RECOMMENDATION_CLARIFY,
			default => self::RECOMMENDATION_UNKNOWN
		};
	}

	private function normalizeConfidence(mixed $value): ?float {
		if (is_string($value)) {
			$value = trim($value);
			if (str_ends_with($value, '%')) {
				$value = substr($value, 0, -1);
				if (is_numeric($value)) {
					return max(0.0, min(1.0, (float)$value / 100));
				}
			}
		}

		if (!is_numeric($value)) {
			return null;
		}

		$confidence = (float)$value;
		if ($confidence > 1.0 && $confidence <= 100.0) {
			$confidence /= 100.0;
		}

		return max(0.0, min(1.0, $confidence));
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function normalizeIssues(mixed $issues): array {
		if (!is_array($issues)) {
			return [];
		}

		$result = [];

		foreach ($issues as $index => $issue) {
			if (!is_array($issue)) {
				continue;
			}

			$code = trim((string)($issue['code'] ?? 'semantic_issue_' . $index));
			$message = trim((string)($issue['message'] ?? ''));
			$detail = $issue['detail'] ?? [];

			if ($message === '') {
				continue;
			}

			$result[] = [
				'code' => $code !== '' ? $code : 'semantic_issue_' . $index,
				'message' => $message,
				'detail' => is_array($detail) ? $detail : ['value' => $detail]
			];
		}

		return $result;
	}

	/**
	 * @param array<int,mixed> $messages
	 */
	private function extractCurrentTask(array $messages): string {
		for ($index = count($messages) - 1; $index >= 0; $index--) {
			$message = $messages[$index] ?? null;
			if (!is_array($message) || ($message['role'] ?? '') !== 'user') {
				continue;
			}

			return $this->serializeValue($message['content'] ?? '');
		}

		return '';
	}

	private function serializeValue(mixed $value): string {
		if (is_string($value)) {
			return $value;
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (is_string($json)) {
			return $json;
		}

		if (is_scalar($value)) {
			return (string)$value;
		}

		return '';
	}

	/**
	 * @return array{0:string,1:bool}
	 */
	private function truncateText(string $value, int $maxBytes): array {
		$maxBytes = max(1, $maxBytes);
		if (strlen($value) <= $maxBytes) {
			return [$value, false];
		}

		if (function_exists('mb_strcut')) {
			return [mb_strcut($value, 0, $maxBytes, 'UTF-8'), true];
		}

		return [substr($value, 0, $maxBytes), true];
	}

	/**
	 * @param array<int,mixed> $verifications
	 * @param array<int,mixed> $modelResults
	 * @param array<string,mixed> $detail
	 */
	private function appendInconclusive(
		int $iteration,
		array $verifications,
		array $modelResults,
		string $code,
		string $summary,
		array $detail
	): AgentStageResult {
		$verifications[] = new AgentResultVerification(
			iteration: $iteration,
			verifier: self::VERIFIER_NAME,
			verdict: AgentResultVerification::VERDICT_INCONCLUSIVE,
			summary: $summary,
			issues: [[
				'code' => $code,
				'message' => $summary,
				'detail' => $detail
			]],
			metadata: [
				'recommendation' => self::RECOMMENDATION_UNKNOWN,
				'confidence' => null
			]
		);

		$verification = $verifications[array_key_last($verifications)];

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
			AgentToolLoopContextKeys::RESULT_VERIFICATIONS => $verifications
		], [
			'semantic_verification' => $verification instanceof AgentResultVerification
				? $verification->toArray()
				: [],
			'parse_status' => 'inconclusive'
		]);
	}

	private function logError(mixed $logger, string $message): void {
		if (!$logger instanceof ILogger) {
			return;
		}

		$logger->log('agentsemanticverificationstage', '[ERROR] ' . $message);
	}
}

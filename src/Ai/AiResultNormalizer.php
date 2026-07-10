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

namespace MissionBay\Ai;

use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiToolCall;
use AssistantFoundation\Dto\AiUsage;
use RuntimeException;

/**
 * Converts provider responses into AssistantFoundation result DTOs.
 *
 * Provider-specific response shapes are handled at the model adapter boundary.
 * Agent stages and higher-level consumers only receive normalized result DTOs.
 */
final class AiResultNormalizer {

	/**
	 * @param array<string,mixed> $hints
	 */
	public static function chat(mixed $raw, array $hints = []): AiChatResult {
		$startedAt = isset($hints['started_at']) && is_numeric($hints['started_at'])
			? (float)$hints['started_at']
			: null;
		$content = '';
		$toolCalls = [];
		$recognized = false;

		if(is_string($raw)) {
			$content = $raw;
			$recognized = true;
		}

		if(is_array($raw)) {
			[$content, $toolCalls, $recognized] = self::extractChatPayload($raw);
		}

		if(!$recognized) {
			throw new RuntimeException('Unable to normalize chat model response.');
		}

		return new AiChatResult(
			$content,
			$toolCalls,
			self::metadata('chat', $raw, $hints, $startedAt),
			$raw
		);
	}

	/**
	 * @param array<string,mixed> $hints
	 */
	public static function metadata(
		string $operation,
		mixed $raw,
		array $hints = [],
		?float $startedAt = null
	): AiResultMetadata {
		$data = is_array($raw) ? $raw : [];
		$durationMs = null;

		if($startedAt !== null) {
			$durationMs = max(0.0, (microtime(true) - $startedAt) * 1000);
		} elseif(isset($hints['duration_ms']) && is_numeric($hints['duration_ms'])) {
			$durationMs = (float)$hints['duration_ms'];
		}

		$model = self::firstString([
			$data['model'] ?? null,
			$data['model_name'] ?? null,
			$data['modelName'] ?? null,
			$data['modelVersion'] ?? null,
			$hints['model'] ?? null
		]);
		$requestId = self::firstString([
			$data['id'] ?? null,
			$data['request_id'] ?? null,
			$data['requestId'] ?? null,
			$data['response_id'] ?? null,
			$data['responseId'] ?? null,
			$hints['request_id'] ?? null
		]);
		$createdAt = self::firstInt([
			$data['created'] ?? null,
			$data['created_at'] ?? null,
			$data['createdAt'] ?? null,
			$hints['created_at'] ?? null
		]);
		$finishReason = self::firstString([
			$data['choices'][0]['finish_reason'] ?? null,
			$data['stop_reason'] ?? null,
			$data['stopReason'] ?? null,
			$data['candidates'][0]['finishReason'] ?? null,
			$data['status'] ?? null,
			$hints['finish_reason'] ?? null
		], true);

		$extra = isset($hints['extra']) && is_array($hints['extra']) ? $hints['extra'] : [];
		if(isset($hints['adapter']) && is_string($hints['adapter']) && $hints['adapter'] !== '') {
			$extra['adapter'] = $hints['adapter'];
		}

		$usage = self::usage($data);
		if(isset($hints['usage_metrics']) && is_array($hints['usage_metrics'])) {
			$usage = $usage->merge(new AiUsage(metrics: $hints['usage_metrics']));
		}

		return new AiResultMetadata(
			$operation,
			self::firstString([$hints['provider'] ?? null]),
			$model,
			$requestId,
			$createdAt,
			$durationMs,
			$finishReason !== '' ? $finishReason : null,
			$usage,
			$extra
		);
	}

	/**
	 * Builds one metadata envelope from provider-specific stream metadata
	 * events without assuming one event shape.
	 *
	 * Usage values are selected from the most complete event instead of
	 * being summed because many providers emit cumulative stream usage.
	 *
	 * @param array<int,array<string,mixed>> $events
	 * @param array<string,mixed> $hints
	 */
	public static function streamMetadata(
		array $events,
		array $hints = [],
		?float $startedAt = null
	): AiResultMetadata {
		$provider = self::firstString([$hints['provider'] ?? null]);
		$model = self::firstString([$hints['model'] ?? null]);
		$requestId = '';
		$createdAt = null;
		$finishReason = null;
		$usage = AiUsage::none();
		$usageScore = -1;

		foreach($events as $event) {
			$candidates = [$event];

			if(is_array($event['full'] ?? null)) {
				$candidates[] = $event['full'];
			}

			foreach($candidates as $candidate) {
				$metadata = self::metadata('chat', $candidate, $hints);

				if($metadata->getProvider() !== '') {
					$provider = $metadata->getProvider();
				}
				if($metadata->getModel() !== '') {
					$model = $metadata->getModel();
				}
				if($metadata->getRequestId() !== '') {
					$requestId = $metadata->getRequestId();
				}
				if($metadata->getCreatedAt() !== null) {
					$createdAt = $metadata->getCreatedAt();
				}
				if($metadata->getFinishReason() !== null) {
					$finishReason = $metadata->getFinishReason();
				}

				$candidateUsage = $metadata->getUsage();
				$candidateScore = self::usageScore($candidateUsage);

				if($candidateScore > $usageScore) {
					$usage = $candidateUsage;
					$usageScore = $candidateScore;
				}
			}
		}

		foreach(array_reverse($events) as $event) {
			$eventFinishReason = self::firstString([
				$event['finish_reason'] ?? null,
				$event['stop_reason'] ?? null,
				$event['finishReason'] ?? null
			], true);

			if($eventFinishReason !== '') {
				$finishReason = $eventFinishReason;
				break;
			}
		}

		$durationMs = $startedAt !== null
			? max(0.0, (microtime(true) - $startedAt) * 1000)
			: null;
		$extra = isset($hints['extra']) && is_array($hints['extra']) ? $hints['extra'] : [];
		$extra['stream'] = true;
		$extra['metadata_event_count'] = count($events);

		if(isset($hints['adapter']) && is_string($hints['adapter']) && $hints['adapter'] !== '') {
			$extra['adapter'] = $hints['adapter'];
		}

		return new AiResultMetadata(
			'chat',
			$provider,
			$model,
			$requestId,
			$createdAt,
			$durationMs,
			$finishReason,
			$usage,
			$extra
		);
	}

	/**
	 * @param array<int,mixed> $rawResponses
	 * @param array<string,mixed> $hints
	 */
	public static function aggregateMetadata(
		string $operation,
		array $rawResponses,
		array $hints = [],
		?float $startedAt = null
	): AiResultMetadata {
		$usage = AiUsage::none();
		$requestIds = [];
		$finishReason = null;
		$model = self::firstString([$hints['model'] ?? null]);
		$createdAt = null;

		foreach($rawResponses as $raw) {
			$metadata = self::metadata($operation, $raw, $hints);
			$usage = $usage->merge($metadata->getUsage());

			if($metadata->getRequestId() !== '') {
				$requestIds[] = $metadata->getRequestId();
			}
			if($model === '' && $metadata->getModel() !== '') {
				$model = $metadata->getModel();
			}
			if($createdAt === null && $metadata->getCreatedAt() !== null) {
				$createdAt = $metadata->getCreatedAt();
			}
			if($metadata->getFinishReason() !== null) {
				$finishReason = $metadata->getFinishReason();
			}
		}

		$durationMs = $startedAt !== null ? max(0.0, (microtime(true) - $startedAt) * 1000) : null;
		$extra = isset($hints['extra']) && is_array($hints['extra']) ? $hints['extra'] : [];
		$extra['request_ids'] = $requestIds;
		$extra['request_count'] = count($rawResponses);
		if(isset($hints['adapter']) && is_string($hints['adapter']) && $hints['adapter'] !== '') {
			$extra['adapter'] = $hints['adapter'];
		}

		if(isset($hints['usage_metrics']) && is_array($hints['usage_metrics'])) {
			$usage = $usage->merge(new AiUsage(metrics: $hints['usage_metrics']));
		}

		return new AiResultMetadata(
			$operation,
			self::firstString([$hints['provider'] ?? null]),
			$model,
			$requestIds[0] ?? '',
			$createdAt,
			$durationMs,
			$finishReason,
			$usage,
			$extra
		);
	}

	/**
	 * @return array{0:string,1:array<int,AiToolCall>,2:bool}
	 */
	private static function extractChatPayload(array $raw): array {
		if(isset($raw['choices'][0]['message']) && is_array($raw['choices'][0]['message'])) {
			$message = $raw['choices'][0]['message'];
			return [
				self::stringContent($message['content'] ?? ''),
				self::normalizeToolCalls($message['tool_calls'] ?? []),
				true
			];
		}

		if(isset($raw['message']) && is_array($raw['message'])) {
			$message = $raw['message'];
			return [
				self::stringContent($message['content'] ?? ''),
				self::normalizeToolCalls($message['tool_calls'] ?? []),
				true
			];
		}

		if(isset($raw['content']) && is_array($raw['content'])) {
			$content = [];
			$toolCalls = [];

			foreach($raw['content'] as $index => $block) {
				if(!is_array($block)) {
					continue;
				}

				$type = (string)($block['type'] ?? '');
				if($type === 'text' && is_string($block['text'] ?? null)) {
					$content[] = $block['text'];
				}
				if($type === 'tool_use') {
					$toolCalls[] = new AiToolCall(
						(string)($block['id'] ?? ('toolcall_' . $index)),
						(string)($block['name'] ?? ''),
						is_array($block['input'] ?? null) ? $block['input'] : [],
						['provider_type' => $type, 'index' => $index]
					);
				}
			}

			return [implode('', $content), self::filterToolCalls($toolCalls), true];
		}

		if(isset($raw['candidates'][0]['content']['parts']) && is_array($raw['candidates'][0]['content']['parts'])) {
			$content = [];
			$toolCalls = [];

			foreach($raw['candidates'][0]['content']['parts'] as $index => $part) {
				if(!is_array($part)) {
					continue;
				}
				if(is_string($part['text'] ?? null)) {
					$content[] = $part['text'];
				}
				if(is_array($part['functionCall'] ?? null)) {
					$call = $part['functionCall'];
					$toolCalls[] = new AiToolCall(
						(string)($call['id'] ?? ('toolcall_' . $index)),
						(string)($call['name'] ?? ''),
						is_array($call['args'] ?? null) ? $call['args'] : [],
						['provider_type' => 'functionCall', 'index' => $index]
					);
				}
			}

			return [implode('', $content), self::filterToolCalls($toolCalls), true];
		}

		if(is_string($raw['output_text'] ?? null)) {
			return [$raw['output_text'], self::normalizeResponseApiToolCalls($raw['output'] ?? []), true];
		}

		if(isset($raw['output']) && is_array($raw['output'])) {
			$content = [];
			$toolCalls = self::normalizeResponseApiToolCalls($raw['output']);
			self::collectOutputText($raw['output'], $content);
			return [implode('', $content), $toolCalls, true];
		}

		if(is_string($raw['content'] ?? null)) {
			return [$raw['content'], self::normalizeToolCalls($raw['tool_calls'] ?? []), true];
		}

		return ['', [], false];
	}

	/**
	 * @return array<int,AiToolCall>
	 */
	private static function normalizeToolCalls(mixed $calls): array {
		if(!is_array($calls)) {
			return [];
		}

		$out = [];
		foreach($calls as $index => $call) {
			if(!is_array($call)) {
				continue;
			}

			$function = is_array($call['function'] ?? null) ? $call['function'] : $call;
			$name = (string)($function['name'] ?? '');
			if($name === '') {
				continue;
			}

			$out[] = new AiToolCall(
				(string)($call['id'] ?? ('toolcall_' . $index)),
				$name,
				self::decodeArguments($function['arguments'] ?? ($call['arguments'] ?? [])),
				[
					'provider_type' => (string)($call['type'] ?? 'function'),
					'index' => is_numeric($call['index'] ?? null) ? (int)$call['index'] : $index
				]
			);
		}

		return $out;
	}

	/**
	 * @return array<int,AiToolCall>
	 */
	private static function normalizeResponseApiToolCalls(mixed $output): array {
		if(!is_array($output)) {
			return [];
		}

		$out = [];
		foreach($output as $index => $item) {
			if(!is_array($item) || !in_array((string)($item['type'] ?? ''), ['function_call', 'tool_call'], true)) {
				continue;
			}

			$name = (string)($item['name'] ?? ($item['function']['name'] ?? ''));
			if($name === '') {
				continue;
			}

			$out[] = new AiToolCall(
				(string)($item['call_id'] ?? ($item['id'] ?? ('toolcall_' . $index))),
				$name,
				self::decodeArguments($item['arguments'] ?? ($item['function']['arguments'] ?? [])),
				['provider_type' => (string)($item['type'] ?? ''), 'index' => $index]
			);
		}

		return $out;
	}

	/**
	 * @param array<int,AiToolCall> $toolCalls
	 * @return array<int,AiToolCall>
	 */
	private static function filterToolCalls(array $toolCalls): array {
		return array_values(array_filter(
			$toolCalls,
			static fn(AiToolCall $call): bool => $call->getName() !== ''
		));
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function decodeArguments(mixed $arguments): array {
		if(is_array($arguments)) {
			return $arguments;
		}

		if(!is_string($arguments) || trim($arguments) === '') {
			return [];
		}

		$decoded = json_decode($arguments, true);
		return is_array($decoded) ? $decoded : ['_raw' => $arguments];
	}

	private static function stringContent(mixed $content): string {
		if(is_string($content)) {
			return $content;
		}
		if($content === null) {
			return '';
		}
		$json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return is_string($json) ? $json : '';
	}

	/**
	 * @param array<int,mixed> $output
	 * @param array<int,string> $content
	 */
	private static function collectOutputText(array $output, array &$content): void {
		foreach($output as $item) {
			if(!is_array($item)) {
				continue;
			}
			if(is_string($item['text'] ?? null)) {
				$content[] = $item['text'];
			}
			if(isset($item['content']) && is_array($item['content'])) {
				self::collectOutputText($item['content'], $content);
			}
		}
	}

	private static function usage(array $raw): AiUsage {
		$usage = [];
		$usageCandidates = [
			$raw['usage'] ?? null,
			$raw['usageMetadata'] ?? null,
			$raw['usage_metadata'] ?? null,
			$raw['message']['usage'] ?? null,
			$raw['response']['usage'] ?? null,
			$raw['full']['usage'] ?? null,
			$raw['full']['message']['usage'] ?? null
		];

		foreach($usageCandidates as $candidate) {
			if(is_array($candidate)) {
				$usage = $candidate;
				break;
			}
		}

		$input = self::firstInt([
			$usage['input_tokens'] ?? null,
			$usage['prompt_tokens'] ?? null,
			$usage['promptTokenCount'] ?? null,
			$usage['inputTokenCount'] ?? null
		]);
		$output = self::firstInt([
			$usage['output_tokens'] ?? null,
			$usage['completion_tokens'] ?? null,
			$usage['candidatesTokenCount'] ?? null,
			$usage['outputTokenCount'] ?? null
		]);
		$total = self::firstInt([
			$usage['total_tokens'] ?? null,
			$usage['totalTokenCount'] ?? null
		]);
		if($total === null && ($input !== null || $output !== null)) {
			$total = ($input ?? 0) + ($output ?? 0);
		}
		$cached = self::firstInt([
			$usage['cache_read_input_tokens'] ?? null,
			$usage['cached_input_tokens'] ?? null,
			$usage['prompt_tokens_details']['cached_tokens'] ?? null,
			$usage['input_tokens_details']['cached_tokens'] ?? null,
			$usage['cachedContentTokenCount'] ?? null
		]);
		$reasoning = self::firstInt([
			$usage['completion_tokens_details']['reasoning_tokens'] ?? null,
			$usage['output_tokens_details']['reasoning_tokens'] ?? null,
			$usage['thoughtsTokenCount'] ?? null,
			$usage['reasoning_tokens'] ?? null
		]);

		$metrics = [];
		self::collectNumericMetrics($usage, '', $metrics);

		return new AiUsage($input, $output, $total, $cached, $reasoning, $metrics, $usage);
	}

	/**
	 * @param array<string,int|float> $out
	 */
	private static function collectNumericMetrics(mixed $value, string $prefix, array &$out): void {
		if(!is_array($value)) {
			return;
		}

		foreach($value as $key => $child) {
			$name = $prefix === '' ? (string)$key : $prefix . '.' . $key;
			if(is_int($child) || is_float($child)) {
				$out[$name] = $child;
				continue;
			}
			if(is_numeric($child) && is_string($child)) {
				$out[$name] = str_contains($child, '.') ? (float)$child : (int)$child;
				continue;
			}
			self::collectNumericMetrics($child, $name, $out);
		}
	}

	private static function usageScore(AiUsage $usage): int {
		$total = $usage->getTotalTokens();

		if($total !== null) {
			return $total;
		}

		$input = $usage->getInputTokens();
		$output = $usage->getOutputTokens();

		if($input !== null || $output !== null) {
			return ($input ?? 0) + ($output ?? 0);
		}

		return $usage->getMetrics() !== [] ? 0 : -1;
	}

	/**
	 * @param array<int,mixed> $values
	 */
	private static function firstString(array $values, bool $allowNumeric = false): string {
		foreach($values as $value) {
			if(is_string($value) && trim($value) !== '') {
				return trim($value);
			}
			if($allowNumeric && is_numeric($value)) {
				return (string)$value;
			}
		}

		return '';
	}

	/**
	 * @param array<int,mixed> $values
	 */
	private static function firstInt(array $values): ?int {
		foreach($values as $value) {
			if(is_int($value)) {
				return $value;
			}
			if(is_numeric($value)) {
				return (int)$value;
			}
		}

		return null;
	}
}

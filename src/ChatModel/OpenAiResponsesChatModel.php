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

namespace MissionBay\ChatModel;

use RuntimeException;

/**
 * OpenAI Responses wire format for the existing chat-model contract.
 *
 * Reuses the OpenAI transport, file lifecycle, normalized results and SSE
 * parsing infrastructure. Reasoning output is replayed in stateless tool
 * continuations, never stored by the OpenAI Responses service.
 */
class OpenAiResponsesChatModel extends OpenAiChatModel {

	public static function getName(): string {
		return 'openairesponseschatmodel';
	}

	protected function getDefaultChatCompletionPath(): string {
		return '/v1/responses';
	}

	public function raw(array $messages, array $tools = []): mixed {
		$response = parent::raw($messages, $tools);
		if(!is_array($response) || ($response['status'] ?? null) !== 'completed') {
			$error = is_array($response['error'] ?? null) ? $response['error'] : [];
			$details = is_array($response['incomplete_details'] ?? null) ? $response['incomplete_details'] : [];
			$reason = (string)($error['message'] ?? ($details['reason'] ?? ($response['status'] ?? 'invalid response')));
			throw new RuntimeException('OpenAI Responses request did not complete: ' . $reason);
		}

		return $response;
	}

	/**
	 * @param array<int,array<string,mixed>> $messages
	 * @param array<int,array<string,mixed>> $tools
	 * @return array<string,mixed>
	 */
	protected function buildPayload(array $messages, array $tools, bool $stream): array {
		$model = $this->getModel();
		if($model === '') {
			throw new RuntimeException('Missing model name for OpenAI Responses.');
		}

		$payload = [
			'model' => $model,
			'input' => $this->buildResponseInput($messages),
			'store' => false
		];

		$maxTokens = $this->getNullableIntOption('max_tokens');
		if($maxTokens !== null) {
			if($maxTokens < 1) {
				throw new RuntimeException('OpenAI Responses max output tokens must be positive.');
			}
			$payload['max_output_tokens'] = $maxTokens;
		}

		$effort = strtolower(trim((string)($this->options['reasoning_effort'] ?? ($this->options['reasoningEffort'] ?? ''))));
		if($effort !== '') {
			if(!in_array($effort, ['none', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'], true)) {
				throw new RuntimeException('Invalid OpenAI Responses reasoning effort: ' . $effort);
			}
			$payload['reasoning'] = ['effort' => $effort];
		}

		// Sampling parameters are intentionally omitted for reasoning models.
		if($stream) {
			$payload['stream'] = true;
		}

		if($this->supportsTools() && $tools !== []) {
			$definitions = [];
			foreach($this->sanitizeTools($tools) as $tool) {
				$function = $tool['function'];
				$definition = [
					'type' => 'function',
					'name' => $function['name'],
					'parameters' => $function['parameters'],
					'strict' => false
				];
				if(isset($function['description'])) {
					$definition['description'] = $function['description'];
				}
				$definitions[] = $definition;
			}

			if($definitions !== []) {
				$payload['tools'] = $definitions;
				$payload['tool_choice'] = $this->options['tool_choice'] ?? 'auto';
			}
		}

		return $payload;
	}

	/**
	 * Converts the existing MissionBay messages into Responses input items.
	 * Original reasoning/function items are replayed for current tool rounds.
	 *
	 * @param array<int,array<string,mixed>> $messages
	 * @return array<int,array<string,mixed>>
	 */
	private function buildResponseInput(array $messages): array {
		$responseItemsByCallId = [];
		foreach($messages as $message) {
			if(!is_array($message) || ($message['role'] ?? '') !== 'assistant') {
				continue;
			}
			$items = $message['response_items'] ?? null;
			$calls = $message['tool_calls'] ?? null;
			if(!is_array($items) || !is_array($calls) || $calls === []) {
				continue;
			}
			$firstCallId = (string)($calls[0]['id'] ?? '');
			if($firstCallId !== '') {
				$responseItemsByCallId[$firstCallId] = $items;
			}
		}

		$input = [];
		foreach($this->normalizeMessages($messages) as $message) {
			$role = (string)$message['role'];

			if($role === 'tool') {
				$input[] = [
					'type' => 'function_call_output',
					'call_id' => (string)$message['tool_call_id'],
					'output' => (string)$message['content']
				];
				continue;
			}

			if($role === 'assistant' && !empty($message['tool_calls'])) {
				$firstCallId = (string)$message['tool_calls'][0]['id'];
				if(isset($responseItemsByCallId[$firstCallId])) {
					$items = $responseItemsByCallId[$firstCallId];
					$this->assertMatchingResponseItems($message['tool_calls'], $items);
					foreach($items as $item) {
						$input[] = $item;
					}
					continue;
				}

				$content = (string)($message['content'] ?? '');
				if($content !== '') {
					$input[] = ['role' => 'assistant', 'content' => $content];
				}
				foreach($message['tool_calls'] as $call) {
					$input[] = [
						'type' => 'function_call',
						'call_id' => (string)$call['id'],
						'name' => (string)$call['function']['name'],
						'arguments' => (string)$call['function']['arguments']
					];
				}
				continue;
			}

			$input[] = [
				'role' => $role,
				'content' => (string)($message['content'] ?? '')
			];
		}

		$files = $this->getFileReferences();
		if($files === []) {
			return $input;
		}

		for($index = count($input) - 1; $index >= 0; $index--) {
			if(($input[$index]['role'] ?? '') !== 'user') {
				continue;
			}

			$content = (string)$input[$index]['content'];
			$parts = [];
			foreach($files as $file) {
				$parts[] = [
					'type' => 'input_file',
					'file_id' => $file->getOpaqueId()
				];
			}
			if($content !== '') {
				$parts[] = ['type' => 'input_text', 'text' => $content];
			}
			$input[$index]['content'] = $parts;
			return $input;
		}

		throw new RuntimeException('OpenAI Responses file inputs require at least one user message.');
	}

	/**
	 * @param array<int,array<string,mixed>> $calls
	 * @param array<int,array<string,mixed>> $items
	 */
	private function assertMatchingResponseItems(array $calls, array $items): void {
		$expected = [];
		foreach($calls as $call) {
			$expected[(string)$call['id']] = (string)$call['function']['name'];
		}

		$found = [];
		foreach($items as $item) {
			if(!is_array($item) || !in_array((string)($item['type'] ?? ''), ['reasoning', 'function_call'], true)) {
				throw new RuntimeException('Invalid preserved OpenAI Responses output item.');
			}
			if($item['type'] !== 'function_call') {
				continue;
			}
			$callId = (string)($item['call_id'] ?? '');
			$functionName = (string)($item['name'] ?? '');
			if($callId === '' || !isset($expected[$callId]) || $expected[$callId] !== $functionName || isset($found[$callId])) {
				throw new RuntimeException('Preserved OpenAI Responses function call does not match the tool history.');
			}
			$found[$callId] = true;
		}

		if(count($found) !== count($expected)) {
			throw new RuntimeException('Preserved OpenAI Responses function calls are incomplete.');
		}
	}

	/**
	 * Emits text, function-call metadata and usage through the existing SSE callbacks.
	 */
	protected function handleSseEventBlock(string $eventBlock, callable $onData, ?callable $onMeta): void {
		$data = $this->extractSseDataPayload($eventBlock);
		if($data === null || $data === '[DONE]') {
			return;
		}

		$event = json_decode($data, true);
		if(!is_array($event)) {
			throw new RuntimeException('Invalid OpenAI Responses streaming event.');
		}

		$type = (string)($event['type'] ?? '');
		if($type === 'response.output_text.delta' || $type === 'response.refusal.delta') {
			$delta = $event['delta'] ?? null;
			if(is_string($delta) && $delta !== '') {
				$onData($delta);
			}
			return;
		}

		if($type === 'response.completed') {
			$response = $event['response'] ?? null;
			if(!is_array($response) || ($response['status'] ?? '') !== 'completed') {
				throw new RuntimeException('OpenAI Responses stream completed without a successful response.');
			}

			if($onMeta !== null) {
				$hasToolCalls = false;
				foreach($response['output'] ?? [] as $index => $item) {
					if(!is_array($item) || ($item['type'] ?? '') !== 'function_call') {
						continue;
					}
					$hasToolCalls = true;
					$onMeta([
						'event' => 'toolcall',
						'tool_calls' => [[
							'index' => $index,
							'id' => (string)($item['call_id'] ?? ''),
							'type' => 'function',
							'function' => [
								'name' => (string)($item['name'] ?? ''),
								'arguments' => (string)($item['arguments'] ?? '')
							]
						]]
					]);
				}
				$onMeta([
					'event' => 'meta',
					'finish_reason' => $hasToolCalls ? 'tool_calls' : 'stop',
					'full' => $response
				]);
			}
			return;
		}

		if(in_array($type, ['response.failed', 'response.incomplete', 'error'], true)) {
			$response = is_array($event['response'] ?? null) ? $event['response'] : [];
			$error = is_array($response['error'] ?? null) ? $response['error'] : [];
			$message = (string)($error['message'] ?? ($event['message'] ?? ($response['incomplete_details']['reason'] ?? $type)));
			throw new RuntimeException('OpenAI Responses stream failed: ' . $message);
		}
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class SimpleOpenAiNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'simpleopenainode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'prompt',
				description: 'The user message or question to send to the assistant.',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'system',
				description: 'The system prompt that defines assistant behavior.',
				type: 'string',
				default: 'You are a helpful assistant.',
				required: false
			),
			new AgentNodePort(
				name: 'model',
				description: 'OpenAI model to use (e.g., gpt-3.5-turbo, gpt-4).',
				type: 'string',
				default: 'gpt-3.5-turbo',
				required: false
			),
			new AgentNodePort(
				name: 'temperature',
				description: 'Controls randomness. Higher values = more creative, lower = more deterministic.',
				type: 'float',
				default: 0.7,
				required: false
			),
			new AgentNodePort(
				name: 'apikey',
				description: 'OpenAI API key for authentication.',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'response',
				description: 'The assistant\'s reply to the prompt.',
				type: 'string',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if the API call fails or input is invalid.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$prompt = $inputs['prompt'] ?? null;
		$system = $inputs['system'] ?? 'You are a helpful assistant.';
		$model = $inputs['model'] ?? 'gpt-3.5-turbo';
		$temperature = $inputs['temperature'] ?? 0.7;
		$apiKey = $inputs['apikey'] ?? null;

		if (!$apiKey || !$prompt) {
			return ['error' => $this->error('Missing OpenAI API key or prompt input')];
		}

		$memory = $context->getMemory();
		$nodeId = $this->getId();
		$history = $memory->loadNodeHistory($nodeId);

		// Build chat messages for API (reduce memory to role/content only)
		$messages = [['role' => 'system', 'content' => $system]];
		foreach ($history as $entry) {
			if (isset($entry['role'], $entry['content'])) {
				$messages[] = ['role' => $entry['role'], 'content' => $entry['content']];
			}
		}
		$messages[] = ['role' => 'user', 'content' => $prompt];

		$body = json_encode([
			'model' => $model,
			'messages' => $messages,
			'temperature' => $temperature
		]);

		$ch = curl_init('https://api.openai.com/v1/chat/completions');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $apiKey
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

		$response = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error || !$response) {
			return ['error' => $this->error('OpenAI request failed: ' . $error)];
		}

		$data = json_decode($response, true);
		$content = $data['choices'][0]['message']['content'] ?? null;

		if (!$content) {
			return ['error' => $this->error('Invalid OpenAI response')];
		}

		// --- Store conversation in memory ---
		$userMessage = [
			'id'        => uniqid('msg_', true),
			'role'      => 'user',
			'content'   => $prompt,
			'timestamp' => (new \DateTimeImmutable())->format('c'),
			'feedback'  => null
		];
		$assistantMessage = [
			'id'        => uniqid('msg_', true),
			'role'      => 'assistant',
			'content'   => $content,
			'timestamp' => (new \DateTimeImmutable())->format('c'),
			'feedback'  => null
		];

		$memory->appendNodeHistory($nodeId, $userMessage);
		$memory->appendNodeHistory($nodeId, $assistantMessage);

		return ['response' => $content];
	}

	public function getDescription(): string {
		return 'Sends a user prompt to the OpenAI Chat API with an optional system message and returns a single assistant response. Supports temperature to control randomness. A lightweight alternative to full agent-based communication.';
	}
}


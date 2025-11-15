<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class SimpleLlamaNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'simplellamanode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'endpoint',
				description: 'HTTP endpoint of the LLaMA server (e.g., http://localhost:11434/api/generate).',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'model',
				description: 'The LLaMA model to use (e.g., llama3).',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'prompt',
				description: 'The user message or question to send to the model.',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'system',
				description: 'The system prompt that defines assistant behavior.',
				type: 'string',
				default: '',
				required: false
			),
			new AgentNodePort(
				name: 'temperature',
				description: 'Controls randomness. Higher values = more creative, lower = more deterministic.',
				type: 'float',
				default: 0.7,
				required: false
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
				description: 'Error message if the API call fails.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		$endpoint = $inputs['endpoint'] ?? '';
		$model = $inputs['model'] ?? '';
		$prompt = $inputs['prompt'] ?? '';
		$system = $inputs['system'] ?? '';
		$temperature = $inputs['temperature'] ?? 0.7;

		if (!$endpoint || !$model || !$prompt) {
			return ['error' => $this->error('Missing required input: endpoint, model or prompt')];
		}

		// Build full prompt for LLaMA
		$fullPrompt = $system ? $system . "\n" . $prompt : $prompt;

		$body = json_encode([
			'model' => $model,
			'prompt' => $fullPrompt,
			'stream' => false,
			'temperature' => $temperature
		]);

		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json'
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

		$response = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error || !$response) {
			return ['error' => $this->error('LLaMA request failed: ' . $error)];
		}

		$data = json_decode($response, true);
		$content = $data['response'] ?? null;

		if (!$content) {
			return ['error' => $this->error('Invalid LLaMA response')];
		}

		// --- Memory integration ---
		$memory = $context->getMemory();
		$nodeId = $this->getId();

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
		return 'Sends a prompt to a local or remote LLaMA model endpoint and returns the generated response. Accepts optional system prompt and temperature for behavior control. Stores conversation in memory.';
	}
}


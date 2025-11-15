<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class OpenAiResponseNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'openairesponsenode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'messages',
				description: 'An array of message objects, each with "from", "body", and optional "subject".',
				type: 'array',
				required: true
			),
			new AgentNodePort(
				name: 'model',
				description: 'The OpenAI model to use (e.g., gpt-3.5-turbo, gpt-4).',
				type: 'string',
				default: 'gpt-3.5-turbo',
				required: false
			),
			new AgentNodePort(
				name: 'apikey',
				description: 'The OpenAI API key used for authentication.',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'responses',
				description: 'An array of generated responses (to, subject, body) for each input message.',
				type: 'array',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if input or API call failed.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		$messages = $inputs['messages'] ?? null;
		$model = $inputs['model'] ?? 'gpt-3.5-turbo';
		$apiKey = $inputs['apikey'] ?? null;

		if (!is_array($messages)) {
			return ['error' => $this->error('Input "messages" must be an array')];
		}

		if (!$apiKey) {
			return ['error' => $this->error('Missing OpenAI API key input')];
		}

		$memory = $context->getMemory();
		$nodeId = $this->getId();
		$responses = [];

		// Load existing history (already in rich format with id/content/etc.)
		$history = $memory->loadNodeHistory($nodeId);

		foreach ($messages as $msg) {
			$body = $msg['body'] ?? '';
			$subject = $msg['subject'] ?? '';
			$to = $msg['from'] ?? '';

			// Build conversation for API call: reduce to role/content only
			$chatMessages = [['role' => 'system', 'content' => 'You are a helpful assistant.']];
			foreach ($history as $entry) {
				if (isset($entry['role'], $entry['content'])) {
					$chatMessages[] = ['role' => $entry['role'], 'content' => $entry['content']];
				}
			}
			$chatMessages[] = ['role' => 'user', 'content' => $body];

			// Call OpenAI
			$reply = $this->callOpenAi($apiKey, $model, $chatMessages);

			// Collect response (compatibility: simple string fields)
			$responses[] = [
				'to' => $to,
				'subject' => 'Re: ' . $subject,
				'body' => $reply
			];

			// Store full messages in memory
			$userMessage = [
				'id'        => uniqid('msg_', true),
				'role'      => 'user',
				'content'   => $body,
				'timestamp' => (new \DateTimeImmutable())->format('c'),
				'feedback'  => null
			];
			$assistantMessage = [
				'id'        => uniqid('msg_', true),
				'role'      => 'assistant',
				'content'   => $reply,
				'timestamp' => (new \DateTimeImmutable())->format('c'),
				'feedback'  => null
			];

			$memory->appendNodeHistory($nodeId, $userMessage);
			$memory->appendNodeHistory($nodeId, $assistantMessage);
		}

		return ['responses' => $responses];
	}

	protected function buildPrompt(array $history, string $newMessage): string {
		$prompt = "";
		foreach ($history as $entry) {
			if (is_array($entry) && isset($entry['role'], $entry['content'])) {
				$prompt .= ucfirst($entry['role']) . ": " . $entry['content'] . "\n";
			}
		}
		$prompt .= "User: " . $newMessage . "\nBot:";
		return $prompt;
	}

	protected function callOpenAi(string $apiKey, string $model, array $chatMessages): string {
		$body = json_encode([
			'model' => $model,
			'messages' => $chatMessages
		]);

		$ch = curl_init('https://api.openai.com/v1/chat/completions');
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $apiKey,
			],
			CURLOPT_POSTFIELDS => $body,
		]);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode !== 200 || !$response) {
			return 'Error: OpenAI API call failed (' . $httpCode . ')';
		}

		$data = json_decode($response, true);
		return $data['choices'][0]['message']['content'] ?? '(no response)';
	}

	public function getDescription(): string {
		return 'Sends a list of user messages to the OpenAI Chat API and returns assistant responses. Maintains conversational history using AgentMemory. Useful for AI-based replies, summarization, or creative text generation.';
	}
}


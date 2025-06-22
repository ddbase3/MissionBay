<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Agent\AgentContext;

class OpenAiResponseNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'openairesponsenode';
	}

	public function getInputDefinitions(): array {
		return ['messages', 'model', 'apikey'];
	}

	public function getOutputDefinitions(): array {
		return ['responses', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$messages = $inputs['messages'] ?? null;
		$model = $inputs['model'] ?? 'gpt-3.5-turbo';
		$apiKey = $inputs['apikey'] ?? null;

		if (!is_array($messages)) {
			return ['error' => 'Input "messages" must be an array'];
		}

		if (!$apiKey) {
			return ['error' => 'Missing OpenAI API key input'];
		}

		$memory = $context->getMemory();
		$responses = [];

		foreach ($messages as $msg) {
			$userId = $msg['from'] ?? 'unknown';
			$body = $msg['body'] ?? '';
			$subject = $msg['subject'] ?? '';

			$history = $memory->load($userId);
			$prompt = $this->buildPrompt($history, $body);

			$chatMessages = [
				['role' => 'system', 'content' => 'You are a helpful assistant.'],
				['role' => 'user', 'content' => $prompt]
			];

			$reply = $this->callOpenAi($apiKey, $model, $chatMessages);

			$responses[] = [
				'to' => $userId,
				'subject' => 'Re: ' . $subject,
				'body' => $reply
			];

			$memory->remember($userId, 'user', $body);
			$memory->remember($userId, 'bot', $reply);
		}

		return ['responses' => $responses];
	}

	protected function buildPrompt(array $history, string $newMessage): string {
		$prompt = "";
		foreach ($history as [$role, $msg]) {
			$prompt .= ucfirst($role) . ": " . $msg . "\n";
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
}


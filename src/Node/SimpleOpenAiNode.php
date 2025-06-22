<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Agent\AgentContext;

class SimpleOpenAiNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'simpleopenainode';
	}

	public function getInputDefinitions(): array {
		return ['prompt', 'system', 'model', 'apikey'];
	}

	public function getOutputDefinitions(): array {
		return ['response', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$prompt = $inputs['prompt'] ?? null;
		$system = $inputs['system'] ?? 'You are a helpful assistant.';
		$model = $inputs['model'] ?? 'gpt-3.5-turbo';
		$apiKey = $inputs['apikey'] ?? null;

		if (!$apiKey || !$prompt) {
			return ['error' => 'Missing OpenAI API key or prompt input'];
		}

		$body = json_encode([
			'model' => $model,
			'messages' => [
				['role' => 'system', 'content' => $system],
				['role' => 'user', 'content' => $prompt],
			]
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
			return ['error' => 'OpenAI request failed: ' . $error];
		}

		$data = json_decode($response, true);
		$content = $data['choices'][0]['message']['content'] ?? null;

		if (!$content) {
			return ['error' => 'Invalid OpenAI response'];
		}

		return ['response' => $content];
	}
}


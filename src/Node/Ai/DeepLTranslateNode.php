<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentContext;
use MissionBay\Node\AbstractAgentNode;
use MissionBay\Agent\AgentNodePort;

class DeepLTranslateNode extends AbstractAgentNode
{
	public static function getName(): string {
		return 'deepltranslatenode';
	}

	public function getDescription(): string {
		return 'Translates text using the DeepL Translation API.';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'apikey',
				description: 'Your DeepL API key.',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'text',
				description: 'Text to translate.',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'source',
				description: 'Source language (e.g. EN, DE). Optional for autodetect.',
				type: 'string',
				required: false
			),
			new AgentNodePort(
				name: 'target',
				description: 'Target language (e.g. DE, EN).',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'translated',
				description: 'Translated result.',
				type: 'string'
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if translation fails.',
				type: 'string'
			)
		];
	}

	public function execute(array $inputs, IAgentContext $context): array {
		$apiKey = trim($inputs['apikey'] ?? '');
		$text = trim($inputs['text'] ?? '');
		$target = strtoupper(trim($inputs['target'] ?? ''));
		$source = strtoupper(trim($inputs['source'] ?? ''));

		if ($apiKey === '' || $text === '' || $target === '') {
			return ['error' => 'Missing required input: apikey, text or target.'];
		}

		$params = [
			'auth_key' => $apiKey,
			'text' => $text,
			'target_lang' => $target
		];
		if ($source !== '') {
			$params['source_lang'] = $source;
		}

		$body = http_build_query($params);

		$opts = [
			'http' => [
				'method' => 'POST',
				'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
				'content' => $body,
				'timeout' => 10
			]
		];

		$ctx = stream_context_create($opts);
		$response = file_get_contents('https://api-free.deepl.com/v2/translate', false, $ctx);

		if ($response === false) {
			$error = error_get_last();
			return ['error' => 'HTTP request failed: ' . ($error['message'] ?? 'unknown error')];
		}

		$data = json_decode($response, true);

		if (!is_array($data) || !isset($data['translations'][0]['text'])) {
			return ['error' => 'Invalid API response from DeepL.'];
		}

		return ['translated' => $data['translations'][0]['text']];
	}
}


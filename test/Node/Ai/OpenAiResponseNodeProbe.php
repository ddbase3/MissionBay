<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Ai;

use MissionBay\Node\Ai\OpenAiResponseNode;

final class OpenAiResponseNodeProbe extends OpenAiResponseNode {

	/** @var array<int,array{api_key:string,model:string,messages:array}> */
	public array $requests = [];

	public string $reply = 'Generated answer';

	public function renderPrompt(array $history, string $message): string {
		return $this->buildPrompt($history, $message);
	}

	protected function callOpenAi(string $apiKey, string $model, array $chatMessages): string {
		$this->requests[] = [
			'api_key' => $apiKey,
			'model' => $model,
			'messages' => $chatMessages
		];
		return $this->reply;
	}
}

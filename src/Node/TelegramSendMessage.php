<?php

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class TelegramSendMessage extends AbstractAgentNode {

	public static function getName(): string {
		return "telegramsendmessage";
	}

	public function getDescription(): string {
		return "Sends a message via Telegram bot using bot token and chat ID.";
	}

	public function getInputDefinitions(): array {
		return ["bot_token", "chat_id", "message"];
	}

	public function getOutputDefinitions(): array {
		return ["message_id", "error"];
	}

	public function execute(array $input, AgentContext $context): array {
		if (empty($input['bot_token']) || empty($input['chat_id']) || empty($input['message'])) {
			return ["error" => "Missing required input: bot_token, chat_id, and message are all required."];
		}

		$botToken = $input["bot_token"];
		$chatId = $input["chat_id"];
		$message = $input["message"];

		$url = "https://api.telegram.org/bot{$botToken}/sendMessage";
		
		$params = [
			'chat_id' => $chatId,
			'text' => $message,
			'parse_mode' => 'HTML'
		];

		$options = [
			"http" => [
				"header"  => "Content-type: application/x-www-form-urlencoded",
				"method"  => "POST",
				"content" => http_build_query($params),
			],
		];
		
		$contextStream = stream_context_create($options);
		$result = @file_get_contents($url, false, $contextStream);

		if ($result === false) {
			return ["error" => "Failed to send message. HTTP request error."];
		}

		$response = json_decode($result, true);

		if (isset($response['ok']) && $response['ok']) {
			return ["message_id" => $response['result']['message_id'] ?? null];
		} else {
			return ["error" => $response['description'] ?? "Unknown error from Telegram API."];
		}
	}
}


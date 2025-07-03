<?php

namespace MissionBay\Node\Message;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class TelegramSendMessage extends AbstractAgentNode {

	public static function getName(): string {
		return "telegramsendmessage";
	}

	public function getDescription(): string {
		return "Sends a message via Telegram bot using bot token and chat ID.";
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'bot_token',
				description: 'The Telegram bot API token.',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'chat_id',
				description: 'The recipient chat ID (user or group).',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'message',
				description: 'The message text to be sent.',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'message_id',
				description: 'The ID of the sent Telegram message.',
				type: 'int',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if the Telegram API request failed.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $input, IAgentContext $context): array {
		if (empty($input['bot_token']) || empty($input['chat_id']) || empty($input['message'])) {
			return ["error" => $this->error("Missing required input: bot_token, chat_id, and message are all required.")];
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
			return ["error" => $this->error("Failed to send message. HTTP request error.")];
		}

		$response = json_decode($result, true);

		if (isset($response['ok']) && $response['ok']) {
			return ["message_id" => $response['result']['message_id'] ?? null];
		} else {
			return ["error" => $this->error($response['description'] ?? "Unknown error from Telegram API.")];
		}
	}
}


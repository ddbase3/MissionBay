<?php

namespace MissionBay\Node\Message;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentResource;
use MissionBay\Node\AbstractAgentNode;
use Base3\Logger\Api\ILogger;

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

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger for status messages.',
				interface: ILogger::class,
				maxConnections: 1
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		/** @var ILogger|null $logger */
		$logger = $resources['logger'][0] ?? null;
		$scope = 'telegram';

		if (empty($inputs['bot_token']) || empty($inputs['chat_id']) || empty($inputs['message'])) {
			$msg = "Missing required input: bot_token, chat_id, and message are all required.";
			if ($logger instanceof ILogger) $logger->error($msg, ['scope' => $scope]);
			return ["error" => $this->error($msg)];
		}

		$botToken = $inputs["bot_token"];
		$chatId = $inputs["chat_id"];
		$message = $inputs["message"];

		if ($logger instanceof ILogger) {
			$logger->info("Sending message to chat $chatId: " . substr($message, 0, 80), ['scope' => $scope]);
		}

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
			$msg = "Failed to send message. HTTP request error.";
			if ($logger instanceof ILogger) $logger->error($msg, ['scope' => $scope]);
			return ["error" => $this->error($msg)];
		}

		$response = json_decode($result, true);

		if (isset($response['ok']) && $response['ok']) {
			$mid = $response['result']['message_id'] ?? null;
			if ($logger instanceof ILogger) $logger->info("Telegram message sent successfully. ID: $mid", ['scope' => $scope]);
			return ["message_id" => $mid];
		} else {
			$msg = $response['description'] ?? "Unknown error from Telegram API.";
			if ($logger instanceof ILogger) $logger->error("Telegram API error: $msg", ['scope' => $scope]);
			return ["error" => $this->error($msg)];
		}
	}
}


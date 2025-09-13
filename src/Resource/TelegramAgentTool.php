<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * TelegramAgentTool
 *
 * Sends messages via Telegram bot.
 * Uses IAgentConfigValueResolver to resolve bottoken and chatid from config.
 */
class TelegramAgentTool extends AbstractAgentResource implements IAgentTool {

	protected IAgentConfigValueResolver $resolver;

	protected array|string|null $botTokenConfig = null;
	protected array|string|null $chatIdConfig   = null;

	protected ?string $botToken = null;
	protected ?string $chatId   = null;

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'telegramagenttool';
	}

	public function getDescription(): string {
		return 'Sends a message via a configured Telegram bot.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->botTokenConfig = $config['bottoken'] ?? null;
		$this->chatIdConfig   = $config['chatid']   ?? null;

		$this->botToken = $this->resolver->resolveValue($this->botTokenConfig);
		$this->chatId   = $this->resolver->resolveValue($this->chatIdConfig);
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'send_telegram_message',
				'description' => 'Sends a message to a predefined Telegram account using a configured bot.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'message' => [
							'type' => 'string',
							'description' => 'The text message to send (HTML supported).'
						]
					],
					'required' => ['message']
				]
			]
		]];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if ($toolName !== 'send_telegram_message') {
			throw new \InvalidArgumentException("Unsupported tool: $toolName");
		}

		$message = $arguments['message'] ?? null;
		if (!$message) {
			return ['error' => 'Missing required parameter: message'];
		}

		if (empty($this->botToken) || empty($this->chatId)) {
			return ['error' => 'Telegram configuration (bottoken/chatid) not resolved'];
		}

		$url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
		$params = [
			'chat_id' => $this->chatId,
			'text' => $message,
			'parse_mode' => 'HTML'
		];

		$options = [
			"http" => [
				"header"  => "Content-type: application/x-www-form-urlencoded",
				"method"  => "POST",
				"content" => http_build_query($params),
				"timeout" => 10
			],
		];

		$ctx = stream_context_create($options);
		$result = @file_get_contents($url, false, $ctx);

		if ($result === false) {
			return ['error' => 'HTTP request failed while sending Telegram message'];
		}

		$response = json_decode($result, true);
		if (isset($response['ok']) && $response['ok']) {
			return [
				'message_id' => $response['result']['message_id'] ?? null,
				'chat_id'    => $response['result']['chat']['id'] ?? null
			];
		}

		return [
			'error' => $response['description'] ?? 'Unknown error from Telegram API'
		];
	}
}


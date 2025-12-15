<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;

/**
 * BlockChatbotAgentTool
 *
 * Tool zum Blockieren des Chatbots durch Setzen einer Session-Variable.
 * Prüfen tut nicht das Tool, sondern z. B. der ConditionalPassNode.
 */
class BlockChatbotAgentTool extends AbstractAgentResource implements IAgentTool {

	public static function getName(): string {
		return 'blockchatbotagenttool';
	}

	public function getDescription(): string {
		return 'Sets $_SESSION["chatblocker"] = "block". '
		     . 'Flows können diese Variable prüfen und darauf reagieren.';
	}

	/**
	 * OpenAI-kompatible Tool-Definition
	 */
	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Chatbot Blocking',
			'category' => 'control',
			'tags' => ['block', 'safe', 'security'],
			'priority' => 50,
			'function' => [
				'name' => 'block_chatbot',
				'description' => 'Blocks the chatbot by setting $_SESSION["chatblocker"] = "block". '
				               . 'Nodes like ConditionalPassNode can check this value.',
				'parameters' => [
					'type' => 'object',
					'properties' => new \stdClass(),
					'required' => []
				]
			]
		]];
	}

	/**
	 * Führt den Tool-Call aus.
	 */
	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if ($toolName !== 'block_chatbot') {
			throw new \InvalidArgumentException("Unsupported tool: $toolName");
		}

		if (session_status() !== PHP_SESSION_ACTIVE) {
			session_start();
		}

		$_SESSION['chatblocker'] = 'block';

		return [
			'status'  => 'ok',
			'message' => 'Chatbot blocked: $_SESSION["chatblocker"]="block" gesetzt.'
		];
	}
}

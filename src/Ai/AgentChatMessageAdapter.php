<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Ai;

use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiToolCall;

/**
 * Adapts provider-neutral chat DTOs to MissionBay's current internal
 * message-stack representation.
 *
 * The compatibility representation stays inside MissionBay. Foundation
 * DTOs do not expose an OpenAI-shaped message contract.
 */
final class AgentChatMessageAdapter {

	/**
	 * @return array<string,mixed>
	 */
	public static function assistantMessage(AiChatResult $result): array {
		$message = [
			'role' => 'assistant',
			'content' => $result->getContent()
		];

		if($result->hasToolCalls()) {
			$message['tool_calls'] = array_map(
				static fn(AiToolCall $toolCall): array => self::toolCall($toolCall),
				$result->getToolCalls()
			);
		}

		return $message;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function toolCall(AiToolCall $toolCall): array {
		$arguments = json_encode(
			$toolCall->getArguments(),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		return [
			'id' => $toolCall->getId(),
			'type' => 'function',
			'function' => [
				'name' => $toolCall->getName(),
				'arguments' => is_string($arguments) ? $arguments : '{}'
			],
			'metadata' => $toolCall->getMetadata()
		];
	}
}

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

namespace MissionBay\ServiceDriver;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Api\IServiceDriverDefinition;
use MissionBay\ChatModel\OpenAiResponsesChatModel;

final class OpenAiResponsesChatServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'openairesponseschatservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'openai-responses';
	}

	public function getServiceType(): string {
		return 'llm';
	}

	public function getLabel(): string {
		return 'OpenAI Responses';
	}

	public function requiresConnection(): bool {
		return true;
	}

	public function getSupportedConnectionTypes(): array {
		return ['http'];
	}

	public function getImplementationInterface(): string {
		return IAiChatModel::class;
	}

	public function getImplementationName(): string {
		return OpenAiResponsesChatModel::getName();
	}

	public function getConfigSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'model' => [
					'type' => 'string',
					'label' => 'Model',
					'default' => 'gpt-6-luna',
					'required' => true
				],
				'maxTokens' => [
					'type' => 'integer',
					'label' => 'Max output tokens (includes reasoning)',
					'default' => 8192
				],
				'reasoningEffort' => [
					'type' => 'string',
					'label' => 'Reasoning effort',
					'runtimeKey' => 'reasoning_effort',
					'default' => 'medium',
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'llm',
			'driver' => 'openai-responses',
			'model' => 'gpt-6-luna',
			'enabled' => true,
			'options' => [
				'maxTokens' => 8192,
				'reasoningEffort' => 'medium',
				'chatCompletionPath' => '/v1/responses'
			]
		];
	}
}

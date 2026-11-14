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

namespace Base3\MissionBay\Test\ChatModel;

use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use PHPUnit\Framework\TestCase;

final class NormalizedChatModelTraitTestDouble1 {
			use NormalizedChatModelTrait;

			public function __construct(AiProviderRequestEventDispatcher $providerRequestEvents) {
				$this->initializeProviderRequestEvents($providerRequestEvents);
			}

			public static function getName(): string {
				return 'normalizedchatmodeltraittest';
			}

			public function raw(array $messages, array $tools = []): mixed {
				return [];
			}

			public function stream(
				array $messages,
				array $tools,
				callable $onData,
				?callable $onMeta = null
			): void {
				$onData('I will check.');
				$onMeta([
					'event' => 'toolcall',
					'tool_calls' => [[
						'index' => 0,
						'id' => 'call-1',
						'type' => 'function',
						'function' => [
							'name' => 'lookup',
							'arguments' => '{"query":"'
						]
					]]
				]);
				$onMeta([
					'event' => 'toolcall',
					'tool_calls' => [[
						'index' => 0,
						'function' => ['arguments' => 'BASE3"}']
					]]
				]);
				$onMeta([
					'event' => 'meta',
					'finish_reason' => 'tool_calls',
					'full' => [
						'id' => 'stream-request',
						'model' => 'stream-test',
						'usage' => [
							'prompt_tokens' => 8,
							'completion_tokens' => 3,
							'total_tokens' => 11
						]
					]
				]);
			}

			public function getOptions(): array {
				return [
					'provider' => 'test',
					'model' => 'stream-test'
				];
			}
		}

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

final class NormalizedChatModelTraitTestDouble2 {
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
				$onData('Hello');
				$onData(' world');
				$onMeta([
					'event' => 'meta',
					'finish_reason' => 'stop'
				]);
			}

			public function getOptions(): array {
				return ['provider' => 'test'];
			}
		}

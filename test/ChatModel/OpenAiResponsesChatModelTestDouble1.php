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

use Base3\AssistantFoundation\Api\IAiProvider;
use Base3\AssistantFoundation\Dto\AiProviderFileReference;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\Ai\AgentChatMessageAdapter;
use Base3\MissionBay\ChatModel\OpenAiResponsesChatModel;
use PHPUnit\Framework\TestCase;

final class OpenAiResponsesChatModelTestDouble1 extends OpenAiResponsesChatModel {

			public function __construct(
				IClassMap $classMap,
				AiProviderRequestEventDispatcher $events,
				private readonly IAiProvider $testProvider
			) {
				parent::__construct($classMap, $events);
			}

			protected function getProvider(): IAiProvider {
				return $this->testProvider;
			}
		}

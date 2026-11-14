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

namespace Base3\MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\Resource\MistralChatModelAgentResource;
use Base3\MissionBay\Api\IAgentConfigValueResolver;

final class MistralChatModelAgentResourceTestDouble2 extends MistralChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hallo!']]
					]
				];
			}
		}

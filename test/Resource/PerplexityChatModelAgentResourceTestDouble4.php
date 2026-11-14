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
use Base3\MissionBay\Resource\PerplexityChatModelAgentResource;
use Base3\MissionBay\Api\IAgentConfigValueResolver;

final class PerplexityChatModelAgentResourceTestDouble4 extends PerplexityChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				$data = [
					'choices' => [
						['message' => ['content' => 'Tool call: notify("hello")']]
					]
				];

				$ref = new \ReflectionClass(PerplexityChatModelAgentResource::class);
				$m = $ref->getMethod('extractToolCallFromText');
				$m->setAccessible(true);

				$text = $data['choices'][0]['message']['content'] ?? '';
				$toolCall = $m->invoke($this, $text);

				if ($toolCall !== null) {
					$data['choices'][0]['message']['tool_calls'] = [$toolCall];
				}

				return $data;
			}
		}

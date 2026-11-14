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
use Base3\MissionBay\Resource\GenericChatModelAgentResource;
use Base3\MissionBay\Api\IAgentConfigValueResolver;

final class GenericChatModelAgentResourceTestDouble4 extends GenericChatModelAgentResource {
			public function runWriteFunction(string $chunk, callable $onData, ?callable $onMeta = null): int {
				$fn = function ($ch, $chunk) use ($onData, $onMeta) {
					$lines = preg_split("/\r\n|\n|\r/", $chunk);
					foreach ($lines as $line) {
						$line = trim($line);
						if ($line === '' || !str_starts_with($line, 'data:')) {
							continue;
						}
						$data = trim(substr($line, 5));
						if ($data === '[DONE]') {
							if ($onMeta !== null) {
								$onMeta(['event' => 'done']);
							}
							continue;
						}
						$json = json_decode($data, true);
						if (!is_array($json)) {
							continue;
						}
						$choice = $json['choices'][0] ?? [];

						if (isset($choice['delta']['content'])) {
							$onData($choice['delta']['content']);
						}

						if ($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
							$onMeta([
								'event' => 'meta',
								'finish_reason' => $choice['finish_reason'],
								'full' => $json
							]);
						}

						if (!empty($choice['delta']['tool_calls']) && $onMeta !== null) {
							$onMeta([
								'event' => 'toolcall',
								'tool_calls' => $choice['delta']['tool_calls']
							]);
						}
					}
					return strlen($chunk);
				};

				return $fn(null, $chunk);
			}
		}

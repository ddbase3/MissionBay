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
use Base3\MissionBay\Resource\AnthropicChatModelAgentResource;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\AssistantFoundation\Api\IAiChatModel;

final class AnthropicChatModelAgentResourceTestDouble2 extends AnthropicChatModelAgentResource {

			/**
			 * Runs the SAME parsing logic as the CURLOPT_WRITEFUNCTION in stream(),
			 * but without any curl dependency (stable unit test).
			 *
			 * @param string[] $chunks
			 */
			public function runParser(array $chunks, callable $onData, ?callable $onMeta = null): void {
				$buffer = '';
				$eventName = '';

				foreach ($chunks as $chunk) {
					$buffer .= $chunk;

					while (($pos = strpos($buffer, "\n")) !== false) {
						$line = substr($buffer, 0, $pos);
						$buffer = substr($buffer, $pos + 1);

						$line = rtrim($line, "\r");
						$trim = trim($line);

						if ($trim === '') {
							$eventName = '';
							continue;
						}

						if (str_starts_with($trim, 'event:')) {
							$eventName = trim(substr($trim, 6));
							continue;
						}

						if (!str_starts_with($trim, 'data:')) {
							continue;
						}

						$dataStr = trim(substr($trim, 5));
						if ($dataStr === '' || $dataStr === '[DONE]') {
							if ($dataStr === '[DONE]' && $onMeta !== null) {
								$onMeta(['event' => 'done']);
							}
							continue;
						}

						$json = json_decode($dataStr, true);
						if (!is_array($json)) {
							continue;
						}

						$type = (string)($json['type'] ?? $eventName);

						if ($type === 'content_block_delta') {
							$deltaText = $json['delta']['text'] ?? null;
							if (is_string($deltaText) && $deltaText !== '') {
								$onData($deltaText);
							}
							continue;
						}

						if ($type === 'message_delta') {
							$stop = $json['delta']['stop_reason'] ?? null;
							if ($onMeta !== null && $stop !== null) {
								$onMeta([
									'event'       => 'meta',
									'stop_reason' => $stop,
									'full'        => $json
								]);
							}
							continue;
						}

						if ($type === 'message_stop') {
							if ($onMeta !== null) {
								$onMeta(['event' => 'done']);
							}
							continue;
						}

						if ($onMeta !== null) {
							$onMeta([
								'event' => 'meta',
								'type'  => $type,
								'full'  => $json
							]);
						}
					}
				}
			}
		}

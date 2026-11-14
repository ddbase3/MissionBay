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
use Base3\MissionBay\Resource\GeminiChatModelAgentResource;
use Base3\MissionBay\Api\IAgentConfigValueResolver;

final class GeminiChatModelAgentResourceTestDouble4 extends GeminiChatModelAgentResource {
			public function testFeedChunk(string $chunk, callable $onData, ?callable $onMeta): void {
				$lines = preg_split("/\r\n|\n|\r/", $chunk);

				foreach ($lines as $line) {
					$line = trim($line);
					if ($line === '') {
						continue;
					}

					$json = json_decode($line, true);
					if (!is_array($json)) {
						continue;
					}

					$candidate = $json['candidates'][0] ?? null;
					if (!$candidate) {
						continue;
					}

					$parts = $candidate['content']['parts'][0] ?? [];

					if (isset($parts['text'])) {
						$onData($parts['text']);
					}

					if (isset($parts['functionCall']) && $onMeta !== null) {
						$onMeta([
							'event' => 'toolcall',
							'tool_calls' => [
								[
									'id' => 'tool_test', // deterministic for test
									'type' => 'function',
									'function' => [
										'name' => $parts['functionCall']['name'] ?? '',
										'arguments' => json_encode($parts['functionCall']['args'] ?? []),
									]
								]
							]
						]);
					}

					if ($onMeta !== null && isset($candidate['finishReason'])) {
						$onMeta([
							'event' => 'meta',
							'finish_reason' => $candidate['finishReason'],
						]);
					}
				}
			}
		}

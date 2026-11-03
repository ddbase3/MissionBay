<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\GeminiChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

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

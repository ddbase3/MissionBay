<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\PerplexityChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

final class PerplexityChatModelAgentResourceTestDouble5 extends PerplexityChatModelAgentResource {
			public function simulateWrite(string $chunk, callable $onData, ?callable $onMeta): void {
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
						$delta = $choice['delta']['content'];
						$onData($delta);

						$ref = new \ReflectionClass(PerplexityChatModelAgentResource::class);
						$m = $ref->getMethod('extractToolCallFromText');
						$m->setAccessible(true);

						$toolCall = $m->invoke($this, $delta);
						if ($toolCall && $onMeta !== null) {
							$onMeta([
								'event' => 'toolcall',
								'tool_calls' => [$toolCall]
							]);
						}
					}

					if ($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
						$onMeta([
							'event' => 'meta',
							'finish_reason' => $choice['finish_reason']
						]);
					}
				}
			}
		}

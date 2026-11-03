<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\GenericChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

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

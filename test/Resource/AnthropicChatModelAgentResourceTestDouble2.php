<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\AnthropicChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;
use AssistantFoundation\Api\IAiChatModel;

final class AnthropicChatModelAgentResourceTestDouble2 extends AnthropicChatModelAgentResource {

			/**
			 * Runs the SAME parsing logic as the CURLOPT_WRITEFUNCTION in stream(),
			 * but without any curl dependency (stable unit test).
			 *
			 * @param string[] $chunks
			 */
			public function runParser(array $chunks, callable $onData, callable $onMeta = null): void {
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

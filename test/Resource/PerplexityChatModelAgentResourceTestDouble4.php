<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\PerplexityChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

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

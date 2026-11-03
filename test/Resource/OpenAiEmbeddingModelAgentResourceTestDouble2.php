<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Api\IClassMap;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\OpenAiEmbeddingModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

final class OpenAiEmbeddingModelAgentResourceTestDouble2 extends OpenAiEmbeddingModelAgentResource {
			public function embed(array $texts): array {
				if (empty($texts)) return [];

				// Emulate a valid OpenAI response -> mapping behavior must match production code:
				$data = [
					'data' => [
						['embedding' => [0.1, 0.2]],
						['embedding' => [0.3, 0.4]],
					],
				];

				return array_map(fn($item) => $item['embedding'] ?? [], $data['data']);
			}
		}

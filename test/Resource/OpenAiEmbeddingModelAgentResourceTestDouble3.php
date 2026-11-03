<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\Api\IClassMap;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\OpenAiEmbeddingModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

final class OpenAiEmbeddingModelAgentResourceTestDouble3 extends OpenAiEmbeddingModelAgentResource {
			public function embed(array $texts): array {
				if (empty($texts)) return [];

				$apikey = $this->getOptions()['apikey'] ?? null;
				if (!$apikey) {
					throw new \RuntimeException("Missing API key for OpenAI embedding model.");
				}

				$data = ['oops' => true]; // missing 'data' array

				if (!isset($data['data']) || !is_array($data['data'])) {
					throw new \RuntimeException("Malformed OpenAI embedding response.");
				}

				return array_map(fn($item) => $item['embedding'] ?? [], $data['data']);
			}
		}

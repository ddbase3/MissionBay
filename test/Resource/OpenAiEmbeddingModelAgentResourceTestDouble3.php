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
use Base3\CoreFoundation\Api\IClassMap;
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\Resource\OpenAiEmbeddingModelAgentResource;
use Base3\MissionBay\Api\IAgentConfigValueResolver;

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

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

namespace Base3\MissionBay\Test\VectorStore;

use Base3\AssistantFoundation\Api\IRetrievalCollectionDefinition;
use Base3\AssistantFoundation\Dto\RetrievalSearchRequest;
use Base3\MissionBay\Api\IRetrievalCollectionConfigRepository;
use Base3\MissionBay\Retrieval\DefaultRetrievalCollectionDefinition;
use Base3\MissionBay\VectorStore\AbstractQdrantVectorStoreService;
use PHPUnit\Framework\TestCase;

final class QdrantVectorStoreServiceTestDouble1 extends AbstractQdrantVectorStoreService {

			private array $responses;
			private array $calls;

			public function __construct(
				IRetrievalCollectionDefinition $definition,
				array $responses,
				array &$calls
			) {
				parent::__construct($definition);
				$this->responses = $responses;
				$this->calls = &$calls;
			}

			public static function getName(): string {
				return 'testqdrantvectorstoreservice';
			}

			protected function buildUrl(string $path): string {
				return rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');
			}

			protected function buildHeaders(): array {
				return ['Content-Type: application/json'];
			}

			protected function curlJson(string $method, string $url, ?array $body): array {
				$this->calls[] = [
					'method' => $method,
					'url' => $url,
					'body' => $body
				];

				return array_shift($this->responses) ?? [
					'http' => 500,
					'raw' => '',
					'error' => 'No test response configured.'
				];
			}
		}

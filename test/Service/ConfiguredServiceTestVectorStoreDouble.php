<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use MissionBay\VectorStore\AbstractQdrantVectorStoreService;

final class ConfiguredServiceTestVectorStoreDouble extends AbstractQdrantVectorStoreService {

	/** @param array<string,mixed> $result */
	public function __construct(IRetrievalCollectionDefinition $definition, private readonly array $result) {
		parent::__construct($definition);
	}

	public static function getName(): string {
		return 'configuredservicetestvectorstoredouble';
	}

	/** @param array<string,mixed> $config @return array<string,mixed> */
	public function test(array $config): array {
		return $this->result;
	}

	protected function buildUrl(string $path): string {
		return 'https://example.invalid' . $path;
	}

	protected function buildHeaders(): array {
		return [];
	}
}

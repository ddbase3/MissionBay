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

namespace Base3\MissionBay\Test\Service;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Service\EmbeddingOrchestratorConfigRepository;
use PHPUnit\Framework\TestCase;

final class EmbeddingOrchestratorConfigRepositoryTest extends TestCase {

	public function testMissingSettingsYieldThreeEmptyConfigKeys(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn([]);
		$this->assertSame(['embedding_preset' => '', 'vector_store_preset' => '', 'collection_key' => ''], (new EmbeddingOrchestratorConfigRepository($settings))->getConfig());
	}

	public function testKeysAreCanonicalizedOnRead(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn(['embedding_preset' => ' EMBEDDING / v1 ', 'vector_store_preset' => ' Qdrant_2 ', 'collection_key' => ' Articles:2026 ']);
		$this->assertSame([
			'embedding_preset' => 'embeddingv1',
			'vector_store_preset' => 'qdrant_2',
			'collection_key' => 'articles2026'
		], (new EmbeddingOrchestratorConfigRepository($settings))->getConfig());
	}

	public function testSaveWritesNormalizedConfigAndPersistsExactlyOnce(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects($this->once())->method('set')->with('embedding-orchestrator', 'default', [
			'embedding_preset' => 'openai_v1',
			'vector_store_preset' => 'qdrant.2',
			'collection_key' => 'articles-2026'
		]);
		$settings->expects($this->once())->method('save');
		(new EmbeddingOrchestratorConfigRepository($settings))->saveConfig(' OpenAI_V1 ', ' Qdrant.2 ', ' Articles-2026 ');
	}

	public function testBlankNormalizedKeysAreRejectedWithoutPersistentWrites(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects($this->never())->method('set');
		$settings->expects($this->never())->method('save');
		$repository = new EmbeddingOrchestratorConfigRepository($settings);
		foreach ([['?', 'valid', 'valid'], ['valid', ' / ', 'valid'], ['valid', 'valid', '???']] as $values) {
			try {
				$repository->saveConfig(...$values);
				$this->fail('Invalid normalized configuration should not be persisted.');
			} catch (\InvalidArgumentException $e) {
				$this->assertStringContainsString('required', $e->getMessage());
			}
		}
	}
}

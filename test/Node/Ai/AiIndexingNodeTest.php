<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Ai;

use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use AssistantFoundation\Api\IRetrievalIndex;
use AssistantFoundation\Dto\RetrievalIndexItem;
use Base3\Api\IClassMap;
use MissionBay\Api\IAgentChunker;
use MissionBay\Api\IAgentContentExtractor;
use MissionBay\Api\IAgentContentParser;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;
use MissionBay\Node\Ai\AiIndexingNode;
use MissionBay\Retrieval\PhoneticTextMaterializer;
use PHPUnit\Framework\TestCase;

final class AiIndexingNodeTest extends TestCase {

	public function testNodeDefinitionsDescribeTheIndexingContract(): void {
		$node = $this->node();
		$this->assertSame('aiindexingnode', $node::getName());
		$this->assertStringContainsString('embedding', $node->getDescription());
		$this->assertSame(['mode', 'on_empty_chunks', 'debug', 'debug_preview_len'], array_map(static fn($port): string => $port->name, $node->getInputDefinitions()));
		$this->assertSame(['stats', 'error'], array_map(static fn($port): string => $port->name, $node->getOutputDefinitions()));
		$docks = $node->getDockDefinitions();
		$this->assertSame(['extractor', 'parser', 'chunker', 'embedder', 'vectordb', 'logger'], array_map(static fn($dock): string => $dock->name, $docks));
		$this->assertFalse($docks[5]->required);
		$this->assertTrue($docks[0]->required);
	}

	public function testMissingEmbedderAndIndexDoNotRunExtraction(): void {
		$extractor = $this->createMock(IAgentContentExtractor::class);
		$extractor->expects($this->never())->method('extract');
		$this->assertSame(['error' => 'Missing embedder or vector store.'], $this->node()->execute([], ['extractor' => [$extractor]], new AgentContext()));
	}

	public function testMissingOnlyIndexIsReportedBeforeExtraction(): void {
		$this->assertSame(['error' => 'Missing embedder or vector store.'], $this->node()->execute([], ['embedder' => [$this->model()]], new AgentContext()));
	}

	public function testUnsupportedModeDoesNotExtractContent(): void {
		$extractor = $this->createMock(IAgentContentExtractor::class);
		$extractor->expects($this->never())->method('extract');
		$result = $this->node()->execute(['mode' => 'overwrite'], $this->resources([$extractor]), new AgentContext());
		$this->assertSame('Unsupported mode: overwrite', $result['error']);
	}

	public function testUnsupportedEmptyChunkPolicyDoesNotExtractContent(): void {
		$extractor = $this->createMock(IAgentContentExtractor::class);
		$extractor->expects($this->never())->method('extract');
		$result = $this->node()->execute(['on_empty_chunks' => 'accept'], $this->resources([$extractor]), new AgentContext());
		$this->assertSame('Unsupported on_empty_chunks: accept', $result['error']);
	}

	public function testEmptyExtractionReturnsCompleteZeroCounters(): void {
		$result = $this->node()->execute([], $this->resources([$this->extractor([])]), new AgentContext());
		$this->assertSame('skip', $result['stats']['mode']);
		$this->assertSame('skip', $result['stats']['on_empty_chunks']);
		$this->assertSame(1, $result['stats']['num_extractors']);
		foreach (['num_items', 'num_items_done', 'num_items_failed', 'num_skipped', 'num_deleted', 'num_parsed', 'num_chunks', 'num_vectors', 'num_store_upserts', 'num_store_errors', 'num_embed_errors', 'num_parser_errors', 'num_chunker_errors', 'num_extractor_errors', 'num_ack_errors', 'num_fail_errors'] as $key) {
			$this->assertSame(0, $result['stats'][$key], $key);
		}
	}

	public function testExtractionExceptionsAreCountedWithoutAbortingOtherExtractors(): void {
		$failing = $this->createStub(IAgentContentExtractor::class);
		$failing->method('extract')->willThrowException(new \RuntimeException('source unavailable'));
		$result = $this->node()->execute([], $this->resources([$failing, $this->extractor([])]), new AgentContext());
		$this->assertSame(1, $result['stats']['num_extractor_errors']);
		$this->assertSame(2, $result['stats']['num_extractors']);
		$this->assertSame(0, $result['stats']['num_items_failed']);
	}

	public function testInvalidExtractorItemsAreNotHandedToThePipeline(): void {
		$result = $this->node()->execute([], $this->resources([$this->extractor([null, 'invalid', ['not an item']])]), new AgentContext());
		$this->assertSame(0, $result['stats']['num_items']);
	}

	public function testDeleteUsesCollectionOwnedIdentityAndAcknowledgesEvenWhenNothingWasDeleted(): void {
		$ack = [];
		$extractor = $this->extractor([$this->item('delete', ['document_id' => 'doc-7'])], $ack);
		$store = $this->createStub(IRetrievalIndex::class);
		$filters = [];
		$store->method('deleteByFilter')->willReturnCallback(static function(string $collection, array $filter) use (&$filters): int {
			$filters[] = [$collection, $filter];
			return 0;
		});
		$result = $this->node()->execute([], $this->resources([$extractor], null, null, null, $store), new AgentContext());
		$this->assertSame([['docs', ['document_id' => 'doc-7']]], $filters);
		$this->assertSame(1, $result['stats']['num_items_done']);
		$this->assertSame(0, $result['stats']['num_deleted']);
		$this->assertSame('delete', $ack[0]['action']);
		$this->assertSame(0, $ack[0]['deleted']);
	}

	public function testDeleteCountsAllDeletedChunks(): void {
		$ack = [];
		$store = $this->createStub(IRetrievalIndex::class);
		$store->method('deleteByFilter')->willReturn(5);
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item('delete')], $ack)], null, null, null, $store), new AgentContext());
		$this->assertSame(5, $result['stats']['num_deleted']);
		$this->assertSame(5, $ack[0]['deleted']);
	}

	public function testMissingCollectionFailsItemWithoutTouchingIndex(): void {
		$errors = [];
		$store = $this->createMock(IRetrievalIndex::class);
		$store->expects($this->never())->method('deleteByFilter');
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item('delete', ['document_id' => 'a'], 'hash', '')], $unused, $errors)], null, null, null, $store), new AgentContext());
		$this->assertSame(1, $result['stats']['num_items_failed']);
		$this->assertStringContainsString('Missing required item.collectionKey', $errors[0]);
	}

	public function testDeleteRejectsCollectionWithoutLifecycleField(): void {
		$errors = [];
		$index = $this->createMock(IRetrievalIndex::class);
		$index->expects($this->never())->method('deleteByFilter');
		$result = $this->node([])->execute([], $this->resources([$this->extractor([$this->item('delete')], $unused, $errors)], null, null, null, $index), new AgentContext());
		$this->assertSame(1, $result['stats']['num_items_failed']);
		$this->assertStringContainsString('does not define a lifecycle group field', $errors[0]);
	}

	public function testDeleteRejectsMissingNonScalarAndEmptyLifecycleValues(): void {
		foreach ([[], ['document_id' => []], ['document_id' => new \stdClass()], ['document_id' => '  ']] as $metadata) {
			$errors = [];
			$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item('delete', $metadata)], $unused, $errors)]), new AgentContext());
			$this->assertSame(1, $result['stats']['num_items_failed']);
			$this->assertCount(1, $errors);
			$this->assertSame(0, $result['stats']['num_items_done']);
		}
	}

	public function testLifecycleIdentityTrimsStringsAndPreservesScalarTypes(): void {
		foreach ([['document_id' => '  alpha  ', 'expected' => 'alpha'], ['document_id' => 0, 'expected' => 0], ['document_id' => false, 'expected' => false], ['document_id' => 1.5, 'expected' => 1.5]] as $case) {
			$filters = [];
			$store = $this->createStub(IRetrievalIndex::class);
			$store->method('deleteByFilter')->willReturnCallback(static function(string $collection, array $filter) use (&$filters): int {
				$filters[] = $filter;
				return 1;
			});
			$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item('delete', ['document_id' => $case['document_id']])])], null, null, null, $store), new AgentContext());
			$this->assertSame([['document_id' => $case['expected']]], $filters);
			$this->assertSame(1, $result['stats']['num_items_done']);
		}
	}

	public function testDeleteFailureDoesNotAcknowledgeAndRequestsRetry(): void {
		$acks = [];
		$fails = [];
		$store = $this->createStub(IRetrievalIndex::class);
		$store->method('deleteByFilter')->willThrowException(new \RuntimeException('index read-only'));
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item('delete')], $acks, $fails)], null, null, null, $store), new AgentContext());
		$this->assertSame([], $acks);
		$this->assertSame(1, $result['stats']['num_items_failed']);
		$this->assertSame(0, $result['stats']['num_items_done']);
		$this->assertStringContainsString('index read-only', $fails[0]);
	}

	public function testSkipModeRecognizesAnExistingHashWithoutParsing(): void {
		$ack = [];
		$store = $this->createStub(IRetrievalIndex::class);
		$store->method('existsByHash')->willReturn(true);
		$parser = $this->createMock(IAgentContentParser::class);
		$parser->method('getPriority')->willReturn(1);
		$parser->expects($this->never())->method('parse');
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item('upsert')], $ack)], [$parser], null, null, $store), new AgentContext());
		$this->assertSame(1, $result['stats']['num_skipped']);
		$this->assertSame(1, $result['stats']['num_items_done']);
		$this->assertSame('skip', $ack[0]['action']);
	}

	public function testEmptyHashSkipsDuplicateLookupAndContinuesToParsing(): void {
		$index = $this->createMock(IRetrievalIndex::class);
		$index->expects($this->never())->method('existsByHash');
		$errors = [];
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item('upsert', ['document_id' => 'a'], '')], $unused, $errors)], [], [], null, $index), new AgentContext());
		$this->assertSame(1, $result['stats']['num_items_failed']);
		$this->assertStringContainsString('no-parse', $errors[0]);
	}

	public function testAppendNeverChecksHashOrDeletesPreviouslyIndexedChunks(): void {
		$store = $this->createMock(IRetrievalIndex::class);
		$store->expects($this->never())->method('existsByHash');
		$store->expects($this->never())->method('deleteByFilter');
		$result = $this->node()->execute(['mode' => 'append'], $this->resources([$this->extractor([$this->item()])], [], [], null, $store), new AgentContext());
		$this->assertSame('append', $result['stats']['mode']);
		$this->assertSame(1, $result['stats']['num_items_failed']);
	}

	public function testReplaceDeletesByLifecycleIdentityBeforeParsing(): void {
		$store = $this->createStub(IRetrievalIndex::class);
		$filters = [];
		$store->method('deleteByFilter')->willReturnCallback(static function(string $key, array $filter) use (&$filters): int {
			$filters[] = [$key, $filter];
			return 2;
		});
		$result = $this->node()->execute(['mode' => 'replace'], $this->resources([$this->extractor([$this->item()])], [], [], null, $store), new AgentContext());
		$this->assertSame([['docs', ['document_id' => 'doc-1']]], $filters);
		$this->assertSame(2, $result['stats']['num_deleted']);
		$this->assertSame(1, $result['stats']['num_items_failed']);
	}

	public function testReplaceWithoutIdentityMustNotDeleteAnything(): void {
		$store = $this->createMock(IRetrievalIndex::class);
		$store->expects($this->never())->method('deleteByFilter');
		$errors = [];
		$result = $this->node()->execute(['mode' => 'replace'], $this->resources([$this->extractor([$this->item('upsert', [])], $unused, $errors)], [], [], null, $store), new AgentContext());
		$this->assertSame(1, $result['stats']['num_items_failed']);
		$this->assertStringContainsString('Missing scalar item metadata', $errors[0]);
	}

	public function testNoParserFailsUpsertWithoutAnAcknowledgement(): void {
		$acks = [];
		$fails = [];
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item()], $acks, $fails)]), new AgentContext());
		$this->assertSame(1, $result['stats']['num_items_failed']);
		$this->assertSame(0, $result['stats']['num_parsed']);
		$this->assertSame([], $acks);
		$this->assertStringContainsString('status=no-parse', $fails[0]);
	}

	public function testParserUnsupportedThenLaterParserProcessesTheItem(): void {
		$first = $this->parser(null, false, 1);
		$second = $this->parser(new AgentParsedContent('Parsed'), true, 9);
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item()])], [$second, $first]), new AgentContext());
		$this->assertSame(1, $result['stats']['num_parsed']);
		$this->assertSame(1, $result['stats']['num_skipped']);
		$this->assertSame(1, $result['stats']['num_items_done']);
	}

	public function testParserSupportsExceptionCausesFailureAndIncrementsParserCounter(): void {
		$parser = $this->createStub(IAgentContentParser::class);
		$parser->method('getPriority')->willReturn(1);
		$parser->method('supports')->willThrowException(new \RuntimeException('cannot inspect content'));
		$errors = [];
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item()], $unused, $errors)], [$parser]), new AgentContext());
		$this->assertSame(1, $result['stats']['num_parser_errors']);
		$this->assertSame(1, $result['stats']['num_items_failed']);
		$this->assertStringContainsString('Parse supports ERROR', $errors[0]);
	}

	public function testParserParseExceptionDoesNotReachChunkerOrStorage(): void {
		$parser = $this->createStub(IAgentContentParser::class);
		$parser->method('getPriority')->willReturn(1);
		$parser->method('supports')->willReturn(true);
		$parser->method('parse')->willThrowException(new \RuntimeException('invalid source'));
		$errors = [];
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item()], $unused, $errors)], [$parser]), new AgentContext());
		$this->assertSame(1, $result['stats']['num_parser_errors']);
		$this->assertSame(0, $result['stats']['num_chunks']);
		$this->assertStringContainsString('invalid source', $errors[0]);
	}

	public function testUnmatchedChunkerSkipsEmptyContentByDefault(): void {
		$acks = [];
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item()], $acks)], [$this->parser()], [$this->chunker([], false)]), new AgentContext());
		$this->assertSame(1, $result['stats']['num_skipped']);
		$this->assertSame(1, $result['stats']['num_items_done']);
		$this->assertSame('no-chunks', $acks[0]['status']);
	}

	public function testEmptyChunksCanBeConfiguredAsAnItemFailure(): void {
		$acks = [];
		$errors = [];
		$result = $this->node()->execute(['on_empty_chunks' => 'fail'], $this->resources([$this->extractor([$this->item()], $acks, $errors)], [$this->parser()], [$this->chunker([])]), new AgentContext());
		$this->assertSame(0, $result['stats']['num_skipped']);
		$this->assertSame(1, $result['stats']['num_items_failed']);
		$this->assertSame([], $acks);
		$this->assertStringContainsString('status=no-chunks', $errors[0]);
	}

	public function testChunkerSupportsFailureIsCountedAsChunkerError(): void {
		$chunker = $this->createStub(IAgentChunker::class);
		$chunker->method('getPriority')->willReturn(1);
		$chunker->method('supports')->willThrowException(new \RuntimeException('unsupported structure'));
		$errors = [];
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item()], $unused, $errors)], [$this->parser()], [$chunker]), new AgentContext());
		$this->assertSame(1, $result['stats']['num_chunker_errors']);
		$this->assertStringContainsString('Chunk supports ERROR', $errors[0]);
	}

	public function testChunkerExceptionIsCountedAndItemFails(): void {
		$chunker = $this->createStub(IAgentChunker::class);
		$chunker->method('getPriority')->willReturn(1);
		$chunker->method('supports')->willReturn(true);
		$chunker->method('chunk')->willThrowException(new \RuntimeException('chunking failed'));
		$errors = [];
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item()], $unused, $errors)], [$this->parser()], [$chunker]), new AgentContext());
		$this->assertSame(1, $result['stats']['num_chunker_errors']);
		$this->assertStringContainsString('chunking failed', $errors[0]);
	}

	public function testWhitespaceOnlyChunksAreNotSentToEmbedder(): void {
		$embedder = $this->createMock(IAiEmbeddingModel::class);
		$embedder->expects($this->never())->method('embed');
		$result = $this->node()->execute(['on_empty_chunks' => 'fail'], $this->resources([$this->extractor([$this->item()])], [$this->parser()], [$this->chunker([['text' => '  ']])], $embedder), new AgentContext());
		$this->assertSame(1, $result['stats']['num_items_failed']);
		$this->assertSame(0, $result['stats']['num_vectors']);
	}

	public function testHappyPathMergesMetadataAndStoresOneVectorPerValidChunk(): void {
		$stored = [];
		$acks = [];
		$store = $this->createStub(IRetrievalIndex::class);
		$store->method('upsert')->willReturnCallback(static function(RetrievalIndexItem $item) use (&$stored): void {
			$stored[] = $item;
		});
		$parser = $this->parser(new AgentParsedContent('parsed', ['origin' => 'parsed', 'language' => 'de']));
		$chunks = $this->chunker([
			['text' => '  First chunk  ', 'meta' => ['origin' => 'chunk']],
			['text' => '   '],
			['text' => 'Second chunk', 'meta' => ['order' => 2]]
		]);
		$model = $this->model(static function(array $texts): array {
			return array_map(static fn(string $text): array => [strlen($text) / 10, 0.25], $texts);
		});
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item('upsert', ['document_id' => 'doc-1', 'origin' => 'item'])], $acks)], [$parser], [$chunks], $model, $store), new AgentContext());
		$this->assertSame(1, $result['stats']['num_items_done']);
		$this->assertSame(0, $result['stats']['num_items_failed']);
		$this->assertSame(3, $result['stats']['num_chunks']);
		$this->assertSame(2, $result['stats']['num_vectors']);
		$this->assertSame(2, $result['stats']['num_store_upserts']);
		$this->assertCount(2, $stored);
		$this->assertSame('First chunk', $stored[0]->text);
		$this->assertSame([1.1, 0.25], $stored[0]->denseVector);
		$this->assertSame('chunk', $stored[0]->metadata['origin']);
		$this->assertSame('de', $stored[0]->metadata['language']);
		$this->assertSame(2, $stored[0]->metadata['num_chunks']);
		$this->assertSame(0, $stored[0]->chunkIndex);
		$this->assertSame(1, $stored[1]->chunkIndex);
		$this->assertSame('docs', $stored[1]->collectionKey);
		$this->assertSame('sha-1', $stored[1]->hash);
		$this->assertSame(2, $acks[0]['stored']);
	}

	public function testEmptyActionIsTreatedAsUpsert(): void {
		$acks = [];
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item('', ['document_id' => 'doc-1'])], $acks)], [$this->parser()], [$this->chunker([['text' => 'hello']])]), new AgentContext());
		$this->assertSame('upsert', $acks[0]['action']);
		$this->assertSame(1, $result['stats']['num_items_done']);
	}

	public function testEmbeddingExceptionPreventsAnyIndexWrite(): void {
		$store = $this->createMock(IRetrievalIndex::class);
		$store->expects($this->never())->method('upsert');
		$model = $this->createStub(IAiEmbeddingModel::class);
		$model->method('getOptions')->willReturn([]);
		$model->method('embed')->willThrowException(new \RuntimeException('provider timeout'));
		$errors = [];
		$result = $this->node()->execute(['mode' => 'append'], $this->resources([$this->extractor([$this->item()], $unused, $errors)], [$this->parser()], [$this->chunker([['text' => 'hello']])], $model, $store), new AgentContext());
		$this->assertSame(1, $result['stats']['num_embed_errors']);
		$this->assertSame(0, $result['stats']['num_store_upserts']);
		$this->assertStringContainsString('provider timeout', $errors[0]);
	}

	public function testEmbeddingVectorCountMismatchFailsWithoutWrites(): void {
		$model = $this->model(static fn(array $texts): array => []);
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item()])], [$this->parser()], [$this->chunker([['text' => 'one'], ['text' => 'two']])], $model), new AgentContext());
		$this->assertSame(1, $result['stats']['num_embed_errors']);
		$this->assertSame(0, $result['stats']['num_vectors']);
		$this->assertSame(0, $result['stats']['num_store_upserts']);
	}

	public function testEmbeddingRejectsEmptyAndNonArrayVectors(): void {
		foreach ([[[]], ['invalid']] as $vectors) {
			$model = $this->model(static fn(array $texts): array => $vectors);
			$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item()])], [$this->parser()], [$this->chunker([['text' => 'text']])], $model), new AgentContext());
			$this->assertSame(1, $result['stats']['num_embed_errors']);
			$this->assertSame(1, $result['stats']['num_items_failed']);
		}
	}

	public function testStoreFailureReportsFailedItemAndUpsertError(): void {
		$store = $this->createStub(IRetrievalIndex::class);
		$store->method('upsert')->willThrowException(new \RuntimeException('storage unavailable'));
		$errors = [];
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item()], $unused, $errors)], [$this->parser()], [$this->chunker([['text' => 'document']])], null, $store), new AgentContext());
		$this->assertSame(1, $result['stats']['num_store_errors']);
		$this->assertSame(0, $result['stats']['num_store_upserts']);
		$this->assertStringContainsString('Retrieval index upsert failed', $errors[0]);
	}

	public function testStoreFailureAfterFirstChunkDoesNotAcknowledgePartialUpsert(): void {
		$store = $this->createStub(IRetrievalIndex::class);
		$writes = 0;
		$store->method('upsert')->willReturnCallback(static function(RetrievalIndexItem $item) use (&$writes): void {
			$writes++;
			if ($writes === 2) {
				throw new \RuntimeException('second write rejected');
			}
		});
		$acks = [];
		$errors = [];
		$result = $this->node()->execute([], $this->resources([$this->extractor([$this->item()], $acks, $errors)], [$this->parser()], [$this->chunker([['text' => 'one'], ['text' => 'two']])], null, $store), new AgentContext());
		$this->assertSame(2, $writes);
		$this->assertSame(1, $result['stats']['num_store_upserts']);
		$this->assertSame(1, $result['stats']['num_store_errors']);
		$this->assertSame([], $acks);
		$this->assertCount(1, $errors);
	}

	public function testAckErrorsAreCountedWithoutMarkingItemAsFailed(): void {
		$extractor = $this->createStub(IAgentContentExtractor::class);
		$extractor->method('extract')->willReturn([$this->item('delete')]);
		$extractor->method('ack')->willThrowException(new \RuntimeException('ack unavailable'));
		$result = $this->node()->execute([], $this->resources([$extractor]), new AgentContext());
		$this->assertSame(1, $result['stats']['num_ack_errors']);
		$this->assertSame(1, $result['stats']['num_items_done']);
		$this->assertSame(0, $result['stats']['num_items_failed']);
	}

	public function testFailCallbackErrorsAreCountedWithoutStoppingLaterItems(): void {
		$extractor = $this->createStub(IAgentContentExtractor::class);
		$extractor->method('extract')->willReturn([$this->item('delete', []), $this->item('delete')]);
		$extractor->method('fail')->willThrowException(new \RuntimeException('fail unavailable'));
		$result = $this->node()->execute([], $this->resources([$extractor]), new AgentContext());
		$this->assertSame(1, $result['stats']['num_fail_errors']);
		$this->assertSame(1, $result['stats']['num_items_done']);
		$this->assertSame(1, $result['stats']['num_items_failed']);
	}

	public function testEachExtractorOwnsAcknowledgementsForItsItems(): void {
		$firstAck = [];
		$secondAck = [];
		$first = $this->extractor([$this->item('delete', ['document_id' => 'first'])], $firstAck);
		$second = $this->extractor([$this->item('delete', ['document_id' => 'second'])], $secondAck);
		$result = $this->node()->execute([], $this->resources([$first, $second]), new AgentContext());
		$this->assertSame(2, $result['stats']['num_items_done']);
		$this->assertCount(1, $firstAck);
		$this->assertCount(1, $secondAck);
	}

	private function node(array $contextSchema = ['group_field' => 'document_id']): AiIndexingNode {
		$collection = $this->createStub(IRetrievalCollectionDefinition::class);
		$collection->method('getContextSchema')->willReturn($contextSchema);
		$collection->method('getPhoneticEncoderNames')->willReturn([]);
		$classMap = $this->createStub(IClassMap::class);
		return new AiIndexingNode(new PhoneticTextMaterializer($classMap, $collection), $collection, 'index-node');
	}

	private function item(string $action = 'upsert', array $metadata = ['document_id' => 'doc-1'], string $hash = 'sha-1', string $collection = 'docs'): AgentContentItem {
		return new AgentContentItem($action, $collection, 'item-1', $hash, 'text/plain', 'content', false, 7, $metadata);
	}

	private function extractor(array $items, ?array &$acks = null, ?array &$failures = null): IAgentContentExtractor {
		$extractor = $this->createStub(IAgentContentExtractor::class);
		$extractor->method('extract')->willReturn($items);
		$extractor->method('ack')->willReturnCallback(static function(AgentContentItem $item, array $metadata) use (&$acks): void {
			$acks[] = $metadata;
		});
		$extractor->method('fail')->willReturnCallback(static function(AgentContentItem $item, string $error, bool $retry) use (&$failures): void {
			if ($retry) {
				$failures[] = $error;
			}
		});
		return $extractor;
	}

	private function parser(?AgentParsedContent $parsed = null, bool $supports = true, int $priority = 1): IAgentContentParser {
		$parser = $this->createStub(IAgentContentParser::class);
		$parser->method('getPriority')->willReturn($priority);
		$parser->method('supports')->willReturn($supports);
		$parser->method('parse')->willReturn($parsed ?? new AgentParsedContent('Parsed document'));
		return $parser;
	}

	private function chunker(array $chunks = [], bool $supports = true, int $priority = 1): IAgentChunker {
		$chunker = $this->createStub(IAgentChunker::class);
		$chunker->method('getPriority')->willReturn($priority);
		$chunker->method('supports')->willReturn($supports);
		$chunker->method('chunk')->willReturn($chunks);
		return $chunker;
	}

	private function model(?callable $embed = null): IAiEmbeddingModel {
		$model = $this->createStub(IAiEmbeddingModel::class);
		$model->method('getOptions')->willReturn(['model' => 'demo', 'dimensions' => 2]);
		$model->method('embed')->willReturnCallback($embed ?? static fn(array $texts): array => array_map(static fn(string $text): array => [0.5, 0.25], $texts));
		return $model;
	}

	/** @param IAgentContentExtractor[] $extractors */
	private function resources(array $extractors, ?array $parsers = null, ?array $chunkers = null, ?IAiEmbeddingModel $model = null, ?IRetrievalIndex $index = null): array {
		return [
			'extractor' => $extractors,
			'parser' => $parsers ?? [],
			'chunker' => $chunkers ?? [],
			'embedder' => [$model ?? $this->model()],
			'vectordb' => [$index ?? $this->createStub(IRetrievalIndex::class)]
		];
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IVectorSearch;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\RagSearchAgentTool;
use PHPUnit\Framework\TestCase;

final class RagSearchAgentToolExecutionTest extends TestCase {

	public function testDefinitionExplicitlyDeclaresReadOnlyLookup(): void {
		$definition = $this->tool()->getToolDefinitions()[0];
		$this->assertSame('rag_search', $definition['function']['name']);
		$this->assertSame(['query'], $definition['function']['parameters']['required']);
		$this->assertTrue($definition['readOnlyHint']);
		$this->assertFalse($definition['mutation']);
		$this->assertFalse($definition['requiresApproval']);
	}

	public function testUnexpectedFunctionNameIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->tool()->callTool('delete_documents', ['query' => 'test'], $this->context());
	}

	public function testMissingEmbeddingDockReturnsStructuredError(): void {
		$this->assertSame(['error' => 'Missing embedding model resource'],
			$this->tool()->callTool('rag_search', ['query' => 'test'], $this->context()));
	}

	public function testMissingVectorStoreDockReturnsStructuredError(): void {
		$tool = $this->tool();
		$tool->init(['embedding' => [$this->embedding()]], $this->context());
		$this->assertSame(['error' => 'Missing vector search resource'],
			$tool->callTool('rag_search', ['query' => 'test'], $this->context()));
	}

	public function testEmptyQueryReturnsErrorBeforeCallingEmbeddingModel(): void {
		$model = $this->createMock(IAiEmbeddingModel::class);
		$model->expects($this->never())->method('embed');
		$tool = $this->tool();
		$tool->init(['embedding' => [$model], 'vectordb' => [$this->vectorSearch()]], $this->context());
		$this->assertSame(['error' => 'Missing required parameter: query'],
			$tool->callTool('rag_search', ['query' => ''], $this->context()));
	}

	public function testNoQueryArgumentReturnsError(): void {
		$tool = $this->initializedTool();
		$this->assertSame(['error' => 'Missing required parameter: query'],
			$tool->callTool('rag_search', [], $this->context()));
	}

	public function testEmbeddingExceptionDoesNotStartVectorSearch(): void {
		$embedding = $this->createStub(IAiEmbeddingModel::class);
		$embedding->method('embed')->willThrowException(new \RuntimeException('provider unavailable'));
		$vector = $this->createMock(IVectorSearch::class);
		$vector->expects($this->never())->method('search');
		$tool = $this->tool();
		$tool->init(['embedding' => [$embedding], 'vectordb' => [$vector]], $this->context());
		$this->assertSame(['error' => 'Embedding generation failed: provider unavailable'],
			$tool->callTool('rag_search', ['query' => 'question'], $this->context()));
	}

	public function testEmptyEmbeddingsCannotBeUsedForVectorSearch(): void {
		$embedding = $this->createStub(IAiEmbeddingModel::class);
		$embedding->method('embed')->willReturn([]);
		$vector = $this->createMock(IVectorSearch::class);
		$vector->expects($this->never())->method('search');
		$tool = $this->tool();
		$tool->init(['embedding' => [$embedding], 'vectordb' => [$vector]], $this->context());
		$this->assertSame(['error' => 'No embedding generated for query'],
			$tool->callTool('rag_search', ['query' => 'question'], $this->context()));
	}

	public function testEmptyFirstVectorIsRejected(): void {
		$embedding = $this->createStub(IAiEmbeddingModel::class);
		$embedding->method('embed')->willReturn([[]]);
		$tool = $this->tool();
		$tool->init(['embedding' => [$embedding], 'vectordb' => [$this->vectorSearch()]], $this->context());
		$this->assertSame(['error' => 'No embedding generated for query'],
			$tool->callTool('rag_search', ['query' => 'question'], $this->context()));
	}

	public function testVectorSearchFailureReturnsStructuredError(): void {
		$vector = $this->createStub(IVectorSearch::class);
		$vector->method('search')->willThrowException(new \RuntimeException('index unavailable'));
		$tool = $this->tool();
		$tool->init(['embedding' => [$this->embedding()], 'vectordb' => [$vector]], $this->context());
		$this->assertSame(['error' => 'Vector search failed: index unavailable'],
			$tool->callTool('rag_search', ['query' => 'question'], $this->context()));
	}

	public function testSearchReceivesConfiguredLimitAndThreshold(): void {
		$embedding = $this->createMock(IAiEmbeddingModel::class);
		$embedding->expects($this->once())->method('embed')->with(['question'])->willReturn([[0.1, 0.3]]);
		$vector = $this->createMock(IVectorSearch::class);
		$hits = [['id' => 'doc-1', 'score' => 0.93]];
		$vector->expects($this->once())->method('search')->with([0.1, 0.3], 7, 0.4)->willReturn($hits);
		$tool = $this->tool();
		$tool->setConfig(['limit' => '7', 'minscore' => '0.4']);
		$tool->init(['embedding' => [$embedding], 'vectordb' => [$vector]], $this->context());
		$this->assertSame(['query' => 'question', 'results' => $hits],
			$tool->callTool('rag_search', ['query' => 'question'], $this->context()));
	}

	public function testEmptySearchResultsAreValid(): void {
		$tool = $this->initializedTool();
		$this->assertSame(['query' => 'nothing', 'results' => []],
			$tool->callTool('rag_search', ['query' => 'nothing'], $this->context()));
	}

	public function testLoggerReceivesResourceScopedDiagnostics(): void {
		$messages = [];
		$logger = $this->createStub(ILogger::class);
		$logger->method('log')->willReturnCallback(static function(string $scope, string $message, ?int $timestamp = null) use (&$messages): bool {
			$messages[] = [$scope, $message];
			return true;
		});
		$tool = $this->tool();
		$tool->init(['embedding' => [$this->embedding()], 'vectordb' => [$this->vectorSearch()], 'logger' => [$logger]], $this->context());
		$tool->callTool('rag_search', ['query' => 'question'], $this->context());
		$this->assertNotEmpty($messages);
		$this->assertSame('RagSearchAgentTool', $messages[0][0]);
		$this->assertStringContainsString('ragsearchagenttool|rag-unit', $messages[0][1]);
	}

	private function context(): IAgentContext {
		return $this->createStub(IAgentContext::class);
	}

	private function embedding(): IAiEmbeddingModel {
		$model = $this->createStub(IAiEmbeddingModel::class);
		$model->method('embed')->willReturn([[0.1, 0.2]]);
		return $model;
	}

	private function vectorSearch(): IVectorSearch {
		$vector = $this->createStub(IVectorSearch::class);
		$vector->method('search')->willReturn([]);
		return $vector;
	}

	private function tool(): RagSearchAgentTool {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(static fn(mixed $value): mixed => $value);
		return new RagSearchAgentTool($resolver, 'rag-unit');
	}

	private function initializedTool(): RagSearchAgentTool {
		$tool = $this->tool();
		$tool->init(['embedding' => [$this->embedding()], 'vectordb' => [$this->vectorSearch()]], $this->context());
		return $tool;
	}
}

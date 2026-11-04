<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Ai;

use AssistantFoundation\Api\IAiEmbeddingModel;
use MissionBay\Context\AgentContext;
use MissionBay\Node\Ai\AiEmbedTextNode;
use PHPUnit\Framework\TestCase;

final class AiEmbedTextNodeTest extends TestCase {

	public function testMissingModelResourceIsReportedBeforeInputProcessing(): void {
		$node = new AiEmbedTextNode('embed');
		$this->assertSame(['error' => '[ aiembedtextnode | embed ] Missing embedding model resource.'], $node->execute(['texts' => 'hello'], [], new AgentContext()));
	}

	public function testScalarInputIsRejectedWithoutCallingModel(): void {
		$model = $this->createMock(IAiEmbeddingModel::class);
		$model->expects($this->never())->method('embed');
		$result = (new AiEmbedTextNode())->execute(['texts' => 21], ['embeddingmodel' => [$model]], new AgentContext());
		$this->assertStringContainsString('must be a string or array', $result['error']);
	}

	public function testSingleStringIsWrappedIntoOneInput(): void {
		$model = $this->createStub(IAiEmbeddingModel::class);
		$model->method('embed')->willReturnCallback(function(array $texts): array {
			$this->assertSame(['hello'], $texts);
			return [[0.25, 0.75]];
		});
		$this->assertSame(['embeddings' => [[0.25, 0.75]]], (new AiEmbedTextNode())->execute(['texts' => 'hello'], ['embeddingmodel' => [$model]], new AgentContext()));
	}

	public function testArrayIsForwardedInOriginalOrder(): void {
		$model = $this->createStub(IAiEmbeddingModel::class);
		$model->method('embed')->willReturnCallback(function(array $texts): array {
			$this->assertSame(['first', 'second'], $texts);
			return [[1.0], [2.0]];
		});
		$this->assertSame(['embeddings' => [[1.0], [2.0]]], (new AiEmbedTextNode())->execute(['texts' => ['first', 'second']], ['embeddingmodel' => [$model]], new AgentContext()));
	}

	public function testEmptyBatchIsPassedThroughWithoutFabricatingVectors(): void {
		$model = $this->createStub(IAiEmbeddingModel::class);
		$model->method('embed')->willReturn([]);
		$this->assertSame(['embeddings' => []], (new AiEmbedTextNode())->execute(['texts' => []], ['embeddingmodel' => [$model]], new AgentContext()));
	}

	public function testModelFailureReturnsContextualError(): void {
		$model = $this->createStub(IAiEmbeddingModel::class);
		$model->method('embed')->willThrowException(new \RuntimeException('model offline'));
		$result = (new AiEmbedTextNode('embedding'))->execute(['texts' => ['hi']], ['embeddingmodel' => [$model]], new AgentContext());
		$this->assertSame(['error' => '[ aiembedtextnode | embedding ] Embedding failed: model offline'], $result);
	}
}

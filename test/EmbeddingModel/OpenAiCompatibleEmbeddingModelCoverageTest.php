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

namespace Base3\MissionBay\Test\EmbeddingModel;

use Base3\AssistantFoundation\Api\IAiProvider;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\EmbeddingModel\OpenAiCompatibleEmbeddingModel;
use Base3\MissionBay\Transport\OpenAiCompatibleTransport;
use PHPUnit\Framework\TestCase;

final class OpenAiCompatibleEmbeddingModelCoverageTest extends TestCase {

	private function model(?IAiProvider $provider = null): OpenAiCompatibleEmbeddingModel {
		$model = new OpenAiCompatibleEmbeddingModel(
			$this->createStub(IClassMap::class),
			new AiProviderRequestEventDispatcher($this->createStub(IEventManager::class))
		);
		if ($provider !== null) {
			(new \ReflectionProperty($model, 'provider'))->setValue($model, $provider);
		}
		return $model;
	}

	public function testProviderIsTheOpenAiCompatibleTransport(): void {
		$model = $this->model();
		$this->assertSame('openaicompatibleembeddingmodel', $model::getName());
		$this->assertSame(OpenAiCompatibleTransport::getName(), (new \ReflectionMethod($model, 'getProviderName'))->invoke($model));
	}

	public function testOptionsAreMergedWithoutLosingExistingOptions(): void {
		$model = $this->model();
		$model->setOptions(['model' => 'embed-a', 'batch_size' => 2]);
		$model->setOptions(['normalize_vectors' => true]);
		$this->assertSame(['model' => 'embed-a', 'batch_size' => 2, 'normalize_vectors' => true], $model->getOptions());
	}

	public function testEmptyInputDoesNotRequireAProvider(): void {
		$model = $this->model();
		$model->setOptions(['model' => 'embed-a', 'auth_type' => 'none']);
		$this->assertSame([], $model->embed([]));
	}

	public function testMissingModelIsRejectedBeforeProviderRequest(): void {
		$model = $this->model();
		$this->expectException(\RuntimeException::class);
		$model->embed(['sample']);
	}

	public function testEmbeddingPayloadIncludesModelAndDimensions(): void {
		$model = $this->model();
		$model->setOptions(['model' => 'embed-a', 'dimensions' => 4]);
		$this->assertSame(['model' => 'embed-a', 'input' => ['A'], 'dimensions' => 4], (new \ReflectionMethod($model, 'buildPayload'))->invoke($model, ['A']));
	}

	public function testEmbeddingPayloadOmitsUnsupportedDimensions(): void {
		$model = $this->model();
		$model->setOptions(['model' => 'embed-a', 'dimensions' => 'invalid']);
		$this->assertSame(['model' => 'embed-a', 'input' => ['A']], (new \ReflectionMethod($model, 'buildPayload'))->invoke($model, ['A']));
	}

	public function testEmbeddingResponseOrdersVectorsByIndex(): void {
		$model = $this->model();
		$raw = ['data' => [['index' => 1, 'embedding' => [3, 4]], ['index' => 0, 'embedding' => [1, 2]]]];
		$this->assertSame([[1.0, 2.0], [3.0, 4.0]], (new \ReflectionMethod($model, 'extractEmbeddings'))->invoke($model, $raw));
	}

	public function testMalformedEmbeddingResponseIsRejected(): void {
		$model = $this->model();
		$this->expectException(\RuntimeException::class);
		(new \ReflectionMethod($model, 'extractEmbeddings'))->invoke($model, ['data' => null]);
	}

	public function testMalformedEmbeddingItemIsRejected(): void {
		$model = $this->model();
		$this->expectException(\RuntimeException::class);
		(new \ReflectionMethod($model, 'extractEmbeddings'))->invoke($model, ['data' => [['index' => 0, 'embedding' => 'wrong']]]);
	}

	public function testNormalizationConvertsVectorsToUnitLength(): void {
		$model = $this->model();
		$this->assertSame([0.6, 0.8], (new \ReflectionMethod($model, 'normalizeVector'))->invoke($model, [3.0, 4.0]));
	}

	public function testZeroVectorRemainsUnchanged(): void {
		$model = $this->model();
		$this->assertSame([0.0, 0.0], (new \ReflectionMethod($model, 'normalizeVector'))->invoke($model, [0.0, 0.0]));
	}

	public function testRequestSettingsUseConfiguredTimeouts(): void {
		$model = $this->model();
		$model->setOptions(['timeout_seconds' => 21, 'connect_timeout_seconds' => 4]);
		$this->assertSame(['timeout' => 21, 'connect_timeout' => 4], (new \ReflectionMethod($model, 'buildRequestOptions'))->invoke($model));
	}

	public function testProviderDoesNotNeedNetworkWhenInputIsEmpty(): void {
		$provider = $this->createStub(IAiProvider::class);
		$provider->method('request')->willThrowException(new \RuntimeException('unexpected request'));
		$model = $this->model($provider);
		$model->setOptions(['model' => 'embed-a', 'auth_type' => 'none']);
		$this->assertSame([], $model->embed([]));
	}
}

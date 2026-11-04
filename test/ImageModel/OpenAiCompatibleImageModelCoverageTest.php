<?php declare(strict_types=1);

namespace MissionBay\Test\ImageModel;

use AssistantFoundation\Api\IAiProvider;
use Base3\Api\IClassMap;
use Base3\Event\Api\IEventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\ImageModel\OpenAiCompatibleImageModel;
use MissionBay\Transport\OpenAiCompatibleTransport;
use PHPUnit\Framework\TestCase;

final class OpenAiCompatibleImageModelCoverageTest extends TestCase {

	private function model(): OpenAiCompatibleImageModel {
		return new OpenAiCompatibleImageModel(
			$this->createStub(IClassMap::class),
			new AiProviderRequestEventDispatcher($this->createStub(IEventManager::class))
		);
	}

	public function testAdapterUsesCompatibleProvider(): void {
		$model = $this->model();
		$this->assertSame('openaicompatibleimagemodel', $model::getName());
		$this->assertSame(OpenAiCompatibleTransport::getName(), (new \ReflectionMethod($model, 'getProviderName'))->invoke($model));
	}

	public function testOptionsAreMerged(): void {
		$model = $this->model();
		$model->setOptions(['model' => 'image-a']);
		$model->setOptions(['size' => '1024x1024']);
		$this->assertSame(['model' => 'image-a', 'size' => '1024x1024'], $model->getOptions());
	}

	public function testWhitespacePromptIsRejectedBeforeProviderAccess(): void {
		$this->expectException(\RuntimeException::class);
		$this->model()->generate("\t  ");
	}

	public function testPayloadRejectsMissingModel(): void {
		$model = $this->model();
		$this->expectException(\RuntimeException::class);
		(new \ReflectionMethod($model, 'buildPayload'))->invoke($model, 'Hello', []);
	}

	public function testImagePayloadMapsSupportedOptions(): void {
		$model = $this->model();
		$payload = (new \ReflectionMethod($model, 'buildPayload'))->invoke($model, 'Sketch', [
			'model' => 'image-a', 'n' => '2', 'size' => 'square', 'output_format' => 'webp', 'quality' => 'high', 'moderation' => 'auto'
		]);
		$this->assertSame(['model' => 'image-a', 'prompt' => 'Sketch', 'size' => 'square', 'quality' => 'high', 'output_format' => 'webp', 'moderation' => 'auto', 'n' => 2], $payload);
	}

	public function testImagePayloadIgnoresInvalidOptionalNumber(): void {
		$model = $this->model();
		$payload = (new \ReflectionMethod($model, 'buildPayload'))->invoke($model, 'Sketch', ['model' => 'image-a', 'n' => 'not a number']);
		$this->assertSame(['model' => 'image-a', 'prompt' => 'Sketch'], $payload);
	}

	public function testMissingImageResponseDataIsRejected(): void {
		$model = $this->model();
		$this->expectException(\RuntimeException::class);
		(new \ReflectionMethod($model, 'extractImages'))->invoke($model, ['data' => 'wrong'], []);
	}

	public function testImageResultsNormalizeMimeTypeAndMetadata(): void {
		$model = $this->model();
		$data = (new \ReflectionMethod($model, 'extractImages'))->invoke($model, ['data' => [
			['b64_json' => 'abc', 'url' => 'https://example.org/p.png', 'revised_prompt' => 'Adjusted']
		]], ['output_format' => 'jpeg']);
		$this->assertSame('image/jpeg', $data[0]['mime_type']);
		$this->assertSame('jpeg', $data[0]['format']);
		$this->assertSame('abc', $data[0]['b64_json']);
		$this->assertSame('Adjusted', $data[0]['revised_prompt']);
	}

	public function testInvalidResultItemsAreSkipped(): void {
		$model = $this->model();
		$this->assertSame([], (new \ReflectionMethod($model, 'extractImages'))->invoke($model, ['data' => [null, 'bad', 42]], []));
	}

	public function testNonStringFieldsBecomeEmptyStrings(): void {
		$model = $this->model();
		$result = (new \ReflectionMethod($model, 'extractImages'))->invoke($model, ['data' => [['b64_json' => 42, 'url' => null]]], ['output_format' => 'webp']);
		$this->assertSame('image/webp', $result[0]['mime_type']);
		$this->assertSame('', $result[0]['b64_json']);
		$this->assertSame('', $result[0]['url']);
	}

	public function testRequestTimeoutsAreClampedToPositiveValues(): void {
		$model = $this->model();
		$this->assertSame(['timeout' => 120, 'connect_timeout' => 15], (new \ReflectionMethod($model, 'buildRequestOptions'))->invoke($model, ['timeout_seconds' => 0, 'connect_timeout_seconds' => -4]));
	}

	public function testCustomImageGenerationPathIsRespected(): void {
		$model = $this->model();
		$this->assertSame('/custom/image', (new \ReflectionMethod($model, 'getGenerationPath'))->invoke($model, ['generation_path' => ' /custom/image ']));
	}
}

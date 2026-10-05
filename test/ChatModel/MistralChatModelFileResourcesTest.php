<?php declare(strict_types=1);

namespace MissionBay\Test\ChatModel;

use AssistantFoundation\Api\IAiFileProvider;
use AssistantFoundation\Api\IAiProvider;
use AssistantFoundation\Dto\AiFileResource;
use AssistantFoundation\Dto\AiProviderFileReference;
use AssistantFoundation\Exception\AiProviderRequestException;
use Base3\Api\IClassMap;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\ChatModel\MistralChatModel;
use PHPUnit\Framework\TestCase;

final class MistralChatModelFileResourcesTest extends TestCase {

	public function testDefaultModelUsesMistralMedium35(): void {
		$provider = new MistralFileTestProvider();
		$model = $this->createModel($provider);
		$model->setOptions([
			'endpoint' => 'https://api.mistral.ai',
			'apikey' => 'test-key',
		]);

		$model->raw([
			['role' => 'user', 'content' => 'Hello'],
		]);

		$this->assertSame('mistral-medium-3-5', $provider->chatPayload['model'] ?? null);
	}

	public function testUploadFileUsesOcrPurposeAndReturnsStableReference(): void {
		$provider = new MistralFileTestProvider();
		$model = $this->createModel($provider);
		$model->setOptions([
			'endpoint' => 'https://api.mistral.ai',
			'apikey' => 'test-key',
		]);
		$content = "%PDF-1.7\nexample";
		$file = new AiFileResource(
			'resources/manual.pdf',
			'manual.pdf',
			'application/pdf',
			$content,
			hash('sha256', $content)
		);

		$reference = $model->uploadFile($file);

		$this->assertSame('/v1/files', $provider->uploadPath);
		$this->assertSame(['purpose' => 'ocr'], $provider->uploadFields);
		$this->assertSame('mistral', $reference->getProvider());
		$this->assertSame('file-uploaded', $reference->getOpaqueId());
		$this->assertSame($file->getContentHash(), $reference->getContentHash());
		$this->assertSame('manual.pdf', $reference->getFilename());
	}

	public function testMissingFileMetadataReturnsNull(): void {
		$provider = new MistralFileTestProvider();
		$model = $this->createModel($provider);
		$model->setOptions([
			'endpoint' => 'https://api.mistral.ai',
			'apikey' => 'test-key',
		]);

		$this->assertNull($model->getFileMetadata($this->reference('missing')));
	}

	public function testFileMetadataUsesStableProviderFileId(): void {
		$provider = new MistralFileTestProvider();
		$model = $this->createModel($provider);
		$model->setOptions([
			'endpoint' => 'https://api.mistral.ai',
			'apikey' => 'test-key',
		]);

		$metadata = $model->getFileMetadata($this->reference('file-1'));

		$this->assertNotNull($metadata);
		$this->assertSame('file-1', $metadata->getOpaqueId());
		$this->assertSame('file-1.pdf', $metadata->getFilename());
		$this->assertSame(1234, $metadata->getSize());
	}

	public function testChatPayloadResolvesSignedUrlsForAllFileReferences(): void {
		$provider = new MistralFileTestProvider();
		$model = $this->createModel($provider);
		$model->setOptions([
			'endpoint' => 'https://api.mistral.ai',
			'apikey' => 'test-key',
		]);
		$model->setFileReferences([
			$this->reference('file-1'),
			$this->reference('file-2'),
		]);

		$model->raw([
			['role' => 'system', 'content' => 'Use the documents.'],
			['role' => 'user', 'content' => 'Which project is described?'],
		]);

		$this->assertSame([
			['type' => 'text', 'text' => 'Which project is described?'],
			['type' => 'document_url', 'document_url' => 'https://files.example.test/file-1.pdf?signature=test'],
			['type' => 'document_url', 'document_url' => 'https://files.example.test/file-2.pdf?signature=test'],
		], $provider->chatPayload['messages'][1]['content'] ?? null);
		$this->assertSame([
			['path' => '/v1/files/file-1/url', 'payload' => ['expiry' => 1], 'method' => 'GET'],
			['path' => '/v1/files/file-2/url', 'payload' => ['expiry' => 1], 'method' => 'GET'],
		], $provider->signedUrlRequests);
	}

	public function testDeleteFileIgnoresAlreadyMissingProviderFile(): void {
		$provider = new MistralFileTestProvider();
		$model = $this->createModel($provider);
		$model->setOptions([
			'endpoint' => 'https://api.mistral.ai',
			'apikey' => 'test-key',
		]);

		$model->deleteFile($this->reference('missing'));

		$this->assertSame('/v1/files/missing', $provider->lastDeletePath);
	}

	private function createModel(MistralFileTestProvider $provider): MistralChatModel {
		return new MistralChatModel(
			new MistralFileTestClassMap($provider),
			new AiProviderRequestEventDispatcher(new EventManager())
		);
	}

	private function reference(string $id): AiProviderFileReference {
		return new AiProviderFileReference(
			'mistral',
			$id,
			hash('sha256', $id),
			$id . '.pdf',
			'application/pdf',
			1234
		);
	}
}

final class MistralFileTestProvider implements IAiFileProvider {

	/** @var array<string,mixed> */
	private array $options = [];

	public string $uploadPath = '';

	/** @var array<string,scalar> */
	public array $uploadFields = [];

	/** @var array<string,mixed> */
	public array $chatPayload = [];

	/** @var array<int,array{path:string,payload:array<string,mixed>,method:string}> */
	public array $signedUrlRequests = [];

	public string $lastDeletePath = '';

	public static function getName(): string {
		return 'mistralfiletestprovider';
	}

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function request(string $path, array $payload, array $options = []): array {
		$method = strtoupper((string)($options['method'] ?? 'POST'));

		if(str_ends_with($path, '/url')) {
			$this->signedUrlRequests[] = [
				'path' => $path,
				'payload' => $payload,
				'method' => $method,
			];
			$id = basename(dirname($path));
			return ['url' => 'https://files.example.test/' . $id . '.pdf?signature=test'];
		}

		if($method === 'GET' && str_starts_with($path, '/v1/files/')) {
			$id = basename($path);
			if($id === 'missing') {
				throw new AiProviderRequestException('missing', 404, '{}');
			}
			return [
				'id' => $id,
				'filename' => $id . '.pdf',
				'bytes' => 1234,
			];
		}

		if($method === 'DELETE' && str_starts_with($path, '/v1/files/')) {
			$this->lastDeletePath = $path;
			if(basename($path) === 'missing') {
				throw new AiProviderRequestException('missing', 404, '{}');
			}
			return ['deleted' => true];
		}

		$this->chatPayload = $payload;
		return [
			'choices' => [
				['message' => ['content' => 'ok']],
			],
		];
	}

	public function stream(string $path, array $payload, callable $onChunk, array $options = []): void {
		$this->chatPayload = $payload;
	}

	public function uploadFile(
		string $path,
		AiFileResource $file,
		array $fields = [],
		array $options = []
	): array {
		$this->uploadPath = $path;
		$this->uploadFields = $fields;

		return [
			'id' => 'file-uploaded',
			'filename' => $file->getName(),
			'bytes' => $file->getSize(),
		];
	}
}

final class MistralFileTestClassMap implements IClassMap {

	/** @var array<int,object> */
	private array $emptyInstances = [];

	private mixed $emptyInstance = null;

	public function __construct(
		private readonly IAiProvider $provider
	) {}

	public function instantiate(string $class) {
		return null;
	}

	public function instantiateWith(string $class, array $arguments = []) {
		return null;
	}

	public function generate($regenerate = false): void {}

	public function getApps() {
		return [];
	}

	public function &getInstances(array $criteria = []) {
		return $this->emptyInstances;
	}

	public function &getInstancesByInterface($interface) {
		return $this->emptyInstances;
	}

	public function &getInstancesByAppInterface($app, $interface, $retry = false) {
		return $this->emptyInstances;
	}

	public function &getInstanceByAppName($app, $name, $retry = false) {
		return $this->emptyInstance;
	}

	public function getClassByInterfaceName(string $interface, string $name): ?string {
		return null;
	}

	public function &getInstanceByInterfaceName($interface, $name, $retry = false) {
		if($interface === IAiProvider::class) {
			$provider = $this->provider;
			return $provider;
		}

		return $this->emptyInstance;
	}

	public function &getInstanceByAppInterfaceName($app, $interface, $name, $retry = false) {
		return $this->getInstanceByInterfaceName($interface, $name, $retry);
	}

	public function getPlugins() {
		return [];
	}
}

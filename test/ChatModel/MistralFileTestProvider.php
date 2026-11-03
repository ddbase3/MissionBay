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

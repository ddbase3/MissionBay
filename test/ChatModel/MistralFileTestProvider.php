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

namespace Base3\MissionBay\Test\ChatModel;

use Base3\AssistantFoundation\Api\IAiFileProvider;
use Base3\AssistantFoundation\Api\IAiProvider;
use Base3\AssistantFoundation\Dto\AiFileResource;
use Base3\AssistantFoundation\Dto\AiProviderFileReference;
use Base3\AssistantFoundation\Exception\AiProviderRequestException;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\ChatModel\MistralChatModel;
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

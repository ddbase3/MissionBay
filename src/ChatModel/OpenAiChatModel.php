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

namespace MissionBay\ChatModel;

use AssistantFoundation\Api\IAiFileCapableChatModel;
use AssistantFoundation\Api\IAiFileProvider;
use AssistantFoundation\Dto\AiFileResource;
use AssistantFoundation\Dto\AiProviderFileMetadata;
use AssistantFoundation\Dto\AiProviderFileReference;
use AssistantFoundation\Exception\AiProviderRequestException;
use MissionBay\Transport\OpenAiTransport;
use RuntimeException;

class OpenAiChatModel extends OpenAiCompatibleChatModel implements IAiFileCapableChatModel {

	private const MAX_FILE_BYTES = 50 * 1024 * 1024;
	private const MAX_REQUEST_FILE_BYTES = 50 * 1024 * 1024;

	/** @var array<int,AiProviderFileReference> */
	private array $fileReferences = [];

	public static function getName(): string {
		return 'openaichatmodel';
	}

	public function getFileProviderName(): string {
		return 'openai';
	}

	public function uploadFile(AiFileResource $file): AiProviderFileReference {
		$this->assertPdfResource($file);

		$provider = $this->getProvider();
		if(!$provider instanceof IAiFileProvider) {
			throw new RuntimeException('Configured OpenAI provider does not support file uploads.');
		}

		$response = $provider->uploadFile('/v1/files', $file, [
			'purpose' => 'user_data',
		], $this->buildRequestOptions(false));
		$id = trim((string)($response['id'] ?? ''));
		if($id === '') {
			throw new RuntimeException('OpenAI file upload response did not contain a file id.');
		}

		$expiresAt = isset($response['expires_at']) && is_numeric($response['expires_at'])
			? (int)$response['expires_at']
			: null;

		return new AiProviderFileReference(
			$this->getFileProviderName(),
			$id,
			$file->getContentHash(),
			$file->getName(),
			$file->getMimeType(),
			$file->getSize(),
			$expiresAt
		);
	}

	public function getFileMetadata(AiProviderFileReference $reference): ?AiProviderFileMetadata {
		$this->assertReferenceProvider($reference);

		try {
			$response = $this->getProvider()->request(
				'/v1/files/' . rawurlencode($reference->getOpaqueId()),
				[],
				$this->buildRequestOptions(false) + ['method' => 'GET']
			);
		}
		catch(AiProviderRequestException $error) {
			if($error->getStatusCode() === 404) {
				return null;
			}
			throw $error;
		}

		$id = trim((string)($response['id'] ?? ''));
		if($id === '') {
			return null;
		}

		return new AiProviderFileMetadata(
			$id,
			trim((string)($response['filename'] ?? $reference->getFilename())),
			max(0, (int)($response['bytes'] ?? $reference->getSize())),
			isset($response['expires_at']) && is_numeric($response['expires_at'])
				? (int)$response['expires_at']
				: $reference->getExpiresAt()
		);
	}

	public function deleteFile(AiProviderFileReference $reference): void {
		$this->assertReferenceProvider($reference);

		try {
			$this->getProvider()->request(
				'/v1/files/' . rawurlencode($reference->getOpaqueId()),
				[],
				$this->buildRequestOptions(false) + ['method' => 'DELETE']
			);
		}
		catch(AiProviderRequestException $error) {
			if($error->getStatusCode() !== 404) {
				throw $error;
			}
		}
	}

	public function setFileReferences(array $references): void {
		$totalBytes = 0;
		$normalized = [];

		foreach($references as $reference) {
			if(!$reference instanceof AiProviderFileReference) {
				throw new \InvalidArgumentException('OpenAI file references must be AiProviderFileReference instances.');
			}
			$this->assertReferenceProvider($reference);
			if(strtolower($reference->getMimeType()) !== 'application/pdf') {
				throw new RuntimeException('OpenAI Chat Completions file inputs currently require PDF resources.');
			}
			if($reference->getSize() > self::MAX_FILE_BYTES) {
				throw new RuntimeException('OpenAI file input exceeds the 50 MB per-file limit: ' . $reference->getFilename());
			}

			$totalBytes += $reference->getSize();
			$normalized[] = $reference;
		}

		if($totalBytes > self::MAX_REQUEST_FILE_BYTES) {
			throw new RuntimeException('OpenAI file inputs exceed the 50 MB combined request limit.');
		}

		$this->fileReferences = $normalized;
	}

	public function getFileReferences(): array {
		return $this->fileReferences;
	}

	protected function getProviderName(): string {
		return OpenAiTransport::getName();
	}

	protected function supportsMultipleSystemMessages(): bool {
		return true;
	}

	protected function getMaxTokensPayloadKey(): string {
		return 'max_completion_tokens';
	}

	protected function getDefaultEndpoint(): string {
		return 'https://api.openai.com';
	}

	protected function getDefaultModel(): string {
		return 'gpt-4o-mini';
	}

	protected function buildPayload(array $messages, array $tools, bool $stream): array {
		$payload = parent::buildPayload($messages, $tools, $stream);
		if($this->fileReferences === []) {
			return $payload;
		}

		$messages = is_array($payload['messages'] ?? null) ? $payload['messages'] : [];
		$userIndex = null;

		for($index = count($messages) - 1; $index >= 0; $index--) {
			if(is_array($messages[$index]) && (string)($messages[$index]['role'] ?? '') === 'user') {
				$userIndex = $index;
				break;
			}
		}

		if($userIndex === null) {
			throw new RuntimeException('OpenAI file inputs require at least one user message.');
		}

		$content = (string)($messages[$userIndex]['content'] ?? '');
		$parts = [];
		foreach($this->fileReferences as $reference) {
			$parts[] = [
				'type' => 'file',
				'file' => [
					'file_id' => $reference->getOpaqueId(),
				],
			];
		}
		if($content !== '') {
			$parts[] = [
				'type' => 'text',
				'text' => $content,
			];
		}

		$messages[$userIndex]['content'] = $parts;
		$payload['messages'] = $messages;

		return $payload;
	}

	private function assertPdfResource(AiFileResource $file): void {
		if(strtolower($file->getMimeType()) !== 'application/pdf' || strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION)) !== 'pdf') {
			throw new RuntimeException('OpenAI Chat Completions file resources must be PDF files: ' . $file->getName());
		}
		if($file->getSize() > self::MAX_FILE_BYTES) {
			throw new RuntimeException('OpenAI file upload exceeds the 50 MB per-file limit: ' . $file->getName());
		}
	}

	private function assertReferenceProvider(AiProviderFileReference $reference): void {
		if($reference->getProvider() !== $this->getFileProviderName()) {
			throw new RuntimeException(
				'Provider file reference belongs to "' . $reference->getProvider() . '", expected "' . $this->getFileProviderName() . '".'
			);
		}
	}
}

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
use MissionBay\Transport\MistralTransport;
use RuntimeException;

class MistralChatModel extends AbstractChatCompletionModel implements IAiFileCapableChatModel {

	private const MAX_FILE_BYTES = 512 * 1024 * 1024;

	/** @var array<int,AiProviderFileReference> */
	private array $fileReferences = [];

	public static function getName(): string {
		return 'mistralchatmodel';
	}

	public function getFileProviderName(): string {
		return 'mistral';
	}

	public function uploadFile(AiFileResource $file): AiProviderFileReference {
		$this->assertPdfResource($file);

		$provider = $this->getProvider();
		if(!$provider instanceof IAiFileProvider) {
			throw new RuntimeException('Configured Mistral provider does not support file uploads.');
		}

		$response = $provider->uploadFile('/v1/files', $file, [
			'purpose' => 'ocr',
		], $this->buildRequestOptions(false));
		$id = trim((string)($response['id'] ?? ''));
		if($id === '') {
			throw new RuntimeException('Mistral file upload response did not contain a file id.');
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

		if(($response['deleted'] ?? false) === true) {
			return null;
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
		$normalized = [];

		foreach($references as $reference) {
			if(!$reference instanceof AiProviderFileReference) {
				throw new \InvalidArgumentException('Mistral file references must be AiProviderFileReference instances.');
			}
			$this->assertReferenceProvider($reference);
			if(strtolower($reference->getMimeType()) !== 'application/pdf') {
				throw new RuntimeException('Mistral Document QnA file inputs currently require PDF resources.');
			}
			if($reference->getSize() > self::MAX_FILE_BYTES) {
				throw new RuntimeException('Mistral file input exceeds the 512 MB per-file limit: ' . $reference->getFilename());
			}

			$normalized[] = $reference;
		}

		$this->fileReferences = $normalized;
	}

	public function getFileReferences(): array {
		return $this->fileReferences;
	}

	protected function getProviderName(): string {
		return MistralTransport::getName();
	}

	protected function getDefaultEndpoint(): string {
		return 'https://api.mistral.ai';
	}

	protected function getDefaultModel(): string {
		return 'mistral-medium-3-5';
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
			throw new RuntimeException('Mistral Document QnA file inputs require at least one user message.');
		}

		$content = (string)($messages[$userIndex]['content'] ?? '');
		$parts = [];
		if($content !== '') {
			$parts[] = [
				'type' => 'text',
				'text' => $content,
			];
		}
		foreach($this->fileReferences as $reference) {
			$parts[] = [
				'type' => 'document_url',
				'document_url' => $this->getSignedFileUrl($reference),
			];
		}

		$messages[$userIndex]['content'] = $parts;
		$payload['messages'] = $messages;

		return $payload;
	}

	private function getSignedFileUrl(AiProviderFileReference $reference): string {
		$this->assertReferenceProvider($reference);
		$response = $this->getProvider()->request(
			'/v1/files/' . rawurlencode($reference->getOpaqueId()) . '/url',
			['expiry' => 1],
			$this->buildRequestOptions(false) + ['method' => 'GET']
		);
		$url = trim((string)($response['url'] ?? ''));
		if($url === '') {
			throw new RuntimeException('Mistral signed file URL response did not contain a URL.');
		}

		return $url;
	}

	private function assertPdfResource(AiFileResource $file): void {
		if(strtolower($file->getMimeType()) !== 'application/pdf' || strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION)) !== 'pdf') {
			throw new RuntimeException('Mistral Document QnA file resources must be PDF files: ' . $file->getName());
		}
		if($file->getSize() > self::MAX_FILE_BYTES) {
			throw new RuntimeException('Mistral file upload exceeds the 512 MB per-file limit: ' . $file->getName());
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

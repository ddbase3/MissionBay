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

namespace MissionBay\Service;

use AssistantFoundation\Api\IAiFileCapableChatModel;
use AssistantFoundation\Dto\AiFileResource;
use AssistantFoundation\Dto\AiProviderFileReference;
use Base3\State\Api\IStateStore;
use ResourceFoundation\Api\IManagedFileStorageService;
use RuntimeException;

final class AiProviderFileReferenceResolver {

	private const LOCK_PREFIX = 'locks.ai.provider-files.';
	private const LOCK_TTL_SECONDS = 120;
	private const LOCK_WAIT_MICROSECONDS = 100000;
	private const LOCK_WAIT_ATTEMPTS = 100;

	public function __construct(
		private readonly IManagedFileStorageService $managedFileStorage,
		private readonly IStateStore $stateStore,
		private readonly AiProviderFileReferenceStateRepository $referenceState
	) {}

	/**
	 * @return array<int,AiProviderFileReference>
	 */
	public function resolve(
		IAiFileCapableChatModel $model,
		string $serviceId,
		string $ownerGroup,
		string $ownerName
	): array {
		$serviceId = $this->requireValue($serviceId, 'Configured LLM service id');
		$ownerGroup = $this->requireValue($ownerGroup, 'AI file owner group');
		$ownerName = $this->requireValue($ownerName, 'AI file owner name');
		$this->referenceState->registerService($serviceId, $ownerGroup, $ownerName);

		if(!$this->managedFileStorage->has(
			$ownerGroup,
			$ownerName,
			IManagedFileStorageService::MODE_COLLECTION
		)) {
			$this->removeStaleReferences($model, $serviceId, $ownerGroup, $ownerName, []);
			$this->referenceState->flush();
			return [];
		}

		$storage = $this->managedFileStorage->openOrCreate(
			$ownerGroup,
			$ownerName,
			IManagedFileStorageService::MODE_COLLECTION
		);
		$items = $storage->list('');
		$currentPaths = [];
		$references = [];

		foreach($items as $item) {
			if(!is_array($item) || (string)($item['type'] ?? '') !== 'file') {
				continue;
			}

			$path = trim((string)($item['name'] ?? ''));
			if($path === '') {
				continue;
			}
			if(strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
				throw new RuntimeException('Chatbot LLM resources currently support PDF files only: ' . $path);
			}

			$content = $storage->read($path);
			$contentHash = hash('sha256', $content);
			$file = new AiFileResource(
				$path,
				basename($path),
				'application/pdf',
				$content,
				$contentHash
			);
			$currentPaths[$path] = true;
			$references[] = $this->resolveFile(
				$model,
				$serviceId,
				$ownerGroup,
				$ownerName,
				$file
			);
		}

		$this->removeStaleReferences($model, $serviceId, $ownerGroup, $ownerName, $currentPaths);
		$this->referenceState->flush();

		return $references;
	}

	private function resolveFile(
		IAiFileCapableChatModel $model,
		string $serviceId,
		string $ownerGroup,
		string $ownerName,
		AiFileResource $file
	): AiProviderFileReference {
		$storedReference = $this->referenceState->get(
			$serviceId,
			$ownerGroup,
			$ownerName,
			$file->getPath()
		);

		if($this->isReusableReference($model, $storedReference, $file)) {
			return $storedReference;
		}

		$lockKey = $this->lockKey($serviceId, $ownerGroup, $ownerName, $file);
		if(!$this->stateStore->setIfNotExists($lockKey, time(), self::LOCK_TTL_SECONDS)) {
			return $this->waitForConcurrentUpload($model, $serviceId, $ownerGroup, $ownerName, $file);
		}

		try {
			$latestReference = $this->referenceState->get(
				$serviceId,
				$ownerGroup,
				$ownerName,
				$file->getPath()
			);
			if($this->isReusableReference($model, $latestReference, $file)) {
				return $latestReference;
			}

			$newReference = $model->uploadFile($file);
			$this->referenceState->set(
				$serviceId,
				$ownerGroup,
				$ownerName,
				$file->getPath(),
				$newReference
			);
			$this->referenceState->flush();

			if(
				$latestReference instanceof AiProviderFileReference
				&& $latestReference->getOpaqueId() !== $newReference->getOpaqueId()
				&& $latestReference->getProvider() === $model->getFileProviderName()
			) {
				$model->deleteFile($latestReference);
			}

			return $newReference;
		}
		finally {
			$this->stateStore->delete($lockKey);
			$this->stateStore->flush();
		}
	}

	private function waitForConcurrentUpload(
		IAiFileCapableChatModel $model,
		string $serviceId,
		string $ownerGroup,
		string $ownerName,
		AiFileResource $file
	): AiProviderFileReference {
		for($attempt = 0; $attempt < self::LOCK_WAIT_ATTEMPTS; $attempt++) {
			usleep(self::LOCK_WAIT_MICROSECONDS);
			$reference = $this->referenceState->get(
				$serviceId,
				$ownerGroup,
				$ownerName,
				$file->getPath()
			);

			if(
				$reference instanceof AiProviderFileReference
				&& $reference->getProvider() === $model->getFileProviderName()
				&& $reference->getContentHash() === $file->getContentHash()
				&& !$reference->isExpired()
			) {
				return $reference;
			}
		}

		throw new RuntimeException('Timed out waiting for concurrent provider file upload: ' . $file->getPath());
	}

	private function isReusableReference(
		IAiFileCapableChatModel $model,
		?AiProviderFileReference $reference,
		AiFileResource $file
	): bool {
		if(!$reference instanceof AiProviderFileReference) {
			return false;
		}
		if($reference->getProvider() !== $model->getFileProviderName()) {
			return false;
		}
		if($reference->getContentHash() !== $file->getContentHash()) {
			return false;
		}
		if($reference->isExpired()) {
			return false;
		}

		$metadata = $model->getFileMetadata($reference);
		return $metadata !== null && !$metadata->isExpired();
	}

	/** @param array<string,bool> $currentPaths */
	private function removeStaleReferences(
		IAiFileCapableChatModel $model,
		string $serviceId,
		string $ownerGroup,
		string $ownerName,
		array $currentPaths
	): void {
		foreach($this->referenceState->list($serviceId, $ownerGroup, $ownerName) as $record) {
			if(isset($currentPaths[$record['path']])) {
				continue;
			}

			$reference = $record['reference'];
			if($reference->getProvider() !== $model->getFileProviderName()) {
				throw new RuntimeException(
					'Provider file reference belongs to "' . $reference->getProvider()
					. '", expected "' . $model->getFileProviderName() . '".'
				);
			}

			$model->deleteFile($reference);
			$this->referenceState->deleteKey($record['key']);
		}
	}

	private function lockKey(
		string $serviceId,
		string $ownerGroup,
		string $ownerName,
		AiFileResource $file
	): string {
		return self::LOCK_PREFIX . hash(
			'sha256',
			$ownerGroup . "\0"
			. $ownerName . "\0"
			. $serviceId . "\0"
			. $file->getPath() . "\0"
			. $file->getContentHash()
		);
	}

	private function requireValue(string $value, string $label): string {
		$value = trim($value);
		if($value === '') {
			throw new \InvalidArgumentException($label . ' is required.');
		}
		return $value;
	}
}

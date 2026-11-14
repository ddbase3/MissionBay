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

require_once __DIR__ . '/MistralFileTestProvider.php';
require_once __DIR__ . '/MistralFileTestClassMap.php';

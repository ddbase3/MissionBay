<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Resource\UploadStreamExtractorAgentResource;
use PHPUnit\Framework\TestCase;

final class UploadStreamExtractorInputBoundaryTest extends TestCase {

	public function testMissingUploadProducesNoContent(): void {
		$this->assertSame([], $this->extractor()->extract($this->context(null)));
	}

	public function testNonArrayUploadProducesNoContent(): void {
		$this->assertSame([], $this->extractor()->extract($this->context('untrusted upload')));
	}

	public function testEmptyUploadRecordProducesNoContent(): void {
		$this->assertSame([], $this->extractor()->extract($this->context([])));
	}

	public function testMissingTemporaryFileProducesNoContent(): void {
		$this->assertSame([], $this->extractor()->extract($this->context(['filename' => 'test.txt'])));
	}

	public function testUnreadableTemporaryFileProducesNoContent(): void {
		$this->assertSame([], $this->extractor()->extract($this->context([
			'tmpfile' => sys_get_temp_dir() . '/missionbay_missing_upload_aaaaaaaaa',
			'mimetype' => 'text/plain'
		])));
	}

	public function testResourceIdentityCanBeUpdated(): void {
		$extractor = $this->extractor();
		$this->assertSame('upload-unit', $extractor->getId());
		$extractor->setId('upload-renamed');
		$this->assertSame('upload-renamed', $extractor->getId());
		$this->assertSame('uploadstreamextractoragentresource', $extractor::getName());
	}

	private function extractor(): UploadStreamExtractorAgentResource {
		return new UploadStreamExtractorAgentResource('upload-unit');
	}

	private function context(mixed $value): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->with('upload_stream')->willReturn($value);
		return $context;
	}
}

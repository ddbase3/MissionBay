<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentContentExtractor;

/**
 * UploadStreamExtractorAgentResource
 *
 * Extracts a single uploaded file or binary stream from the agent context.
 * Expected context variable: 'upload_stream'
 *
 * Example structure set by controller/service:
 *   $context->setVar('upload_stream', [
 *       'id'       => 'doc_123',
 *       'filename' => 'example.pdf',
 *       'mimetype' => 'application/pdf',
 *       'tmpfile'  => '/tmp/upload_xyz'
 *   ]);
 */
class UploadStreamExtractorAgentResource extends AbstractAgentResource implements IAgentContentExtractor {

	public static function getName(): string {
		return strtolower(__CLASS__);
	}

	public function getDescription(): string {
		return 'Extracts an uploaded binary document stream from the context for embedding.';
	}

	/**
	 * Extracts exactly one uploaded document from context.
	 *
	 * @param IAgentContext $context
	 * @return array<int,array<string,mixed>>
	 */
	public function extract(IAgentContext $context): array {
		$upload = $context->getVar('upload_stream');

		if (!$upload || !is_array($upload)) {
			return [];
		}

		$tmpfile = $upload['tmpfile'] ?? null;
		if (!$tmpfile || !is_readable($tmpfile)) {
			return [];
		}

		$bytes = file_get_contents($tmpfile);

		return [[
			'id'       => $upload['id']       ?? uniqid('upload_', true),
			'filename' => $upload['filename'] ?? 'upload.bin',
			'mimetype' => $upload['mimetype'] ?? 'application/octet-stream',
			'size'     => strlen($bytes),
			'bytes'    => $bytes
		]];
	}
}

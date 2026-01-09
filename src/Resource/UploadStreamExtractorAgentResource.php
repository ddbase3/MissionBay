<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentContentExtractor;
use MissionBay\Api\IAgentContext;
use MissionBay\Dto\AgentContentItem;

/**
 * UploadStreamExtractorAgentResource
 *
 * Extracts any uploaded document and produces a normalized AgentContentItem.
 */
class UploadStreamExtractorAgentResource extends AbstractAgentResource implements IAgentContentExtractor {

	public static function getName(): string {
		return 'uploadstreamextractoragentresource';
	}

	public function getDescription(): string {
		return 'Extracts an uploaded document stream from the context.';
	}

	/**
	 * @return AgentContentItem[]
	 */
	public function extract(IAgentContext $context): array {
		$upload = $context->getVar('upload_stream');

		if (!$upload || !is_array($upload)) {
			return [];
		}

		$tmpfile  = $upload['tmpfile']  ?? null;
		$filename = $upload['filename'] ?? 'upload';
		$mimetype = $upload['mimetype'] ?? 'application/octet-stream';

		if (!$tmpfile || !is_readable((string)$tmpfile)) {
			return [];
		}

		$bytes = file_get_contents((string)$tmpfile);
		if (!is_string($bytes) || $bytes === '') {
			return [];
		}

		$hash = hash('sha256', $bytes);

		$isText =
			str_starts_with((string)$mimetype, 'text/')
			|| in_array((string)$mimetype, [
				'application/json',
				'application/xml',
				'application/javascript'
			], true);

		$item = new AgentContentItem(
			id: $hash,
			hash: $hash,
			contentType: (string)$mimetype,
			content: $bytes,
			isBinary: !$isText,
			size: strlen($bytes),
			metadata: [
				'filename' => (string)$filename
			]
		);

		return [$item];
	}

	/**
	 * Ack hook (dummy).
	 */
	public function ack(AgentContentItem $item, array $result = []): void {
		// no-op
	}

	/**
	 * Fail hook (dummy).
	 */
	public function fail(AgentContentItem $item, string $errorMessage, bool $retryHint = true): void {
		// no-op
	}
}

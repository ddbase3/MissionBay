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

                if (!$tmpfile || !is_readable($tmpfile)) {
                        return [];
                }

                $bytes = file_get_contents($tmpfile);
                if (!is_string($bytes) || $bytes === '') {
                        return [];
                }

                // stable, content-based hash
                $hash = hash('sha256', $bytes);

                // decide text vs binary
                $isText =
                        str_starts_with($mimetype, 'text/')
                        || in_array($mimetype, [
                                'application/json',
                                'application/xml',
                                'application/javascript'
                        ]);

                $item = new AgentContentItem(
                        id: $hash,
                        hash: $hash,
                        contentType: $mimetype,
                        content: $bytes,      // ALWAYS string, no binaryData/textData split
                        isBinary: !$isText,
                        size: strlen($bytes),
                        metadata: [
                                'filename' => $filename
                        ]
                );

                return [$item];
        }
}

<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentContentExtractor;
use MissionBay\Api\IAgentContext;
use MissionBay\Dto\AgentContentItem;
use ResourceFoundation\Api\IEntityDataService;

/**
 * CrmProductXrmExtractorAgentResource
 *
 * Extracts CRM products (type "product" + tag "crm") from XRM
 * and normalizes them into AgentContentItem objects.
 */
class CrmProductXrmExtractorAgentResource extends AbstractAgentResource implements IAgentContentExtractor {

	public function __construct(
		protected IEntityDataService $entityDataService,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'crmproductxrmextractoragentresource';
	}

	public function getDescription(): string {
		return 'Extracts CRM product entries from XRM and converts them into normalized content items.';
	}

	/**
	 * @return AgentContentItem[]
	 */
	public function extract(IAgentContext $context): array {
		$entries = $this->loadEntries();
		return $this->mapEntriesToItems($entries);
	}

	/**
	 * Load all CRM product entries via XRM.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	protected function loadEntries(): array {
		$options = [
			'type'       => 'product',
			'tag'        => ['crm'],
			'loadname'   => true,
			'loaddata'   => true,
			'loadaccess' => false,
			'archive'    => 'all'
		];

		return $this->entityDataService->getEntries($options);
	}

	/**
	 * Convert CRM product entries into AgentContentItems.
	 *
	 * @param array<int, array<string,mixed>> $entries
	 * @return AgentContentItem[]
	 */
	protected function mapEntriesToItems(array $entries): array {
		$items = [];

		foreach ($entries as $entry) {
			// tidy up
			unset($entry['data']['price']);
			unset($entry['data']['weight']);

			$raw = [
				'id'   => $entry['id'] ?? null,
				'name' => $entry['name'] ?? '',
				'data' => $entry['data'] ?? []
			];

			$json = json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			$hash = hash('sha256', (string)$json);

			$items[] = new AgentContentItem(
				id: $hash,
				hash: $hash,
				contentType: 'application/x-crm-json',
				content: $raw,
				isBinary: false,
				size: strlen((string)$json),
				metadata: [
					'content_id'   => $entry['id'] ?? null,
					'content_uuid' => $entry['uuid'] ?? null,
					'name'         => $entry['name'] ?? '',
				]
			);
		}

		return $items;
	}

	/**
	 * Ack hook (dummy for legacy extractor).
	 */
	public function ack(AgentContentItem $item, array $result = []): void {
		// no-op (legacy extractor has no queue)
	}

	/**
	 * Fail hook (dummy for legacy extractor).
	 */
	public function fail(AgentContentItem $item, string $errorMessage, bool $retryHint = true): void {
		// no-op (legacy extractor has no queue)
	}
}

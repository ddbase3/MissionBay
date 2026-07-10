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

namespace MissionBay\Resource;

use MissionBay\Api\IAgentContentExtractor;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Dto\AgentContentItem;
use ResourceFoundation\Api\IEntityDataService;

/**
 * ProductXrmExtractorAgentResource
 *
 * Extracts all products from XRM
 * and normalizes them into AgentContentItem objects.
 */
class ProductXrmExtractorAgentResource extends AbstractAgentResource implements IAgentContentExtractor {

	public function __construct(
		protected IEntityDataService $entityDataService,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'productxrmextractoragentresource';
	}

	public function getDescription(): string {
		return 'Extracts product entries from XRM and converts them into normalized content items.';
	}

	/**
	 * @return AgentContentItem[]
	 */
	public function extract(IAgentContext $context): array {
		$entries = $this->loadEntries();
		return $this->mapEntriesToItems($entries);
	}

	/**
	 * Load all product entries via XRM.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	protected function loadEntries(): array {
		$options = [
			'type'       => 'product',
			'loadname'   => true,
			'loaddata'   => true,
			'loadaccess' => false,
			'archive'    => 'all'
		];

		return $this->entityDataService->getEntries($options);
	}

	/**
	 * Convert product entries into AgentContentItems.
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

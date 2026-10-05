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

use AssistantFoundation\Dto\AiProviderFileReference;
use Base3\State\Api\IStateStore;
use InvalidArgumentException;

final class AiProviderFileReferenceStateRepository {

	private const STATE_PREFIX = 'ai.provider-files.';
	private const OWNER_INDEX_PREFIX = 'ai.provider-file-owners.';

	public function __construct(
		private readonly IStateStore $stateStore
	) {}

	public function registerService(string $serviceId, string $ownerGroup, string $ownerName): void {
		$serviceId = $this->requireValue($serviceId, 'Configured LLM service id');
		$key = $this->ownerIndexKey($ownerGroup, $ownerName);
		$record = $this->stateStore->get($key, []);
		$serviceIds = is_array($record) && is_array($record['service_ids'] ?? null)
			? $record['service_ids']
			: [];
		$serviceIds[$serviceId] = $serviceId;
		ksort($serviceIds);

		$this->stateStore->set($key, [
			'owner_group' => $ownerGroup,
			'owner_name' => $ownerName,
			'service_ids' => $serviceIds,
		]);
	}

	/** @return array<int,string> */
	public function listRegisteredServiceIds(string $ownerGroup, string $ownerName): array {
		$record = $this->stateStore->get($this->ownerIndexKey($ownerGroup, $ownerName), []);
		if(!is_array($record) || !is_array($record['service_ids'] ?? null)) {
			return [];
		}

		$serviceIds = [];
		foreach($record['service_ids'] as $serviceId) {
			$serviceId = trim((string)$serviceId);
			if($serviceId !== '') {
				$serviceIds[$serviceId] = $serviceId;
			}
		}
		$serviceIds = array_values($serviceIds);
		sort($serviceIds);

		return $serviceIds;
	}

	public function get(
		string $serviceId,
		string $ownerGroup,
		string $ownerName,
		string $path
	): ?AiProviderFileReference {
		$record = $this->stateStore->get(
			$this->stateKey($serviceId, $ownerGroup, $ownerName, $path),
			null
		);
		if(!is_array($record)) {
			return null;
		}
		if(trim((string)($record['path'] ?? '')) !== $path) {
			return null;
		}
		if(trim((string)($record['service_id'] ?? '')) !== $serviceId) {
			return null;
		}
		if(!is_array($record['reference'] ?? null)) {
			return null;
		}

		return AiProviderFileReference::fromArray($record['reference']);
	}

	public function set(
		string $serviceId,
		string $ownerGroup,
		string $ownerName,
		string $path,
		AiProviderFileReference $reference
	): void {
		$this->registerService($serviceId, $ownerGroup, $ownerName);
		$this->stateStore->set(
			$this->stateKey($serviceId, $ownerGroup, $ownerName, $path),
			[
				'path' => $path,
				'service_id' => $serviceId,
				'reference' => $reference->toArray(),
			]
		);
	}

	/**
	 * @return array<int,array{key:string,path:string,reference:AiProviderFileReference}>
	 */
	public function list(string $serviceId, string $ownerGroup, string $ownerName): array {
		$records = [];

		foreach($this->stateStore->listKeys($this->statePrefix($serviceId, $ownerGroup, $ownerName)) as $stateKey) {
			$record = $this->stateStore->get($stateKey, null);
			if(!is_array($record)) {
				$this->stateStore->delete($stateKey);
				continue;
			}
			$path = trim((string)($record['path'] ?? ''));
			if(
				$path === ''
				|| trim((string)($record['service_id'] ?? '')) !== $serviceId
				|| !is_array($record['reference'] ?? null)
			) {
				$this->stateStore->delete($stateKey);
				continue;
			}

			$records[] = [
				'key' => $stateKey,
				'path' => $path,
				'reference' => AiProviderFileReference::fromArray($record['reference']),
			];
		}

		return $records;
	}

	public function deleteKey(string $stateKey): void {
		$this->stateStore->delete($stateKey);
	}

	public function clearOwnerIndex(string $ownerGroup, string $ownerName): void {
		$this->stateStore->delete($this->ownerIndexKey($ownerGroup, $ownerName));
	}

	public function flush(): void {
		$this->stateStore->flush();
	}

	private function statePrefix(string $serviceId, string $ownerGroup, string $ownerName): string {
		$serviceId = $this->requireValue($serviceId, 'Configured LLM service id');
		$ownerGroup = $this->requireValue($ownerGroup, 'AI file owner group');
		$ownerName = $this->requireValue($ownerName, 'AI file owner name');

		return self::STATE_PREFIX . hash('sha256', $ownerGroup . "\0" . $ownerName . "\0" . $serviceId) . '.';
	}

	private function stateKey(string $serviceId, string $ownerGroup, string $ownerName, string $path): string {
		$path = $this->requireValue($path, 'AI file path');
		return $this->statePrefix($serviceId, $ownerGroup, $ownerName) . hash('sha256', $path);
	}

	private function ownerIndexKey(string $ownerGroup, string $ownerName): string {
		$ownerGroup = $this->requireValue($ownerGroup, 'AI file owner group');
		$ownerName = $this->requireValue($ownerName, 'AI file owner name');

		return self::OWNER_INDEX_PREFIX . hash('sha256', $ownerGroup . "\0" . $ownerName);
	}

	private function requireValue(string $value, string $label): string {
		$value = trim($value);
		if($value === '') {
			throw new InvalidArgumentException($label . ' is required.');
		}
		return $value;
	}
}

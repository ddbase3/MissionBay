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

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Api\IAiFileCapableChatModel;
use Base3\Settings\Api\ISettingsStore;
use ResourceFoundation\Api\IManagedFileStorageService;
use ResourceFoundation\Event\ManagedFileStorageDeletingEvent;
use RuntimeException;

final class AiProviderFileOwnerCleanupService {

	private const LLM_SETTINGS_GROUP = 'service-llm';
	private const SERVICE_TYPE = 'llm';
	private const SERVICE_ALIAS = 'llm';

	public function __construct(
		private readonly AiProviderFileReferenceStateRepository $referenceState,
		private readonly ConfiguredServiceRuntimeResolver $runtimeResolver,
		private readonly ISettingsStore $settingsStore
	) {}

	public function handle(ManagedFileStorageDeletingEvent $event): void {
		if($event->getMode() !== IManagedFileStorageService::MODE_COLLECTION) {
			return;
		}

		$this->cleanup($event->getOwnerGroup(), $event->getOwnerName());
	}

	public function cleanup(string $ownerGroup, string $ownerName): void {
		$serviceIds = [];

		foreach($this->referenceState->listRegisteredServiceIds($ownerGroup, $ownerName) as $serviceId) {
			$serviceIds[$serviceId] = $serviceId;
		}
		foreach($this->settingsStore->getGroup(self::LLM_SETTINGS_GROUP) as $serviceId => $settings) {
			if(is_string($serviceId) && trim($serviceId) !== '' && is_array($settings)) {
				$serviceIds[$serviceId] = $serviceId;
			}
		}
		ksort($serviceIds);

		foreach($serviceIds as $serviceId) {
			$records = $this->referenceState->list($serviceId, $ownerGroup, $ownerName);
			if($records === []) {
				continue;
			}

			$settings = $this->settingsStore->get(self::LLM_SETTINGS_GROUP, $serviceId, []);
			if($settings === []) {
				throw new RuntimeException(
					'Cannot delete provider file resources because the configured LLM service no longer exists: ' . $serviceId
				);
			}

			$model = $this->runtimeResolver->resolveSettings(
				$serviceId,
				$settings,
				self::SERVICE_TYPE,
				self::SERVICE_ALIAS,
				IAiChatModel::class
			);
			if(!$model instanceof IAiFileCapableChatModel) {
				throw new RuntimeException(
					'Cannot delete provider file resources because the configured LLM service is no longer file-capable: ' . $serviceId
				);
			}

			foreach($records as $record) {
				$reference = $record['reference'];
				if($reference->getProvider() !== $model->getFileProviderName()) {
					throw new RuntimeException(
						'Provider file reference belongs to "' . $reference->getProvider()
						. '", but service "' . $serviceId . '" resolves to "' . $model->getFileProviderName() . '".'
					);
				}

				$model->deleteFile($reference);
				$this->referenceState->deleteKey($record['key']);
				$this->referenceState->flush();
			}
		}

		$this->referenceState->clearOwnerIndex($ownerGroup, $ownerName);
		$this->referenceState->flush();
	}
}

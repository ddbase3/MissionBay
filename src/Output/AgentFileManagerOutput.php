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

namespace Base3\MissionBay\Output;

use Base3\AssistantFoundation\Api\IAgentConfigurationService;
use Base3\CoreFoundation\Api\IOutput;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Permission;
use Base3\ClientStack\FileManager\FileManagerHttpService;
use Base3\MissionBay\SettingsTab\AgentadminSettingsTab;
use Base3\ResourceFoundation\Api\IManagedFileStorageService;
use Throwable;

/**
 * Exposes the existing ClientStack FileManager transport for configured agent
 * resources, independent of the platform that provides managed file storage.
 * Identity and authorization are checked here before any storage operation.
 */
final class AgentFileManagerOutput implements IOutput {

	private const OWNER_GROUP = 'agent';

	public function __construct(
		private readonly IRequest $request,
		private readonly IUsermanager $usermanager,
		private readonly IAuthorizationService $authorization,
		private readonly IAgentConfigurationService $agentConfigurations,
		private readonly FileManagerHttpService $fileManagerHttpService,
		private readonly ITranslation $translation
	) {}

	public static function getName(): string {
		return 'agentfilemanageroutput';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		try {
			$allowed = $this->authorization->decide(new AuthorizationRequest(
				$this->authorization->getDefaultAuthority(),
				Permission::for('feature', AgentadminSettingsTab::getName())
			))->isAllowed();
		} catch (Throwable) {
			$allowed = false;
		}
		if (!$allowed) {
			return $this->errorResponse(403, $this->translation->translate('Administration', 'missionbay_admin', 'agent_files_access_denied'));
		}

		$group = $this->queryString('fm_group');
		$id = $this->queryString('fm_name');
		if ($group !== self::OWNER_GROUP || $id === '' ||
			preg_match('/^[a-z0-9._-]+$/D', $id) !== 1 || !$this->agentConfigurations->hasAgent($id)) {
			return $this->errorResponse(404, $this->translation->translate('Administration', 'missionbay_admin', 'agent_files_not_found'));
		}

		$user = $this->usermanager->getUser();
		$actorId = $user !== null && is_scalar($user->id) ? trim((string)$user->id) : '';
		if ($actorId === '' || $actorId === '0') {
			return $this->errorResponse(403, $this->translation->translate('Administration', 'missionbay_admin', 'agent_files_access_denied'));
		}

		return $this->fileManagerHttpService->handle(
			$this->queryString('fm_action'),
			self::OWNER_GROUP,
			$id,
			IManagedFileStorageService::MODE_COLLECTION,
			$actorId,
			FileManagerHttpService::DEFAULT_MAX_FILE_SIZE
		);
	}

	private function queryString(string $name): string {
		$value = $this->request->get($name, '');
		return is_scalar($value) ? trim((string)$value) : '';
	}

	private function errorResponse(int $status, string $message): string {
		http_response_code($status);
		if (!headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
			header('Cache-Control: no-store');
		}
		return json_encode(['message' => $message], JSON_THROW_ON_ERROR);
	}
}

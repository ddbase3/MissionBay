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

namespace Base3\MissionBay\Mcp;

use Base3\CredentialFoundation\Api\ICredentialServiceProvider;
use Base3\CredentialFoundation\Dto\CredentialServiceDefinition;

/**
 * Publishes credential-protected services for enabled MissionBay MCP profiles.
 */
final class McpProfileCredentialServiceProvider implements ICredentialServiceProvider {

	public function __construct(
		private readonly McpToolProfileRepository $profileRepository
	) {}

	public static function getName(): string {
		return 'mcpprofilecredentialserviceprovider';
	}

	public function getServices(): array {
		$services = [];

		foreach($this->profileRepository->getEnabledMcpProfiles() as $profileId => $profile) {
			if(!$this->profileRepository->isCredentialAccessEnabled($profile)) {
				continue;
			}

			$label = trim((string)($profile['label'] ?? ''));
			$description = trim((string)($profile['description'] ?? ''));

			if($label === '') {
				$label = $profileId;
			}

			$services[] = new CredentialServiceDefinition(
				$this->profileRepository->getCredentialServiceId($profileId),
				'MissionBay MCP - ' . $label,
				$description !== ''
					? $description
					: 'Grants access to the MissionBay MCP profile "' . $label . '".'
			);
		}

		return $services;
	}
}

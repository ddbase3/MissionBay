<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use CredentialFoundation\Api\ICredentialServiceProvider;
use CredentialFoundation\Dto\CredentialServiceDefinition;

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

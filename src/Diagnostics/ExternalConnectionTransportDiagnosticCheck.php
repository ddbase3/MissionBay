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

namespace Base3\MissionBay\Diagnostics;

use Base3\CoreFoundation\Diagnostics\Api\IDiagnosticCheck;
use Base3\CoreFoundation\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\Core\Diagnostics\Scope\PrivacyDiagnosticScope;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Connection\ConnectionConfig;

/** Read-only inspection of TLS URL schemes for enabled remote HTTP connections. */
final class ExternalConnectionTransportDiagnosticCheck implements IDiagnosticCheck {

	public function __construct(private readonly ISettingsStore $settingsStore) {}

	public static function getName(): string {
		return 'externalconnectiontransportdiagnosticcheck';
	}

	public function getScope(): string {
		return PrivacyDiagnosticScope::getName();
	}

	public function run(): DiagnosticCheckResult {
		$checked = 0;
		$insecure = 0;
		$unverifiable = 0;
		foreach($this->settingsStore->getGroup('connection') as $id => $settings) {
			if(!is_string($id) || $id === '' || !is_array($settings)) {
				continue;
			}
			$config = ConnectionConfig::fromSettings($id, $settings);
			if(!$config->isEnabled() || $config->getType() !== 'http') {
				continue;
			}
			$url = $config->getBaseUrl();
			if($url === '') {
				$unverifiable++;
				continue;
			}

			$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
			$host = strtolower(trim((string)parse_url($url, PHP_URL_HOST), '[]'));
			if($host === '') {
				$unverifiable++;
				continue;
			}
			if(in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
				continue;
			}
			$checked++;
			if($scheme !== 'https') {
				$insecure++;
			}
		}

		if($checked === 0 && $unverifiable === 0) {
			return new DiagnosticCheckResult(DiagnosticCheckResult::NOT_APPLICABLE, 'not_applicable');
		}

		$status = $insecure > 0 ? DiagnosticCheckResult::FAILED
			: ($unverifiable > 0 ? DiagnosticCheckResult::WARNING : DiagnosticCheckResult::PASSED);
		return new DiagnosticCheckResult(
			$status,
			$insecure > 0 ? 'failed' : ($unverifiable > 0 ? 'warning' : 'passed'),
			['count' => $checked, 'insecure' => $insecure, 'unverifiable' => $unverifiable],
			$status !== DiagnosticCheckResult::PASSED ? 'remediation' : '',
			['remoteConnections' => $checked, 'insecureConnections' => $insecure, 'unverifiableConnections' => $unverifiable]
		);
	}

}

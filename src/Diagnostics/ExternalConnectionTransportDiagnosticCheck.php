<?php declare(strict_types=1);

namespace MissionBay\Diagnostics;

use Base3\Diagnostics\Api\IDiagnosticCheck;
use Base3\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\Diagnostics\Scope\PrivacyDiagnosticScope;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Connection\ConnectionConfig;

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

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

namespace MissionBay\Display;

use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IConnectionDriverDefinition;
use MissionBay\Connection\ConnectionConfig;
use RuntimeException;

final class ConnectionConfigDisplay implements IDisplay {

	private const SETTINGS_GROUP = 'connection';

	public function __construct(
		private readonly IMvcView $view,
		private readonly IRequest $request,
		private readonly ILinkTargetService $linkTargetService,
		private readonly ISettingsStore $settingsStore,
		private readonly IClassMap $classMap
	) {}

	public static function getName(): string {
		return 'connectionconfigdisplay';
	}

	public function getHelp(): string {
		return 'Configure reusable technical connections for MissionBay services.';
	}

	public function setData($data) {
		// no-op
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$out = strtolower((string)$out);

		if($out === 'json') {
			return $this->handleJson();
		}

		return $this->handleHtml();
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/ConnectionConfigDisplay.php');

		$this->view->assign('instanceId', 'connectioncfg-' . uniqid());
		$this->view->assign('endpoint', $this->buildEndpointBase());
		$this->view->assign('configGroup', self::SETTINGS_GROUP);

		return $this->view->loadTemplate();
	}

	private function handleJson(): string {
		$action = strtolower(trim((string)$this->request->request('action', '')));

		try {
			return match($action) {
				'list' => $this->jsonSuccess([
					'group' => self::SETTINGS_GROUP,
					'drivers' => $this->listDriverDefinitions(),
					'connections' => $this->listConnections()
				]),
				'save' => $this->jsonSuccess([
					'group' => self::SETTINGS_GROUP,
					'connection' => $this->saveConnection()
				]),
				'remove' => $this->jsonSuccess([
					'group' => self::SETTINGS_GROUP,
					'id' => $this->removeConnection()
				]),
				default => $this->jsonError("Unknown action '$action'. Use: list|save|remove"),
			};
		}
		catch(\Throwable $e) {
			return $this->jsonError('Exception: ' . $e->getMessage());
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function listConnections(): array {
		$group = $this->settingsStore->getGroup(self::SETTINGS_GROUP);
		$drivers = $this->listDriverDefinitionsByDriver();
		$rows = [];

		foreach($group as $id => $settings) {
			if(!is_string($id) || $id === '' || !is_array($settings)) {
				continue;
			}

			$config = ConnectionConfig::fromSettings($id, $settings);
			$row = $config->toDisplayArray();

			$row['driverLabel'] = (string)($drivers[$config->getDriver()]['label'] ?? $config->getDriver());
			$row['driverType'] = (string)($drivers[$config->getDriver()]['type'] ?? $row['type']);

			$rows[] = $row;
		}

		usort($rows, function(array $a, array $b): int {
			$aSort = trim((string)($a['name'] ?? ''));
			$bSort = trim((string)($b['name'] ?? ''));

			if($aSort === '') {
				$aSort = (string)($a['id'] ?? '');
			}

			if($bSort === '') {
				$bSort = (string)($b['id'] ?? '');
			}

			$cmp = strcasecmp($aSort, $bSort);
			if($cmp !== 0) {
				return $cmp;
			}

			return strcasecmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
		});

		return $rows;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function listDriverDefinitions(): array {
		$rows = array_values($this->listDriverDefinitionsByDriver());

		usort($rows, function(array $a, array $b): int {
			return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
		});

		return $rows;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function listDriverDefinitionsByDriver(): array {
		$instances = $this->classMap->getInstancesByInterface(IConnectionDriverDefinition::class);
		$rows = [];

		foreach($instances as $instance) {
			if(!$instance instanceof IConnectionDriverDefinition) {
				continue;
			}

			$driver = $this->normalizeKey($instance->getDriver());

			if($driver === '') {
				continue;
			}

			$rows[$driver] = [
				'name' => $instance::getName(),
				'driver' => $driver,
				'label' => $instance->getLabel(),
				'type' => $instance->getConnectionType(),
				'configSchema' => $instance->getConfigSchema(),
				'defaultConfig' => $instance->getDefaultConfig(),
				'healthCheckSchema' => $instance->getHealthCheckSchema()
			];
		}

		return $rows;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function saveConnection(): array {
		$id = $this->normalizeKey((string)$this->request->request('id', ''));
		$name = trim((string)$this->request->request('name', ''));
		$driver = $this->normalizeKey((string)$this->request->request('driver', ''));
		$type = $this->normalizeKey((string)$this->request->request('type', ''));
		$baseUrl = trim((string)$this->request->request('baseUrl', ''));
		$authType = $this->normalizeAuthType((string)$this->request->request('authType', 'none'));
		$secretMode = $this->normalizeSecretMode((string)$this->request->request('secretMode', 'fixed'));
		$secretValue = trim((string)$this->request->request('secretValue', ''));
		$authHeaderName = trim((string)$this->request->request('authHeaderName', ''));
		$timeoutSeconds = $this->readPositiveInt('timeoutSeconds', 60, 'Timeout seconds');
		$scope = $this->normalizeKey((string)$this->request->request('scope', 'global'));
		$enabled = $this->normalizeBool($this->request->request('enabled', 0));
		$options = $this->readOptions();

		if($id === '') {
			throw new RuntimeException('Missing connection id.');
		}

		if($name === '') {
			throw new RuntimeException('Missing connection name.');
		}

		if($driver === '') {
			throw new RuntimeException('Missing connection driver.');
		}

		$driverDefinition = $this->listDriverDefinitionsByDriver()[$driver] ?? null;

		if(is_array($driverDefinition)) {
			$type = $this->normalizeKey((string)($driverDefinition['type'] ?? $type));
		}

		if($type === '') {
			throw new RuntimeException('Missing connection type.');
		}

		if($baseUrl === '' && $type === 'http') {
			throw new RuntimeException('Missing base URL.');
		}

		if($authType !== 'none' && $secretValue === '') {
			throw new RuntimeException('Missing secret value.');
		}

		if($authType === 'none') {
			$secretMode = 'fixed';
			$secretValue = '';
			$authHeaderName = '';
		}

		if($scope === '') {
			$scope = 'global';
		}

		$config = new ConnectionConfig(
			$id,
			$name,
			$type,
			$driver,
			$baseUrl,
			$authType,
			$secretMode,
			$secretValue,
			$timeoutSeconds,
			$scope,
			$enabled,
			$options,
			$authHeaderName
		);

		$this->settingsStore->set(self::SETTINGS_GROUP, $id, $config->toSettings());
		$this->settingsStore->save();

		return $config->toDisplayArray();
	}

	private function removeConnection(): string {
		$id = $this->normalizeKey((string)$this->request->request('id', ''));

		if($id === '') {
			throw new RuntimeException('Missing connection id.');
		}

		$this->settingsStore->remove(self::SETTINGS_GROUP, $id);
		$this->settingsStore->save();

		return $id;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function readOptions(): array {
		$raw = trim((string)$this->request->request('options', ''));

		if($raw === '') {
			return [];
		}

		$decoded = json_decode($raw, true);

		if(!is_array($decoded)) {
			throw new RuntimeException('Options must be a valid JSON object.');
		}

		return $decoded;
	}

	private function readPositiveInt(string $key, int $default, string $label): int {
		$value = trim((string)$this->request->request($key, ''));

		if($value === '') {
			return $default;
		}

		if(!ctype_digit($value)) {
			throw new RuntimeException($label . ' must be a positive integer.');
		}

		$value = (int)$value;

		return $value > 0 ? $value : $default;
	}

	private function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	private function normalizeAuthType(string $value): string {
		$value = $this->normalizeKey($value);

		if(in_array($value, ['none', 'bearer', 'api-key', 'basic'], true)) {
			return $value;
		}

		return 'none';
	}

	private function normalizeSecretMode(string $value): string {
		$value = $this->normalizeKey($value);

		if(in_array($value, ['fixed', 'env'], true)) {
			return $value;
		}

		return 'fixed';
	}

	private function normalizeBool(mixed $value): bool {
		if(is_bool($value)) {
			return $value;
		}

		$value = strtolower(trim((string)$value));

		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}

	private function buildEndpointBase(): string {
		return $this->linkTargetService->getLink(
			[
				'name' => self::getName()
			],
			[
				'out' => 'json'
			]
		);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function jsonSuccess(array $data): string {
		return (string)json_encode([
			'status' => 'ok',
			'timestamp' => gmdate('c'),
			'data' => $data
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	private function jsonError(string $message): string {
		return (string)json_encode([
			'status' => 'error',
			'timestamp' => gmdate('c'),
			'message' => $message
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
}

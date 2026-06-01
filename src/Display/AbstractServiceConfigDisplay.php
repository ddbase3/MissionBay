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
use MissionBay\Api\IServiceDriverDefinition;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

abstract class AbstractServiceConfigDisplay implements IDisplay {

	public function __construct(
		protected readonly IMvcView $view,
		protected readonly IRequest $request,
		protected readonly ILinkTargetService $linkTargetService,
		protected readonly ISettingsStore $settingsStore,
		protected readonly IClassMap $classMap
	) {}

	abstract public static function getName(): string;

	abstract protected function getSettingsGroup(): string;

	abstract protected function getConnectionGroup(): string;

	abstract protected function getServiceType(): string;

	abstract protected function getTemplate(): string;

	abstract protected function getInstancePrefix(): string;

	abstract protected function getListDataKey(): string;

	abstract protected function getSingleDataKey(): string;

	abstract protected function getMissingIdMessage(): string;

	abstract protected function getMissingNameMessage(): string;

	abstract protected function getMissingDriverMessage(): string;

	abstract protected function getUnknownDriverMessage(string $driver): string;

	abstract protected function getMissingModelMessage(): string;

	/**
	 * @param array<string,mixed> $options
	 * @return array<string,mixed>
	 */
	abstract protected function readSpecificOptions(array $options): array;

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	abstract protected function expandSpecificDisplayOptions(array $row): array;

	/**
	 * Allows concrete service config displays to decide whether a model is required.
	 *
	 * Default remains strict to preserve existing behavior for all service types.
	 *
	 * @param array<string,mixed> $driverDefinition
	 */
	protected function isModelRequiredForDriver(string $driver, array $driverDefinition): bool {
		return true;
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

	protected function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate($this->getTemplate());

		$this->view->assign('instanceId', $this->getInstancePrefix() . '-' . uniqid());
		$this->view->assign('endpoint', $this->buildEndpointBase());
		$this->view->assign('configGroup', $this->getSettingsGroup());
		$this->view->assign('connectionGroup', $this->getConnectionGroup());

		return $this->view->loadTemplate();
	}

	protected function handleJson(): string {
		$action = strtolower(trim((string)$this->request->request('action', '')));

		try {
			return match($action) {
				'list' => $this->jsonSuccess([
					'group' => $this->getSettingsGroup(),
					'connectionGroup' => $this->getConnectionGroup(),
					'drivers' => $this->listDriverDefinitions(),
					'connections' => $this->listConnections(),
					$this->getListDataKey() => $this->listServices()
				]),
				'save' => $this->jsonSuccess([
					'group' => $this->getSettingsGroup(),
					$this->getSingleDataKey() => $this->saveService()
				]),
				'remove' => $this->jsonSuccess([
					'group' => $this->getSettingsGroup(),
					'id' => $this->removeService()
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
	protected function listServices(): array {
		$group = $this->settingsStore->getGroup($this->getSettingsGroup());
		$connections = $this->listConnectionsById();
		$drivers = $this->listDriverDefinitionsByDriver();
		$rows = [];

		foreach($group as $id => $settings) {
			if(!is_string($id) || $id === '' || !is_array($settings)) {
				continue;
			}

			$config = ServiceConfig::fromSettings($id, $settings);

			if($config->getServiceType() !== $this->getServiceType()) {
				continue;
			}

			$row = $config->toDisplayArray();
			$connectionId = $config->getConnectionId();
			$driver = $config->getDriver();

			$row['connectionName'] = (string)($connections[$connectionId]['name'] ?? '');
			$row['connectionType'] = (string)($connections[$connectionId]['type'] ?? '');
			$row['connectionEnabled'] = (bool)($connections[$connectionId]['enabled'] ?? false);
			$row['driverLabel'] = (string)($drivers[$driver]['label'] ?? $driver);

			$rows[] = $this->expandSpecificDisplayOptions($row);
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
	protected function listConnections(): array {
		$rows = array_values($this->listConnectionsById());

		usort($rows, function(array $a, array $b): int {
			return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
		});

		return $rows;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	protected function listConnectionsById(): array {
		$group = $this->settingsStore->getGroup($this->getConnectionGroup());
		$rows = [];

		foreach($group as $id => $settings) {
			if(!is_string($id) || $id === '' || !is_array($settings)) {
				continue;
			}

			$config = ConnectionConfig::fromSettings($id, $settings);
			$row = $config->toDisplayArray();
			$rows[(string)$row['id']] = $row;
		}

		return $rows;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	protected function listDriverDefinitions(): array {
		$rows = array_values($this->listDriverDefinitionsByDriver());

		usort($rows, function(array $a, array $b): int {
			return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
		});

		return $rows;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	protected function listDriverDefinitionsByDriver(): array {
		$instances = $this->classMap->getInstancesByInterface(IServiceDriverDefinition::class);
		$rows = [];

		foreach($instances as $instance) {
			if(!$instance instanceof IServiceDriverDefinition) {
				continue;
			}

			if($instance->getServiceType() !== $this->getServiceType()) {
				continue;
			}

			$driver = $this->normalizeKey($instance->getDriver());

			if($driver === '') {
				continue;
			}

			$rows[$driver] = [
				'name' => $instance::getName(),
				'driver' => $driver,
				'serviceType' => $instance->getServiceType(),
				'label' => $instance->getLabel(),
				'requiresConnection' => $instance->requiresConnection(),
				'supportedConnectionTypes' => $instance->getSupportedConnectionTypes(),
				'configSchema' => $instance->getConfigSchema(),
				'defaultConfig' => $instance->getDefaultConfig()
			];
		}

		return $rows;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function saveService(): array {
		$id = $this->normalizeKey((string)$this->request->request('id', ''));
		$name = trim((string)$this->request->request('name', ''));
		$connection = $this->normalizeKey((string)$this->request->request('connection', ''));
		$driver = $this->normalizeKey((string)$this->request->request('driver', ''));
		$model = trim((string)$this->request->request('model', ''));
		$enabled = $this->normalizeBool($this->request->request('enabled', 0));
		$options = $this->readSpecificOptions($this->readOptions());

		if($id === '') {
			throw new RuntimeException($this->getMissingIdMessage());
		}

		if($name === '') {
			throw new RuntimeException($this->getMissingNameMessage());
		}

		if($connection === '') {
			throw new RuntimeException('Missing connection.');
		}

		if(!$this->settingsStore->has($this->getConnectionGroup(), $connection)) {
			throw new RuntimeException('Connection not found: ' . $connection);
		}

		if($driver === '') {
			throw new RuntimeException($this->getMissingDriverMessage());
		}

		$drivers = $this->listDriverDefinitionsByDriver();

		if(!isset($drivers[$driver])) {
			throw new RuntimeException($this->getUnknownDriverMessage($driver));
		}

		if($model === '' && $this->isModelRequiredForDriver($driver, $drivers[$driver])) {
			throw new RuntimeException($this->getMissingModelMessage());
		}

		$connectionConfig = ConnectionConfig::fromSettings(
			$connection,
			$this->settingsStore->get($this->getConnectionGroup(), $connection, [])
		);

		$connectionData = $connectionConfig->toDisplayArray();
		$connectionType = (string)($connectionData['type'] ?? '');
		$supportedConnectionTypes = $drivers[$driver]['supportedConnectionTypes'] ?? [];

		if(is_array($supportedConnectionTypes) && $supportedConnectionTypes !== [] && !in_array($connectionType, $supportedConnectionTypes, true)) {
			throw new RuntimeException('Connection type "' . $connectionType . '" is not supported by driver "' . $driver . '".');
		}

		$config = new ServiceConfig(
			$id,
			$name,
			$this->getServiceType(),
			$connection,
			$driver,
			$model,
			$enabled,
			$options
		);

		$this->settingsStore->set($this->getSettingsGroup(), $id, $config->toSettings());
		$this->settingsStore->save();

		return $this->expandSpecificDisplayOptions($config->toDisplayArray());
	}

	protected function removeService(): string {
		$id = $this->normalizeKey((string)$this->request->request('id', ''));

		if($id === '') {
			throw new RuntimeException($this->getMissingIdMessage());
		}

		$this->settingsStore->remove($this->getSettingsGroup(), $id);
		$this->settingsStore->save();

		return $id;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function readOptions(): array {
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

	protected function readOptionalFloat(string $key, string $label): ?float {
		$value = trim((string)$this->request->request($key, ''));

		if($value === '') {
			return null;
		}

		if(!is_numeric($value)) {
			throw new RuntimeException($label . ' must be numeric.');
		}

		return (float)$value;
	}

	protected function readOptionalInt(string $key, string $label): ?int {
		$value = trim((string)$this->request->request($key, ''));

		if($value === '') {
			return null;
		}

		if(!ctype_digit($value)) {
			throw new RuntimeException($label . ' must be a positive integer.');
		}

		return (int)$value;
	}

	protected function normalizeNullableNumber(mixed $value): string {
		if($value === null || $value === '') {
			return '';
		}

		if(!is_numeric($value)) {
			return '';
		}

		return (string)$value;
	}

	protected function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	protected function normalizeBool(mixed $value): bool {
		if(is_bool($value)) {
			return $value;
		}

		$value = strtolower(trim((string)$value));

		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}

	protected function buildEndpointBase(): string {
		return $this->linkTargetService->getLink(
			[
				'name' => static::getName()
			],
			[
				'out' => 'json'
			]
		);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	protected function jsonSuccess(array $data): string {
		return (string)json_encode([
			'status' => 'ok',
			'timestamp' => gmdate('c'),
			'data' => $data
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	protected function jsonError(string $message): string {
		return (string)json_encode([
			'status' => 'error',
			'timestamp' => gmdate('c'),
			'message' => $message
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
}

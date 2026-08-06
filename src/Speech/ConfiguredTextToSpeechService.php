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

namespace MissionBay\Speech;

use AssistantFoundation\Api\IServiceDriverDefinition;
use AssistantFoundation\Api\ITextToSpeechService;
use AssistantFoundation\Api\ITextToSpeechStream;
use AssistantFoundation\Dto\TextToSpeechRequest;
use AssistantFoundation\Dto\TextToSpeechResult;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\ITextToSpeechDriver;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

final class ConfiguredTextToSpeechService implements ITextToSpeechService {

	private const SERVICE_GROUP = 'service-tts';
	private const CONNECTION_GROUP = 'connection';
	private const SERVICE_TYPE = 'tts';

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		private readonly IAgentConfigValueResolver $configValueResolver
	) {}

	public function synthesize(
		TextToSpeechRequest $request,
		ITextToSpeechStream $stream
	): TextToSpeechResult {
		$serviceId = $this->normalizeKey($request->getServiceId());
		if($serviceId === '') {
			throw new RuntimeException('Missing text-to-speech service id.');
		}

		$serviceConfig = $this->loadServiceConfig($serviceId);
		$connectionConfig = $this->loadConnectionConfig($serviceConfig->getConnectionId());
		$secret = $this->resolveConnectionSecret($connectionConfig);
		$driver = $this->resolveDriver($serviceConfig->getDriver());

		return $driver->synthesize($serviceConfig, $connectionConfig, $secret, $request, $stream);
	}

	private function loadServiceConfig(string $serviceId): ServiceConfig {
		$settings = $this->settingsStore->get(self::SERVICE_GROUP, $serviceId, []);
		if($settings === []) {
			throw new RuntimeException('Text-to-speech service not found: ' . $serviceId);
		}

		$config = ServiceConfig::fromSettings($serviceId, $settings);
		if(!$config->isEnabled()) {
			throw new RuntimeException('Text-to-speech service is disabled: ' . $serviceId);
		}
		if($config->getServiceType() !== self::SERVICE_TYPE) {
			throw new RuntimeException('Invalid text-to-speech service type: ' . $serviceId);
		}
		if($config->getConnectionId() === '') {
			throw new RuntimeException('Text-to-speech service has no connection: ' . $serviceId);
		}
		if($config->getDriver() === '') {
			throw new RuntimeException('Text-to-speech service has no driver: ' . $serviceId);
		}
		if(trim($config->getModel()) === '') {
			throw new RuntimeException('Text-to-speech service has no model: ' . $serviceId);
		}

		return $config;
	}

	private function loadConnectionConfig(string $connectionId): ConnectionConfig {
		$settings = $this->settingsStore->get(self::CONNECTION_GROUP, $connectionId, []);
		if($settings === []) {
			throw new RuntimeException('Connection not found: ' . $connectionId);
		}

		$config = ConnectionConfig::fromSettings($connectionId, $settings);
		if(!$config->isEnabled()) {
			throw new RuntimeException('Connection is disabled: ' . $connectionId);
		}
		if(trim($config->getBaseUrl()) === '') {
			throw new RuntimeException('Connection has no base URL: ' . $connectionId);
		}

		return $config;
	}

	private function resolveConnectionSecret(ConnectionConfig $connectionConfig): string {
		if($connectionConfig->getAuthType() !== 'bearer') {
			throw new RuntimeException('Text-to-speech requires bearer authentication.');
		}

		$secretConfig = $connectionConfig->getAuthSecretConfig();
		if($secretConfig === []) {
			throw new RuntimeException('Connection has no authentication secret: ' . $connectionConfig->getId());
		}

		$value = $this->configValueResolver->resolveValue($secretConfig);
		if(!is_scalar($value) || trim((string)$value) === '') {
			throw new RuntimeException('Connection secret could not be resolved: ' . $connectionConfig->getId());
		}

		return trim((string)$value);
	}

	private function resolveDriver(string $driverName): ITextToSpeechDriver {
		$driverName = $this->normalizeKey($driverName);
		$definitions = $this->classMap->getInstancesByInterface(IServiceDriverDefinition::class);

		foreach($definitions as $definition) {
			if(!$definition instanceof IServiceDriverDefinition) {
				continue;
			}
			if($this->normalizeKey($definition->getServiceType()) !== 'tts') {
				continue;
			}
			if($this->normalizeKey($definition->getDriver()) !== $driverName) {
				continue;
			}
			if(trim($definition->getImplementationInterface()) !== ITextToSpeechDriver::class) {
				continue;
			}

			$driver = $this->classMap->getInstanceByInterfaceName(
				ITextToSpeechDriver::class,
				trim($definition->getImplementationName())
			);

			if($driver instanceof ITextToSpeechDriver) {
				return $driver;
			}
		}

		throw new RuntimeException('Text-to-speech driver not found: ' . $driverName);
	}

	private function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}

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

use AssistantFoundation\Api\ITextToSpeechService;
use AssistantFoundation\Api\ITextToSpeechStream;
use AssistantFoundation\Dto\TextToSpeechRequest;
use AssistantFoundation\Dto\TextToSpeechResult;
use MissionBay\Api\ITextToSpeechDriver;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

final class ConfiguredTextToSpeechService implements ITextToSpeechService {

	private const SERVICE_GROUP = 'service-tts';
	private const SERVICE_TYPE = 'tts';

	public function __construct(
		private readonly ConfiguredServiceRuntimeResolver $runtimeResolver
	) {}

	public function synthesize(TextToSpeechRequest $request): TextToSpeechResult {
		[$serviceConfig, $connectionConfig, $secret, $driver] = $this->resolve($request);

		return $driver->synthesize(
			$serviceConfig,
			$connectionConfig,
			$secret,
			$request
		);
	}

	public function stream(
		TextToSpeechRequest $request,
		ITextToSpeechStream $stream
	): TextToSpeechResult {
		[$serviceConfig, $connectionConfig, $secret, $driver] = $this->resolve($request);

		return $driver->stream(
			$serviceConfig,
			$connectionConfig,
			$secret,
			$request,
			$stream
		);
	}

	/**
	 * @return array{0:ServiceConfig,1:ConnectionConfig,2:string,3:ITextToSpeechDriver}
	 */
	private function resolve(TextToSpeechRequest $request): array {
		$serviceId = $this->normalizeKey($request->getServiceId());
		if($serviceId === '') {
			throw new RuntimeException('Missing text-to-speech service id.');
		}

		$serviceConfig = $this->runtimeResolver->loadServiceConfig(
			self::SERVICE_GROUP,
			$serviceId,
			self::SERVICE_TYPE
		);
		if(trim($serviceConfig->getModel()) === '') {
			throw new RuntimeException('Text-to-speech service has no model: ' . $serviceId);
		}

		$connectionConfig = $this->runtimeResolver->loadConnectionConfig($serviceConfig->getConnectionId());

		if($connectionConfig->getAuthType() !== 'bearer') {
			throw new RuntimeException('Text-to-speech requires bearer authentication.');
		}

		$secret = $this->runtimeResolver->resolveConnectionSecret($connectionConfig);
		if($secret === null || $secret === '') {
			throw new RuntimeException('Connection secret could not be resolved: ' . $connectionConfig->getId());
		}

		$driver = $this->runtimeResolver->resolveDriver(
			$serviceConfig,
			self::SERVICE_TYPE,
			ITextToSpeechDriver::class
		);

		if(!$driver instanceof ITextToSpeechDriver) {
			throw new RuntimeException('Text-to-speech driver could not be initialized.');
		}

		return [$serviceConfig, $connectionConfig, $secret, $driver];
	}

	private function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}

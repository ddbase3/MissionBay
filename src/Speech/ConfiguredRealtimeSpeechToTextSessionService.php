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

use AssistantFoundation\Api\IRealtimeSpeechToTextSessionService;
use AssistantFoundation\Dto\RealtimeSpeechToTextSession;
use AssistantFoundation\Dto\RealtimeSpeechToTextSessionRequest;
use MissionBay\Api\IRealtimeSpeechToTextDriver;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use RuntimeException;

final class ConfiguredRealtimeSpeechToTextSessionService implements IRealtimeSpeechToTextSessionService {

	private const SERVICE_GROUP = 'service-stt';
	private const SERVICE_TYPE = 'stt';

	public function __construct(
		private readonly ConfiguredServiceRuntimeResolver $runtimeResolver
	) {}

	public function createSession(RealtimeSpeechToTextSessionRequest $request): RealtimeSpeechToTextSession {
		$serviceId = $this->normalizeKey($request->getServiceId());
		if($serviceId === '') {
			throw new RuntimeException('Missing speech-to-text service id.');
		}

		$serviceConfig = $this->runtimeResolver->loadServiceConfig(
			self::SERVICE_GROUP,
			$serviceId,
			self::SERVICE_TYPE
		);
		if(trim($serviceConfig->getModel()) === '') {
			throw new RuntimeException('Speech-to-text service has no model: ' . $serviceId);
		}

		$connectionConfig = $this->runtimeResolver->loadConnectionConfig($serviceConfig->getConnectionId());

		if($connectionConfig->getAuthType() !== 'bearer') {
			throw new RuntimeException('Realtime speech-to-text requires bearer authentication.');
		}

		$secret = $this->runtimeResolver->resolveConnectionSecret($connectionConfig);
		if($secret === null || $secret === '') {
			throw new RuntimeException('Connection secret could not be resolved: ' . $connectionConfig->getId());
		}

		$driver = $this->runtimeResolver->resolveDriver(
			$serviceConfig,
			self::SERVICE_TYPE,
			IRealtimeSpeechToTextDriver::class
		);

		if(!$driver instanceof IRealtimeSpeechToTextDriver) {
			throw new RuntimeException('Realtime speech-to-text driver could not be initialized.');
		}

		return $driver->createSession($serviceConfig, $connectionConfig, $secret, $request);
	}

	private function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}

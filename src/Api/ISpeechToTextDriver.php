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

namespace Base3\MissionBay\Api;

use Base3\AssistantFoundation\Dto\RealtimeSpeechToTextSession;
use Base3\AssistantFoundation\Dto\RealtimeSpeechToTextSessionRequest;
use Base3\CoreFoundation\Api\IBase;
use Base3\MissionBay\Connection\ConnectionConfig;
use Base3\MissionBay\Service\ServiceConfig;

/**
 * Provider driver for realtime speech-to-text sessions.
 */
interface ISpeechToTextDriver extends IBase {

	public function getDriver(): string;

	public function createSession(
		ServiceConfig $serviceConfig,
		ConnectionConfig $connectionConfig,
		string $secret,
		RealtimeSpeechToTextSessionRequest $request
	): RealtimeSpeechToTextSession;
}

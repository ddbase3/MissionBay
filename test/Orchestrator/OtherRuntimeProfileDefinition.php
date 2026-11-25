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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentOrchestratorProfile;

final class OtherRuntimeProfileDefinition implements IAgentOrchestratorProfile {

	public static function getName(): string { return 'otherruntimeprofiledefinition'; }
	public static function getRuntimeId(): string { return 'other-runtime'; }
	public function getId(): string { return 'native-tool-loop'; }
	public function getSettings(): array { throw new \LogicException('Foreign profile must not be materialized.'); }
}

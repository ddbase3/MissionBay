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

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use Base3\MissionBay\Orchestrator\Profile\NativeToolLoopOrchestratorProfile;
use Base3\MissionBay\Orchestrator\Profile\DirectCallOrchestratorProfile;
use Base3\MissionBay\Orchestrator\Profile\ProgramToolLoopOrchestratorProfile;
use Base3\MissionBay\Orchestrator\Profile\LargeCatalogNativeOrchestratorProfile;
use Base3\MissionBay\Orchestrator\Profile\AgentSelectedNativeOrchestratorProfile;

trait NativeProfileRepositoryTestSupport {

	private function nativeDefinitions(): array {
		$bricks = parse_ini_file(dirname(__DIR__, 2) . '/lang/OrchestratorProfiles/en.ini', true, INI_SCANNER_RAW);
		$translation = $this->createStub(ITranslation::class);
		$translation->method('translate')->willReturnCallback(static fn(string $set, string $section, string $key): string => $bricks[$section][$key]);
		return [
			new NativeToolLoopOrchestratorProfile($translation),
			new LargeCatalogNativeOrchestratorProfile($translation),
			new AgentSelectedNativeOrchestratorProfile($translation),
			new DirectCallOrchestratorProfile($translation),
			new ProgramToolLoopOrchestratorProfile($translation)
		];
	}

	private function nativeRepository(?ISettingsStore $settings = null, ?array $definitions = null): AgentOrchestratorProfileRepository {
		if ($settings === null) {
			$settings = $this->createStub(ISettingsStore::class);
			$settings->method('getGroup')->willReturn([]);
		}
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn($definitions ?? $this->nativeDefinitions());
		return new AgentOrchestratorProfileRepository($settings, $classMap);
	}
}

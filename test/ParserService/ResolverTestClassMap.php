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

namespace Base3\MissionBay\Test\ParserService;

use Base3\AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\AssistantFoundation\Dto\ParserFileRequest;
use Base3\AssistantFoundation\Dto\ParserServiceResult;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IParserService;
use Base3\MissionBay\Dto\AgentContentItem;
use Base3\MissionBay\Dto\AgentParsedContent;
use Base3\MissionBay\ParserService\ConfiguredParserServiceResolver;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use PHPUnit\Framework\TestCase;

final class ResolverTestClassMap implements IClassMap {

	public function instantiate(string $class) {
		return $class === ResolverTestParserService::class ? new ResolverTestParserService() : null;
	}

	public function instantiateWith(string $class, array $arguments = []) {
		return $this->instantiate($class);
	}

	public function generate($regenerate = false): void {}

	public function getApps() {
		return [];
	}

	public function &getInstances(array $criteria = []) {
		$result = [];
		return $result;
	}

	public function &getInstancesByInterface($interface) {
		$result = $interface === IServiceDriverDefinition::class
			? [new ResolverTestParserServiceDriverDefinition()]
			: [];
		return $result;
	}

	public function &getInstancesByAppInterface($app, $interface, $retry = false) {
		$result = [];
		return $result;
	}

	public function &getInstanceByAppName($app, $name, $retry = false) {
		$result = null;
		return $result;
	}

	public function getClassByInterfaceName(string $interface, string $name): ?string {
		if($interface === IParserService::class && $name === ResolverTestParserService::getName()) {
			return ResolverTestParserService::class;
		}

		return null;
	}

	public function &getInstanceByInterfaceName($interface, $name, $retry = false) {
		$result = null;
		return $result;
	}

	public function &getInstanceByAppInterfaceName($app, $interface, $name, $retry = false) {
		$result = null;
		return $result;
	}

	public function getPlugins() {
		return [];
	}
}

<?php declare(strict_types=1);

namespace MissionBay\Test\ParserService;

use AssistantFoundation\Api\IServiceDriverDefinition;
use AssistantFoundation\Dto\ParserFileRequest;
use AssistantFoundation\Dto\ParserServiceResult;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IParserService;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;
use MissionBay\ParserService\ConfiguredParserServiceResolver;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
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

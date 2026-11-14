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

namespace Base3\MissionBay\Test\SearchService;

use Base3\AssistantFoundation\Api\IAiProvider;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\Core\Event\EventManager;
use Base3\MissionBay\Ai\AiProviderRequestEventDispatcher;
use Base3\MissionBay\SearchService\MistralWebSearchService;
use PHPUnit\Framework\TestCase;

final class MistralWebSearchTestClassMap implements IClassMap {

	/** @var array<int,object> */
	private array $emptyInstances = [];

	private mixed $emptyInstance = null;

	public function __construct(
		private readonly IAiProvider $provider
	) {}

	public function instantiate(string $class) {
		return null;
	}

	public function instantiateWith(string $class, array $arguments = []) {
		return null;
	}

	public function generate($regenerate = false): void {}

	public function getApps() {
		return [];
	}

	public function &getInstances(array $criteria = []) {
		return $this->emptyInstances;
	}

	public function &getInstancesByInterface($interface) {
		return $this->emptyInstances;
	}

	public function &getInstancesByAppInterface($app, $interface, $retry = false) {
		return $this->emptyInstances;
	}

	public function &getInstanceByAppName($app, $name, $retry = false) {
		return $this->emptyInstance;
	}

	public function getClassByInterfaceName(string $interface, string $name): ?string {
		return null;
	}

	public function &getInstanceByInterfaceName($interface, $name, $retry = false) {
		if($interface === IAiProvider::class) {
			$provider = $this->provider;
			return $provider;
		}

		return $this->emptyInstance;
	}

	public function &getInstanceByAppInterfaceName($app, $interface, $name, $retry = false) {
		return $this->getInstanceByInterfaceName($interface, $name, $retry);
	}

	public function getPlugins() {
		return [];
	}
}

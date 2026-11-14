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

namespace Base3\MissionBay\Test;

use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Dto\ComponentDefinition;
use Base3\MissionBay\MissionBayPlugin;
use PHPUnit\Framework\TestCase;

final class LazyInitContainer implements IContainer {

	/** @var array<string,array{definition:mixed,flags:int}> */
	private array $registrations = [];
	private int $getCalls = 0;

	public function getServiceList(): array {
		return array_keys($this->registrations);
	}

	public function set(string $name, $classDefinition, $flags = 0): IContainer {
		if (($flags & IContainer::NOOVERWRITE) === IContainer::NOOVERWRITE && isset($this->registrations[$name])) {
			return $this;
		}

		$this->registrations[$name] = [
			'definition' => $classDefinition,
			'flags' => (int)$flags
		];

		return $this;
	}

	public function remove(string $name) {
		unset($this->registrations[$name]);
	}

	public function has(string $name): bool {
		return isset($this->registrations[$name]);
	}

	public function get(string $name) {
		$this->getCalls++;
		throw new \LogicException('MissionBayPlugin::init() must not resolve container service ' . $name . '.');
	}

	public function getGetCalls(): int {
		return $this->getCalls;
	}

	/** @return array<string,array{definition:mixed,flags:int}> */
	public function getRegistrations(): array {
		return $this->registrations;
	}
}

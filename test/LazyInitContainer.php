<?php declare(strict_types=1);

namespace MissionBay\Test;

use Base3\Api\IContainer;
use Base3\Core\ComponentDefinition;
use MissionBay\MissionBayPlugin;
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

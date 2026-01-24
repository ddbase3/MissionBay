<?php declare(strict_types=1);

namespace MissionBay\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\MissionBayPlugin;
use Base3\Api\IContainer;
use Base3\Api\IClassMap;
use Base3\Configuration\Api\IConfiguration;
use Base3\Test\Core\ClassMapStub;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentMemoryFactory;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Agent\AgentConfigValueResolver;
use MissionBay\Agent\AgentContextFactory;
use MissionBay\Agent\AgentMemoryFactory;
use MissionBay\Agent\AgentFlowFactory;
use MissionBay\Agent\AgentNodeFactory;
use MissionBay\Agent\AgentRagPayloadNormalizer;
use MissionBay\Agent\AgentResourceFactory;

class MissionBayPluginTest extends TestCase {

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('missionbayplugin', MissionBayPlugin::getName());
	}

	public function testInitRegistersAllServicesWithExpectedFlagsAndCreatesInstances(): void {
		$container = new FakeContainer();

		// Use stubs (no PHPUnit mocks) to avoid "no expectations configured" notices.
		$container->set(IClassMap::class, new ClassMapStub(), IContainer::SHARED);
		$container->set(IConfiguration::class, new ConfigurationStub(), IContainer::SHARED);

		$plugin = new MissionBayPlugin($container);
		$plugin->init();

		// Plugin itself
		$this->assertTrue($container->has(MissionBayPlugin::getName()));
		$this->assertSame($plugin, $container->get(MissionBayPlugin::getName()));
		$this->assertSame(IContainer::SHARED, $container->getFlags(MissionBayPlugin::getName()));

		// Services registered
		$this->assertTrue($container->has(IAgentContextFactory::class));
		$this->assertTrue($container->has(IAgentMemoryFactory::class));
		$this->assertTrue($container->has(IAgentNodeFactory::class));
		$this->assertTrue($container->has(IAgentResourceFactory::class));
		$this->assertTrue($container->has(IAgentConfigValueResolver::class));
		$this->assertTrue($container->has(IAgentFlowFactory::class));
		$this->assertTrue($container->has(IAgentRagPayloadNormalizer::class));

		// Flags
		$this->assertSame(IContainer::SHARED, $container->getFlags(IAgentContextFactory::class));
		$this->assertSame(IContainer::SHARED, $container->getFlags(IAgentMemoryFactory::class));
		$this->assertSame(IContainer::SHARED, $container->getFlags(IAgentNodeFactory::class));
		$this->assertSame(IContainer::SHARED, $container->getFlags(IAgentResourceFactory::class));
		$this->assertSame(IContainer::SHARED | IContainer::NOOVERWRITE, $container->getFlags(IAgentConfigValueResolver::class));
		$this->assertSame(IContainer::SHARED, $container->getFlags(IAgentFlowFactory::class));
		$this->assertSame(IContainer::SHARED | IContainer::NOOVERWRITE, $container->getFlags(IAgentRagPayloadNormalizer::class));

		// Ensure resolution returns concrete implementations and behaves like SHARED singletons.
		$contextFactory1 = $container->get(IAgentContextFactory::class);
		$contextFactory2 = $container->get(IAgentContextFactory::class);
		$this->assertInstanceOf(AgentContextFactory::class, $contextFactory1);
		$this->assertSame($contextFactory1, $contextFactory2);

		$memoryFactory1 = $container->get(IAgentMemoryFactory::class);
		$memoryFactory2 = $container->get(IAgentMemoryFactory::class);
		$this->assertInstanceOf(AgentMemoryFactory::class, $memoryFactory1);
		$this->assertSame($memoryFactory1, $memoryFactory2);

		$nodeFactory1 = $container->get(IAgentNodeFactory::class);
		$nodeFactory2 = $container->get(IAgentNodeFactory::class);
		$this->assertInstanceOf(AgentNodeFactory::class, $nodeFactory1);
		$this->assertSame($nodeFactory1, $nodeFactory2);

		$resourceFactory1 = $container->get(IAgentResourceFactory::class);
		$resourceFactory2 = $container->get(IAgentResourceFactory::class);
		$this->assertInstanceOf(AgentResourceFactory::class, $resourceFactory1);
		$this->assertSame($resourceFactory1, $resourceFactory2);

		$configResolver1 = $container->get(IAgentConfigValueResolver::class);
		$configResolver2 = $container->get(IAgentConfigValueResolver::class);
		$this->assertInstanceOf(AgentConfigValueResolver::class, $configResolver1);
		$this->assertSame($configResolver1, $configResolver2);

		$flowFactory1 = $container->get(IAgentFlowFactory::class);
		$flowFactory2 = $container->get(IAgentFlowFactory::class);
		$this->assertInstanceOf(AgentFlowFactory::class, $flowFactory1);
		$this->assertSame($flowFactory1, $flowFactory2);

		$ragNormalizer1 = $container->get(IAgentRagPayloadNormalizer::class);
		$ragNormalizer2 = $container->get(IAgentRagPayloadNormalizer::class);
		$this->assertInstanceOf(AgentRagPayloadNormalizer::class, $ragNormalizer1);
		$this->assertSame($ragNormalizer1, $ragNormalizer2);
	}

	public function testCheckDependenciesReturnsNotInstalledIfAssistantFoundationPluginMissing(): void {
		$container = new FakeContainer();
		$plugin = new MissionBayPlugin($container);

		$this->assertSame([
			'assistantfoundationplugin_installed' => 'assistantfoundationplugin not installed'
		], $plugin->checkDependencies());
	}

	public function testCheckDependenciesReturnsOkIfAssistantFoundationPluginIsInstalled(): void {
		$container = new FakeContainer();
		$container->set('assistantfoundationplugin', new \stdClass(), IContainer::SHARED);

		$plugin = new MissionBayPlugin($container);

		$this->assertSame([
			'assistantfoundationplugin_installed' => 'Ok'
		], $plugin->checkDependencies());
	}

}

/**
 * Minimal container implementation for tests:
 * - Stores definitions via set()
 * - Resolves callables on get()
 * - Caches SHARED services
 */
class FakeContainer implements IContainer {

	private array $items = [];
	private array $flags = [];
	private array $instances = [];

	public function getServiceList(): array {
		return array_keys($this->items);
	}

	public function set(string $name, $classDefinition, $flags = 0): IContainer {
		$this->items[$name] = $classDefinition;
		$this->flags[$name] = (int)$flags;

		// Reset instance cache on overwrite to keep behavior predictable in tests.
		unset($this->instances[$name]);

		return $this;
	}

	public function remove(string $name) {
		unset($this->items[$name], $this->flags[$name], $this->instances[$name]);
	}

	public function has(string $name): bool {
		return array_key_exists($name, $this->items);
	}

	public function get(string $name) {
		if (!array_key_exists($name, $this->items)) {
			return null;
		}

		$flags = (int)($this->flags[$name] ?? 0);

		if (($flags & IContainer::SHARED) === IContainer::SHARED && array_key_exists($name, $this->instances)) {
			return $this->instances[$name];
		}

		$definition = $this->items[$name];

		// Resolve factories (closures / callables) with the container as argument.
		if (is_callable($definition)) {
			$resolved = $definition($this);

			if (($flags & IContainer::SHARED) === IContainer::SHARED) {
				$this->instances[$name] = $resolved;
			}

			return $resolved;
		}

		// Direct values (already instantiated services, scalars, etc.)
		if (($flags & IContainer::SHARED) === IContainer::SHARED) {
			$this->instances[$name] = $definition;
		}

		return $definition;
	}

	public function getFlags(string $name): ?int {
		return $this->flags[$name] ?? null;
	}

}

/**
 * Simple IConfiguration stub for tests.
 *
 * Updated to the extended IConfiguration interface.
 */
class ConfigurationStub implements IConfiguration {

	private mixed $data = [];
	private bool $dirty = false;

	public function get($configuration = "") {
		if ($configuration === "" || $configuration === null) {
			return $this->data;
		}

		if (!is_array($this->data)) {
			return null;
		}

		return $this->data[$configuration] ?? null;
	}

	public function set($data, $configuration = "") {
		if ($configuration === "" || $configuration === null) {
			$this->data = $data;
			$this->dirty = true;
			return;
		}

		if (!is_array($this->data)) {
			$this->data = [];
		}

		$this->data[$configuration] = $data;
		$this->dirty = true;
	}

	public function save() {
		$this->dirty = false;
	}

	// ---------------------------------------------------------------------
	// Convenience API
	// ---------------------------------------------------------------------

	public function getGroup(string $group, array $default = []): array {
		$val = $this->get($group);
		return is_array($val) ? $val : $default;
	}

	public function getValue(string $group, string $key, $default = null) {
		$g = $this->getGroup($group, []);
		return array_key_exists($key, $g) ? $g[$key] : $default;
	}

	public function getString(string $group, string $key, string $default = ''): string {
		$val = $this->getValue($group, $key, $default);
		if ($val === null) return $default;
		if (is_string($val)) return $val;
		if (is_scalar($val)) return (string)$val;
		return $default;
	}

	public function getInt(string $group, string $key, int $default = 0): int {
		$val = $this->getValue($group, $key, null);
		if ($val === null) return $default;
		if (is_int($val)) return $val;
		if (is_bool($val)) return $val ? 1 : 0;
		if (is_numeric($val)) return (int)$val;
		return $default;
	}

	public function getFloat(string $group, string $key, float $default = 0.0): float {
		$val = $this->getValue($group, $key, null);
		if ($val === null) return $default;
		if (is_float($val)) return $val;
		if (is_int($val)) return (float)$val;
		if (is_numeric($val)) return (float)$val;
		return $default;
	}

	public function getBool(string $group, string $key, bool $default = false): bool {
		$val = $this->getValue($group, $key, null);
		if ($val === null) return $default;
		if (is_bool($val)) return $val;

		if (is_int($val)) return $val !== 0;
		if (is_float($val)) return $val !== 0.0;

		if (is_string($val)) {
			$v = strtolower(trim($val));
			if ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on') return true;
			if ($v === '0' || $v === 'false' || $v === 'no' || $v === 'off' || $v === '') return false;
		}

		return $default;
	}

	public function getArray(string $group, string $key, array $default = []): array {
		$val = $this->getValue($group, $key, null);
		if ($val === null) return $default;
		if (is_array($val)) return $val;

		if (is_string($val)) {
			$trim = trim($val);
			if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
				$decoded = json_decode($trim, true);
				if (is_array($decoded)) return $decoded;
			}
		}

		return $default;
	}

	public function hasGroup(string $group): bool {
		$g = $this->get($group);
		return is_array($g);
	}

	public function hasValue(string $group, string $key): bool {
		$g = $this->getGroup($group, []);
		return array_key_exists($key, $g);
	}

	public function setValue(string $group, string $key, $value): void {
		$g = $this->getGroup($group, []);
		$g[$key] = $value;
		$this->set($g, $group);
	}

	public function setGroup(string $group, array $values, bool $merge = true): void {
		if (!$merge) {
			$this->set($values, $group);
			return;
		}

		$existing = $this->getGroup($group, []);
		$this->set(array_replace($existing, $values), $group);
	}

	public function setMany(array $data, bool $merge = true): void {
		foreach ($data as $group => $values) {
			if (!is_string($group)) continue;

			if (!is_array($values)) {
				$this->set($values, $group);
				continue;
			}

			$this->setGroup($group, $values, $merge);
		}
	}

	public function removeGroup(string $group): void {
		if (!is_array($this->data)) return;
		if (!array_key_exists($group, $this->data)) return;
		unset($this->data[$group]);
		$this->dirty = true;
	}

	public function removeValue(string $group, string $key): void {
		$g = $this->getGroup($group, []);
		if (!array_key_exists($key, $g)) return;
		unset($g[$key]);
		$this->set($g, $group);
	}

	public function isDirty(): bool {
		return $this->dirty;
	}

	public function saveIfDirty(): bool {
		if (!$this->dirty) return true;
		return $this->trySave();
	}

	public function trySave(): bool {
		$this->save();
		return true;
	}

	public function reload(): void {
		$this->dirty = false;
	}

	public function persistValue(string $group, string $key, $value): bool {
		$this->setValue($group, $key, $value);
		return $this->saveIfDirty();
	}

}

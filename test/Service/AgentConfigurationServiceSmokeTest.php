<?php declare(strict_types=1);

use AssistantFoundation\Api\IAgentDefinition;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Service\AgentConfigurationService;

$root = dirname(__DIR__, 4);
foreach (['DIR_ROOT' => $root . '/', 'DIR_SRC' => $root . '/src/', 'DIR_PLUGIN' => $root . '/plugin/', 'DIR_TEST' => $root . '/test/'] as $name => $path) {
	if (!defined($name)) define($name, $path);
}
require_once DIR_SRC . 'Core/Autoloader.php';
Base3\Core\Autoloader::register();

final class FixedAgentTestStore implements ISettingsStore {
	public array $groups = [];
	public int $writes = 0;
	public int $deletes = 0;
	public function get(string $group, string $name, array $default = []): array { return $this->groups[$group][$name] ?? $default; }
	public function has(string $group, string $name): bool { return array_key_exists($name, $this->groups[$group] ?? []); }
	public function getGroup(string $group): array { return $this->groups[$group] ?? []; }
	public function set(string $group, string $name, array $settings): void { $this->groups[$group][$name] = $settings; $this->writes++; }
	public function remove(string $group, string $name): void { unset($this->groups[$group][$name]); $this->deletes++; }
	public function save(): void {}
	public function reload(): void {}
}

final class FixedAgentTestClassMap implements IClassMap {
	public function __construct(public array $definitions) {}
	public function instantiate(string $class) { return null; }
	public function instantiateWith(string $class, array $arguments = []) { return null; }
	public function generate($regenerate = false): void {}
	public function getApps() { return []; }
	public function &getInstances(array $criteria = []) { $value = $this->definitions; return $value; }
	public function &getInstancesByInterface($interface) { $value = $this->definitions; return $value; }
	public function &getInstancesByAppInterface($app, $interface, $retry = false) { $value = []; return $value; }
	public function &getInstanceByAppName($app, $name, $retry = false) { $value = null; return $value; }
	public function getClassByInterfaceName(string $interface, string $name): ?string { return null; }
	public function &getInstanceByInterfaceName($interface, $name, $retry = false) { $value = null; return $value; }
	public function &getInstanceByAppInterfaceName($app, $interface, $name, $retry = false) { $value = null; return $value; }
	public function getPlugins() { return []; }
}

final class FirstFixedAgent implements IAgentDefinition {
	public static function getName(): string { return 'firstfixedagent'; }
	public function getId(): string { return 'alpha'; }
	public function getLabel(): string { return 'Alpha'; }
	public function getDefaultSettings(): array { return ['agent_runtime' => 'missionbay', 'enabled' => false, 'label' => 'Alpha', 'chatmodel' => '']; }
}
final class SecondFixedAgent implements IAgentDefinition {
	public static function getName(): string { return 'secondfixedagent'; }
	public function getId(): string { return 'beta'; }
	public function getLabel(): string { return 'Beta'; }
	public function getDefaultSettings(): array { return ['agent_runtime' => 'missionbay', 'enabled' => false, 'label' => 'Beta']; }
}
final class DuplicateFixedAgent implements IAgentDefinition {
	public static function getName(): string { return 'duplicatefixedagent'; }
	public function getId(): string { return 'alpha'; }
	public function getLabel(): string { return 'Duplicate Alpha'; }
	public function getDefaultSettings(): array { return ['enabled' => false]; }
}
function fixedCheck(bool $condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
}
function fixedThrows(callable $run, string $expectedClass): void {
	try { $run(); } catch (Throwable $error) {
		fixedCheck($error instanceof $expectedClass, 'Wrong error type: ' . $error::class);
		return;
	}
	throw new RuntimeException('Expected rejection was not enforced: ' . $expectedClass);
}

$store = new FixedAgentTestStore();
$map = new FixedAgentTestClassMap([new FirstFixedAgent(), new SecondFixedAgent()]);
$service = new AgentConfigurationService($store, $map);
fixedCheck(array_keys($service->getAgents()) === ['alpha', 'beta'], 'Multiple code-defined agents not discovered.');
fixedCheck($service->isFixed('alpha') && $service->isFixed('beta') && !$service->isFixed('other'), 'Incorrect fixed identity ownership.');
fixedCheck($service->getAgent('alpha')['enabled'] === false && $store->writes === 0, 'Virtual agent was incorrectly persisted or enabled.');
fixedCheck($service->hasAgent('alpha') && $service->hasAgent('beta'), 'Virtual fixed agent must exist without a settings record.');
fixedCheck($service->getAgent('missing') === null, 'Unknown agent must not be created by lookup.');

$store->groups['agent']['alpha'] = ['enabled' => true, 'label' => 'Override attempted', 'chatmodel' => 'model-1', 'tool_profiles' => ['tools'], 'memory_profile' => 'memory', 'context_profile' => 'context'];
$effective = $service->getAgent('alpha');
fixedCheck($effective['label'] === 'Alpha' && $effective['chatmodel'] === 'model-1', 'Saved settings did not win except for fixed identity.');
fixedCheck($effective['tool_profiles'] === ['tools'] && $effective['memory_profile'] === 'memory' && $effective['context_profile'] === 'context', 'Custom runtime configuration was lost.');
fixedCheck($store->groups['agent']['alpha']['label'] === 'Override attempted', 'Reads must not rewrite pre-existing settings.');
fixedThrows(fn() => $service->deleteAgent('alpha'), LogicException::class);
fixedCheck($store->deletes === 0, 'Fixed agent deletion reached storage.');
fixedThrows(fn() => $service->saveAgent('alpha', ['label' => 'Renamed']), InvalidArgumentException::class);
fixedCheck($store->writes === 0, 'Fixed agent rename reached storage.');
$service->saveAgent('alpha', ['label' => 'Alpha', 'enabled' => true, 'chatmodel' => 'model-2']);
fixedCheck($store->groups['agent']['alpha']['chatmodel'] === 'model-2' && $store->writes === 1, 'Editing a fixed agent failed.');
$service->saveAgent('custom', ['label' => 'Custom']);
$service->deleteAgent('custom');
fixedCheck(!$service->hasAgent('custom') && $store->deletes === 1, 'Normal agent lifecycle changed.');
$store->groups['external-agents'] = ['external' => ['label' => 'External']];
fixedCheck(array_keys($service->getAgents('external-agents')) === ['external'], 'Unrelated settings groups must remain unmodified.');
fixedCheck($service->getAgent('alpha', 'external-agents') === null, 'Fixed agents leaked into unrelated groups.');
$map->definitions[] = new DuplicateFixedAgent();
fixedThrows(fn() => $service->getAgents(), LogicException::class);
echo "Fixed agent configuration: OK\n";

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

final class ConfiguredParserServiceResolverTest extends TestCase {

	public function testListsEnabledParserServiceIdsOnly(): void {
		$resolver = $this->createResolver();

		self::assertSame(
			['docling_default', 'unstructured_default'],
			$resolver->listServiceIds()
		);
	}

	public function testResolvesConfiguredParserIntoIsolatedRuntimeInstance(): void {
		$resolver = $this->createResolver();

		$first = $resolver->resolve('unstructured_default');
		$second = $resolver->resolve('unstructured_default');

		self::assertInstanceOf(ResolverTestParserService::class, $first);
		self::assertInstanceOf(ResolverTestParserService::class, $second);
		self::assertNotSame($first, $second);
		self::assertSame('unstructured_default', $first->getOptions()['parser_id'] ?? null);
		self::assertSame('http://parser.local', $first->getOptions()['endpoint'] ?? null);
		self::assertSame(35, $first->getOptions()['priority'] ?? null);
		self::assertSame(['file'], $first->getOptions()['supported_types'] ?? null);
	}

	public function testMapsParserSpecificRuntimeOptions(): void {
		$resolver = $this->createResolver();
		$service = $resolver->resolve('docling_default');

		self::assertSame('/v1/convert/file', $service->getOptions()['convert_path'] ?? null);
		self::assertSame(['pdf', 'docx'], $service->getOptions()['supported_extensions'] ?? null);
	}

	public function testReturnsConfiguredParserPriorityWithoutRuntimeResolution(): void {
		$resolver = $this->createResolver();

		self::assertSame(35, $resolver->getPriority('unstructured_default'));
		self::assertSame(45, $resolver->getPriority('docling_default'));
	}

	public function testDescribesConfiguredParserWithoutResolvingConnectionSecret(): void {
		$resolver = $this->createResolver();
		$definition = $resolver->describe('docling_default');

		self::assertSame('docling_default', $definition->getId());
		self::assertSame('fake-parser', $definition->getDriver());
		self::assertSame(45, $definition->getPriority());
		self::assertSame(['file'], $definition->getSupportedTypes());
		self::assertSame(['pdf', 'docx'], $definition->getSupportedExtensions());
	}

	public function testDescriptionUsesDriverDefaultsForMissingCapabilities(): void {
		$resolver = $this->createResolver();
		$definition = $resolver->describe('unstructured_default');

		self::assertSame(['file'], $definition->getSupportedTypes());
		self::assertSame(['pdf'], $definition->getSupportedExtensions());
	}

	private function createResolver(): ConfiguredParserServiceResolver {
		$settingsStore = new ResolverTestSettingsStore([
			'service-parser' => [
				'unstructured_default' => [
					'id' => 'unstructured_default',
					'name' => 'Unstructured Default',
					'serviceType' => 'parser',
					'connection' => 'parser-http',
					'driver' => 'fake-parser',
					'model' => 'default',
					'enabled' => true,
					'options' => [
						'priority' => 35,
						'supportedTypes' => ['file'],
						'fileField' => 'files',
					]
				],
				'docling_default' => [
					'id' => 'docling_default',
					'name' => 'Docling Default',
					'serviceType' => 'parser',
					'connection' => 'parser-http',
					'driver' => 'fake-parser',
					'model' => 'default',
					'enabled' => true,
					'options' => [
						'priority' => 45,
						'supportedTypes' => ['file'],
						'supportedExtensions' => ['pdf', 'docx'],
						'convertPath' => '/v1/convert/file',
					]
				],
				'disabled_parser' => [
					'id' => 'disabled_parser',
					'name' => 'Disabled',
					'serviceType' => 'parser',
					'connection' => 'parser-http',
					'driver' => 'fake-parser',
					'model' => 'default',
					'enabled' => false,
				],
				'not_a_parser' => [
					'id' => 'not_a_parser',
					'name' => 'Other Service',
					'serviceType' => 'embedding',
					'connection' => 'parser-http',
					'driver' => 'fake-parser',
					'model' => 'default',
					'enabled' => true,
				],
			],
			'connection' => [
				'parser-http' => [
					'id' => 'parser-http',
					'name' => 'Parser HTTP',
					'type' => 'http',
					'driver' => 'http',
					'baseUrl' => 'http://parser.local',
					'auth' => [
						'type' => 'none',
					],
					'timeoutSeconds' => 60,
					'enabled' => true,
				],
			],
		]);

		return new ConfiguredParserServiceResolver(
			new ConfiguredServiceRuntimeResolver(
				$settingsStore,
				new ResolverTestClassMap(),
				new ResolverTestConfigValueResolver()
			)
		);
	}
}

final class ResolverTestSettingsStore implements ISettingsStore {

	/**
	 * @param array<string,array<string,array<string,mixed>>> $data
	 */
	public function __construct(private array $data) {}

	public function get(string $group, string $name, array $default = []): array {
		return $this->data[$group][$name] ?? $default;
	}

	public function set(string $group, string $name, array $settings): void {
		$this->data[$group][$name] = $settings;
	}

	public function has(string $group, string $name): bool {
		return isset($this->data[$group][$name]);
	}

	public function remove(string $group, string $name): void {
		unset($this->data[$group][$name]);
	}

	public function getGroup(string $group): array {
		return $this->data[$group] ?? [];
	}

	public function save(): void {}
	public function reload(): void {}
}

final class ResolverTestConfigValueResolver implements IAgentConfigValueResolver {

	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		return is_array($config) ? ($config['value'] ?? null) : $config;
	}
}

final class ResolverTestParserServiceDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'resolvertestparserservicedriverdefinition';
	}

	public function getDriver(): string {
		return 'fake-parser';
	}

	public function getServiceType(): string {
		return 'parser';
	}

	public function getLabel(): string {
		return 'Fake Parser';
	}

	public function requiresConnection(): bool {
		return true;
	}

	public function getSupportedConnectionTypes(): array {
		return ['http'];
	}

	public function getImplementationInterface(): string {
		return IParserService::class;
	}

	public function getImplementationName(): string {
		return ResolverTestParserService::getName();
	}

	public function getConfigSchema(): array {
		return [];
	}

	public function getDefaultConfig(): array {
		return [
			'options' => [
				'supportedTypes' => ['file'],
				'supportedExtensions' => ['pdf'],
				'priority' => 50,
			]
		];
	}
}

final class ResolverTestParserService implements IParserService {

	/** @var array<string,mixed> */
	private array $options = [];

	public static function getName(): string {
		return 'resolvertestparserservice';
	}

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function getPriority(): int {
		return (int)($this->options['priority'] ?? 50);
	}

	public function supportsFile(ParserFileRequest $request): bool {
		return true;
	}

	public function parseFile(ParserFileRequest $request): ParserServiceResult {
		return new ParserServiceResult('parsed');
	}

	public function supports(AgentContentItem $item): bool {
		return true;
	}

	public function parse(AgentContentItem $item): AgentParsedContent {
		return new AgentParsedContent('parsed');
	}
}

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

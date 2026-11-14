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

require_once __DIR__ . '/ResolverTestSettingsStore.php';
require_once __DIR__ . '/ResolverTestConfigValueResolver.php';
require_once __DIR__ . '/ResolverTestParserServiceDriverDefinition.php';
require_once __DIR__ . '/ResolverTestParserService.php';
require_once __DIR__ . '/ResolverTestClassMap.php';

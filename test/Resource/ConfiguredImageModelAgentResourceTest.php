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

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Api\IImageGenerationModel;
use Base3\AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\ImageModel\MistralImageModel;
use Base3\MissionBay\Resource\ConfiguredImageModelAgentResource;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use Base3\MissionBay\ServiceDriver\MistralImageServiceDriverDefinition;
use PHPUnit\Framework\TestCase;

final class ConfiguredImageModelAgentResourceTest extends TestCase {

	public function testResolvesMistralDriverAndMapsSchemaOptionsToRuntimeKeys(): void {
		$settingsStore = $this->createStub(ISettingsStore::class);
		$settingsStore->method('get')->willReturnCallback(
			static function(string $group, string $name, array $default = []): array {
				if($group === 'service-image' && $name === 'mistral_course_images') {
					return [
						'id' => 'mistral_course_images',
						'name' => 'Mistral Course Images',
						'serviceType' => 'image',
						'connection' => 'mistral',
						'driver' => 'mistral-image',
						'model' => 'mistral-small-latest',
						'enabled' => true,
						'options' => [
							'toolChoice' => 'required',
							'timeoutSeconds' => 180,
							'connectTimeoutSeconds' => 25,
							'endpoint' => 'https://duplicate.example.test',
							'api_key' => 'duplicate-key',
							'authType' => 'none'
						]
					];
				}

				if($group === 'connection' && $name === 'mistral') {
					return [
						'id' => 'mistral',
						'name' => 'Mistral',
						'type' => 'http',
						'driver' => 'http',
						'baseUrl' => 'https://api.mistral.ai',
						'auth' => [
							'type' => 'bearer',
							'secret' => [
								'mode' => 'env',
								'name' => 'MISTRAL_API_KEY'
							]
						],
						'timeoutSeconds' => 120,
						'scope' => 'global',
						'enabled' => true,
						'options' => []
					];
				}

				return $default;
			}
		);

		$resolver = new ConfiguredImageModelAgentResourceTestDouble1();

		$capturedOptions = [];
		$imageModel = $this->createMock(IImageGenerationModel::class);
		$imageModel->expects($this->once())
			->method('setOptions')
			->willReturnCallback(static function(array $options) use (&$capturedOptions): void {
				$capturedOptions = $options;
			});

		$driverDefinition = new MistralImageServiceDriverDefinition();
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')
			->with(IServiceDriverDefinition::class)
			->willReturn([$driverDefinition]);
		$classMap->method('getClassByInterfaceName')
			->with(IImageGenerationModel::class, MistralImageModel::getName())
			->willReturn(MistralImageModel::class);
		$classMap->method('instantiate')
			->with(MistralImageModel::class)
			->willReturn($imageModel);

		$resource = new ConfiguredImageModelAgentResource(
			$resolver,
			$settingsStore,
			new ConfiguredServiceRuntimeResolver($settingsStore, $classMap, $resolver),
			'course-image'
		);
		$resource->setConfig([
			'service' => 'mistral_course_images'
		]);
		$resource->getOptions();

		$this->assertSame('mistral-small-latest', $capturedOptions['model']);
		$this->assertSame('https://api.mistral.ai', $capturedOptions['endpoint']);
		$this->assertSame('mistral-test-key', $capturedOptions['apikey']);
		$this->assertArrayNotHasKey('tool_choice', $capturedOptions);
		$this->assertSame(180, $capturedOptions['timeout_seconds']);
		$this->assertSame(25, $capturedOptions['connect_timeout_seconds']);
		$this->assertArrayNotHasKey('size', $capturedOptions);
	}
}

require_once __DIR__ . '/ConfiguredImageModelAgentResourceTestDouble1.php';
